<?php
declare(strict_types=1);
namespace App\Support;
use App\Database\Database;
use PDO;
use RuntimeException;

final class Access
{
    private const CATALOG_CREATE_PAGES = [
        'oficio_entidad_nuevo.php', 'oficio_subentidad_nuevo.php', 'oficio_persona_entidad_nuevo.php',
        'oficio_asunto_nuevo.php', 'oficio_cargo_nuevo.php', 'oficio_oficial_ano_nuevo.php',
        'add_catalogo.php', 'involucrados_vehiculos_nuevo.php',
    ];
    public const ROLES = ['admin'=>'Administrador','jefe_emi'=>'JEFE EMI','adjunto'=>'ADJUNTO','secretaria'=>'Secretaría','administracion'=>'Administración','guardia'=>'Comandante de guardia','viewer'=>'Consulta (anterior)','editor'=>'Consulta (anterior)'];
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
            'secretaria', 'administracion' => "EXISTS (SELECT 1 FROM expediente_transferencias workspace_archive WHERE workspace_archive.accidente_id = $alias.id AND workspace_archive.destino_id = $id AND workspace_archive.tipo = 'archivo' AND workspace_archive.estado = 'aceptada')",
            default => '1=0',
        };
    }
    public static function canViewWorkspaceCase(int $case): bool {
        $role = self::role();
        if ($role === 'admin' || in_array($role, ['viewer','editor'], true)) return true;
        // Archivo consulta solo sus expedientes aceptados; Guardia conserva sus tarjetas y registro guiado.
        if (!in_array($role, ['jefe_emi','adjunto','secretaria','administracion'], true)) return false;
        $predicate = self::workspacePredicate('a');
        $s = Database::connection()->prepare("SELECT 1 FROM accidentes_activos a WHERE a.id=? AND ($predicate) LIMIT 1");
        $s->execute([$case]);
        return (bool)$s->fetchColumn();
    }
    public static function requireWorkspaceCase(int $case): void {
        if ($case <= 0 || !self::canViewWorkspaceCase($case)) {
            http_response_code(403);
            exit(self::role()==='guardia' ? 'Guardia solo puede consultar las tarjetas de sus registros.' : 'No tienes autorización para abrir este expediente fuera de tu espacio de trabajo. Puedes consultarlo desde el buscador general.');
        }
    }
    public static function canOpenInvestigationCase(int $case): bool {
        return !in_array(self::role(), ['secretaria','administracion'], true) && self::canViewWorkspaceCase($case);
    }
    public static function requireInvestigationCase(int $case): void {
        if (!self::canOpenInvestigationCase($case)) {
            http_response_code(403);
            exit('Archivo permite consultar la ficha del expediente, sin abrir el espacio de edición.');
        }
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
        $s=Database::connection()->prepare("SELECT COALESCE(investigador.nombre,u.nombre) nombre,COALESCE(investigador.grado,u.grado) grado,COALESCE(investigador.cip,u.cip) cip,COALESCE(investigador.cargo,u.cargo) cargo,COALESCE(investigador.unidad,u.unidad) unidad,COALESCE(investigador.telefono,u.telefono) telefono,COALESCE(investigador.email,u.email) email
            FROM accidentes a JOIN usuarios u ON u.id=a.responsable_id
            LEFT JOIN expediente_transferencias archivo ON archivo.id=(SELECT MAX(t.id) FROM expediente_transferencias t WHERE t.accidente_id=a.id AND t.destino_id=a.responsable_id AND t.tipo='archivo' AND t.estado='aceptada')
            LEFT JOIN usuarios investigador ON investigador.id=archivo.origen_id WHERE a.id=?");$s->execute([$case]);
        return $s->fetch() ?: ['nombre'=>'Responsable pendiente de asignación','grado'=>'','cip'=>'','cargo'=>'','unidad'=>'DEPIAT'];
    }
    public static function documentProfile(array $document): array {
        $snapshot = json_decode((string)($document['responsable_documento'] ?? ''), true);
        if (!empty($document['gestion']) && !is_array($snapshot)) return ['nombre'=>'','grado'=>'','cip'=>'','cargo'=>'','unidad'=>'','telefono'=>'','email'=>''];
        return is_array($snapshot) ? $snapshot : self::profile((int)($document['accidente_id'] ?? 0));
    }
    public static function greeting(int $case = 0): string {
        $user = self::actor();
        $grade = preg_replace('/[.\s]+/u', ' ', trim((string)($user['grado'] ?? ''))) ?? '';
        $name = trim((string)($user['nombre'] ?? 'Personal policial'));
        return 'Hola, le saluda '.trim($grade.' '.$name);
    }
    public static function requestGuard(): void {
        if(PHP_SAPI==='cli')return;
        $script=basename((string)($_SERVER['SCRIPT_NAME']??''));
        if(in_array($script,['login.php','logout.php','cambiar_clave.php','session_keepalive.php','session_resume.php'],true))return;
        if(!self::id()) { http_response_code(401); exit('Inicia sesión para continuar.'); }
        if (in_array($script, ['catalogos.php', 'oficio_entidad_editar.php'], true) && !self::admin()) {
            http_response_code(403);
            exit('La administración y edición de catálogos está reservada al administrador.');
        }
        if (!self::admin() && in_array($script, ['persona_listar.php','vehiculo_listar.php','comisarias_listar.php','comisarias_nuevo.php','comisarias_editar.php','comisarias_eliminar.php','oficio_entidades_listar.php'], true)) {
            http_response_code(403);
            exit('Este directorio está reservado al administrador.');
        }
        $action=implode(' ',array_filter([$_POST['action']??'',$_POST['accion']??'',$_POST['do']??'',$_GET['action']??'',$_GET['accion']??''], 'is_scalar'));
        $deleting=preg_match('/eliminar|delete|_delete|\bdel\b|\bborrar\b/i',$script.' '.$action);
        if($deleting && !self::admin()) {http_response_code(403);exit('Solo el administrador puede eliminar registros.');}
        // Se bloquean también las rutas de diagnóstico que realizan escrituras sin un formulario.
        if(preg_match('/^(tmp_|test_|.*_diag\.php|.*_debug\.php)/',$script) && !self::admin()) {header('Location: index.php', true, 303);exit;}
        $post=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
        if($post) {
            try {self::checkCsrf();} catch(\Throwable $e){http_response_code(419);exit($e->getMessage());}
        }
        if($deleting && !$post && $script!=='accidente_eliminar.php') {
            echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Confirmar eliminación</title><body><h1>Eliminar registro</h1><p>Esta acción está reservada al administrador. El historial conservará el registro eliminado.</p><form method="post">';
            foreach($_GET as $key=>$value) if(is_scalar($value))echo '<input type="hidden" name="'.htmlspecialchars((string)$key,ENT_QUOTES).'" value="'.htmlspecialchars((string)$value,ENT_QUOTES).'">';
            echo '<input type="hidden" name="_csrf" value="'.self::csrf().'"><input type="hidden" name="confirm" value="1"><button>Confirmar eliminación</button></form></body></html>';exit;
        }

        $guardiaCreation = self::role()==='guardia' && in_array($script,['accidente_nuevo.php','involucrados_vehiculos_nuevo.php','involucrados_personas_nuevo.php','persona_nuevo.php','vehiculo_nuevo.php'],true);
        $newPage=in_array($script,['gestion_expedientes.php','guardia_registro.php','guardia.php','usuarios_gestion.php','estadisticas.php'],true);
        $catalogCreationPage = in_array($script, self::CATALOG_CREATE_PAGES, true);
        $catalogAjaxCreate = $script === 'involucrados_vehiculos_nuevo.php'
            && preg_match('/^crear_(categoria|tipo|carroceria|marca|modelo)$/', (string) ($_GET['ajax'] ?? '')) === 1;
        if($post && !$newPage && !in_array($script,['buscar_dni.php','buscar_placa.php','buscar_personas_nombre.php','documento_recibido_analizar_ia.php'],true)) {
            if(in_array(self::role(),['secretaria','administracion','viewer','editor','guardia'],true) && !$catalogCreationPage && !$catalogAjaxCreate && !$guardiaCreation && $script!=='expedientes_recepcion.php' && !in_array($script, ['oficios_nuevo.php','oficios_editar.php'], true)) {http_response_code(403);exit('Este perfil tiene acceso de consulta. Guardia registra y corrige desde Comunicaciones.');}
        }
    }
}
