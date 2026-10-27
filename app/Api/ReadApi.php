<?php
declare(strict_types=1);
namespace App\Api;

use PDO;
use RuntimeException;

final class ApiError extends RuntimeException
{
    public function __construct(public int $status, string $message) { parent::__construct($message); }
}

/** No connection to the application's write-capable Database singleton. */
final class ReadApi
{
    public function __construct(private PDO $pdo) {}

    public static function positive(mixed $value, int $max = 2147483647): int
    {
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,9}$/D', $value) || (float)$value > $max) {
            throw new ApiError(422, 'Parámetro numérico inválido.');
        }
        return (int)$value;
    }

    public static function authenticate(array $session, array|false $user, int $now): array
    {
        $started = $session['api_authenticated_at'] ?? 0;
        $last = $session['api_last_seen'] ?? $started;
        if (!$user || !is_int($started) || !is_int($last) || $started <= 0 || $started > $now || $last > $now
            || $now - $started >= 28800 || $now - $last >= 900
            || (int)($session['user']['id'] ?? 0) !== (int)$user['id']
            || (int)$user['activo'] !== 1 || (int)$user['must_change_password'] !== 0
            || !isset($session['user']['auth_version'])
            || (int)$session['user']['auth_version'] !== (int)$user['auth_version']) {
            throw new ApiError(401, 'Inicia sesión nuevamente.');
        }
        if (!in_array($user['rol'], ['admin','jefe_emi','adjunto','secretaria','administracion'], true)) {
            throw new ApiError(403, 'Perfil sin acceso a esta API.');
        }
        return $user;
    }

    private function scope(array $actor): array
    {
        return match ($actor['rol']) {
            'admin' => ['1=1', []],
            'jefe_emi' => ['a.responsable_id=?', [(int)$actor['id']]],
            'adjunto' => ['EXISTS (SELECT 1 FROM expediente_colaboradores ec WHERE ec.accidente_id=a.id AND ec.usuario_id=? AND ec.revocado_en IS NULL)', [(int)$actor['id']]],
            'secretaria','administracion' => ["EXISTS (SELECT 1 FROM expediente_transferencias et WHERE et.accidente_id=a.id AND et.destino_id=? AND et.tipo='archivo' AND et.estado='aceptada')", [(int)$actor['id']]],
            default => ['1=0', []],
        };
    }

    private function rows(string $sql, array $params): array
    {
        $s = $this->pdo->prepare($sql);
        foreach ($params as $i => $value) $s->bindValue($i + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $s->execute();
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function handle(string $endpoint, array $query, array $actor): array
    {
        $allowed = $endpoint === 'buscar_expedientes' ? ['q','limite','despues_id'] :
            ($endpoint === 'consultar_diligencias' ? ['expediente_id','limite','despues_id'] : ['expediente_id']);
        if (array_diff(array_keys($query), $allowed)) throw new ApiError(422, 'Parámetro desconocido.');
        [$scope, $params] = $this->scope($actor);
        $fields = 'a.id,a.sidpol,a.registro_sidpol,a.fecha_accidente,a.estado';
        if ($endpoint === 'buscar_expedientes') {
            $q = $query['q'] ?? '';
            if (!is_string($q) || strlen($q) < 3 || strlen($q) > 80 || !preg_match('/^[\p{L}\p{N} .\/-]+$/uD', $q)) {
                throw new ApiError(422, 'Busca por código de expediente (3 a 80 caracteres).');
            }
            $limit = self::positive($query['limite'] ?? '20', 50);
            $after = isset($query['despues_id']) ? self::positive($query['despues_id']) : 0;
            $rows = $this->rows("SELECT $fields FROM accidentes_activos a WHERE ($scope) AND a.id>? AND (a.sidpol LIKE ? OR a.registro_sidpol LIKE ?) ORDER BY a.id LIMIT ?", [...$params,$after,$q.'%',$q.'%',$limit + 1]);
        } elseif (in_array($endpoint, ['consultar_expediente','consultar_diligencias'], true)) {
            $id = self::positive($query['expediente_id'] ?? null);
            $case = $this->rows("SELECT $fields FROM accidentes_activos a WHERE ($scope) AND a.id=?", [...$params,$id]);
            if (!$case) throw new ApiError(404, 'Expediente no disponible.');
            if ($endpoint === 'consultar_expediente') return ['datos' => $case[0]];
            $limit = self::positive($query['limite'] ?? '20', 50);
            $after = isset($query['despues_id']) ? self::positive($query['despues_id']) : 0;
            // Reapply authorization in the data query, including after a concurrent revocation.
            $rows = $this->rows("SELECT d.id,d.accidente_id,d.tipo_diligencia_id,d.estado FROM diligencias_pendientes_activos d JOIN accidentes_activos a ON a.id=d.accidente_id WHERE ($scope) AND a.id=? AND d.id>? ORDER BY d.id LIMIT ?", [...$params,$id,$after,$limit + 1]);
        } else {
            throw new ApiError(404, 'Ruta no disponible.');
        }
        $more = count($rows) > $limit;
        if ($more) array_pop($rows);
        return ['datos'=>$rows,'siguiente_id'=>$more ? (int)end($rows)['id'] : null];
    }
}
