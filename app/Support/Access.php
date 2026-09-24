<?php
declare(strict_types=1);
namespace App\Support;
use App\Database\Database;
use PDO;
use RuntimeException;

final class Access
{
    public const ROLES = ['admin'=>'Administrador','jefe_emi'=>'JEFE EMI','adjunto'=>'ADJUNTO','secretaria'=>'Secretaría','guardia'=>'Comandante de guardia','viewer'=>'Consulta (anterior)','editor'=>'Consulta (anterior)'];
    public static function actor(): array { Database::connection(); return $_SESSION['user'] ?? []; }
    public static function id(): int { return (int)(self::actor()['id'] ?? 0); }
    public static function role(): string { return (string)(self::actor()['rol'] ?? ''); }
    public static function admin(): bool { return self::role()==='admin'; }
    /** Personal workspace only; general consultation and edit permissions remain separate. */
    public static function workspacePredicate(string $alias = 'a'): string {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $alias)) {
            throw new \InvalidArgumentException('Alias SQL inválido.');
        }
        $actor = self::actor();
        $id = (int)($actor['id'] ?? 0);
        if ($id <= 0) return '1=0';
        return match ($actor['rol'] ?? '') {
            'admin' => '1=1',
            'jefe_emi' => "$alias.responsable_id = $id",
            'adjunto' => "EXISTS (SELECT 1 FROM expediente_colaboradores workspace_ec WHERE workspace_ec.accidente_id = $alias.id AND workspace_ec.usuario_id = $id AND workspace_ec.revocado_en IS NULL)",
            'guardia' => "EXISTS (SELECT 1 FROM comunicaciones_guardia workspace_cg WHERE workspace_cg.accidente_id = $alias.id AND workspace_cg.creado_por = $id AND workspace_cg.eliminado_en IS NULL)",
            default => '1=0',
        };
    }
    public static function canEdit(int $case): bool {
        $s=Database::connection()->prepare('SELECT rbac_case(?)');$s->execute([$case]);return (bool)$s->fetchColumn();
    }
    public static function requireEdit(int $case): void { if(!self::canEdit($case)) throw new RuntimeException('Solo el responsable o un adjunto con colaboración activa puede editar este expediente.'); }
    public static function csrf(): string {
        if(empty($_SESSION['rbac_csrf']))$_SESSION['rbac_csrf']=bin2hex(random_bytes(32));
        return $_SESSION['rbac_csrf'];
    }
    public static function checkCsrf(): void {
        $value=(string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if(!hash_equals(self::csrf(),$value)) throw new RuntimeException('La sesión del formulario venció. Recarga la página.');
    }
    public static function guardEditable(array $row, int $actor, string $role, ?int $now=null): bool {
        return empty($row['eliminado_en']) && ($role==='admin' || ($role==='guardia' && (int)$row['creado_por']===$actor && empty($row['eliminado_en']) && ($now ?? time()) < strtotime($row['registrado_en'])+43200));
    }
    public static function profile(int $case): array {
        $s=Database::connection()->prepare('SELECT u.nombre,u.grado,u.cip,u.cargo,u.unidad,u.telefono,u.email FROM accidentes a JOIN usuarios u ON u.id=a.responsable_id WHERE a.id=?');$s->execute([$case]);
        return $s->fetch() ?: ['nombre'=>'Responsable pendiente de asignación','grado'=>'','cip'=>'','cargo'=>'','unidad'=>'DEPIAT'];
    }
    public static function documentProfile(array $document): array {
        $snapshot = json_decode((string)($document['responsable_documento'] ?? ''), true);
        return is_array($snapshot) ? $snapshot : self::profile((int)($document['accidente_id'] ?? 0));
    }
    public static function greeting(int $case): string {
        $p=self::profile($case);
        return 'Buen día le saluda '.trim(($p['grado']??'').' '.($p['nombre']??'')).' de '.($p['unidad']??'DEPIAT');
    }
    public static function requestGuard(): void {
        if(PHP_SAPI==='cli')return;
        $script=basename((string)($_SERVER['SCRIPT_NAME']??''));
        if(in_array($script,['login.php','logout.php','cambiar_clave.php','session_keepalive.php','session_resume.php'],true))return;
        if(!self::id()) { http_response_code(401); exit('Inicia sesión para continuar.'); }
        $action=implode(' ',array_filter([$_POST['action']??'',$_POST['accion']??'',$_POST['do']??'',$_GET['action']??'',$_GET['accion']??''], 'is_scalar'));
        $deleting=preg_match('/eliminar|delete|_delete|\bdel\b|\bborrar\b/i',$script.' '.$action);
        if($deleting && !self::admin()) {http_response_code(403);exit('Solo el administrador puede eliminar registros.');}
        // Se bloquean también las rutas de diagnóstico que realizan escrituras sin un formulario.
        if(preg_match('/^(tmp_|test_|.*_diag\.php|.*_debug\.php)/',$script) && !self::admin()) {http_response_code(403);exit('Acceso exclusivo del administrador.');}
        $post=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
        if($post) {
            try {self::checkCsrf();} catch(\Throwable $e){http_response_code(419);exit($e->getMessage());}
        }
        if($deleting && !$post && $script!=='accidente_eliminar.php') {
            echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Confirmar eliminación</title><body><h1>Eliminar registro</h1><p>Esta acción está reservada al administrador. El historial conservará el registro eliminado.</p><form method="post">';
            foreach($_GET as $key=>$value) if(is_scalar($value))echo '<input type="hidden" name="'.htmlspecialchars((string)$key,ENT_QUOTES).'" value="'.htmlspecialchars((string)$value,ENT_QUOTES).'">';
            echo '<input type="hidden" name="_csrf" value="'.self::csrf().'"><input type="hidden" name="confirm" value="1"><button>Confirmar eliminación</button></form></body></html>';exit;
        }

        $newPage=in_array($script,['gestion_expedientes.php','guardia.php','usuarios_gestion.php','estadisticas.php'],true);
        if($post && !$newPage && !in_array($script,['buscar_dni.php','buscar_placa.php','buscar_personas_nombre.php','documento_recibido_analizar_ia.php'],true)) {
            if(in_array(self::role(),['secretaria','viewer','editor','guardia'],true)) {http_response_code(403);exit('Este perfil tiene acceso de consulta. Guardia registra y corrige desde Comunicaciones.');}
        }
    }
}
