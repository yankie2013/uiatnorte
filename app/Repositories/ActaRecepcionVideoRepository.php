<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ActaRecepcionVideoRepository
{
    public function __construct(private PDO $pdo) {}

    public function accident(int $id): ?array
    {
        $query = $this->pdo->prepare(
            "SELECT a.id, a.fecha_accidente, a.lugar, a.referencia,
                    COALESCE(ud.nombre, '') AS distrito,
                    COALESCE(u.nombre, '') AS jefe_emi_nombre,
                    COALESCE(u.grado, '') AS jefe_emi_grado,
                    COALESCE(u.cip, '') AS jefe_emi_cip,
                    COALESCE(u.cargo, '') AS jefe_emi_cargo,
                    COALESCE(u.unidad, '') AS jefe_emi_unidad,
                    COALESCE(u.rol, '') AS responsable_rol,
                    COALESCE((SELECT GROUP_CONCAT(m.nombre ORDER BY m.nombre SEPARATOR ', ')
                                FROM accidente_modalidad_activos am
                                JOIN modalidad_accidente m ON m.id=am.modalidad_id
                               WHERE am.accidente_id=a.id), '') AS modalidades
               FROM accidentes_activos a
          LEFT JOIN ubigeo_distrito ud ON ud.cod_dep=a.cod_dep AND ud.cod_prov=a.cod_prov AND ud.cod_dist=a.cod_dist
          LEFT JOIN usuarios u ON u.id=a.responsable_id
              WHERE a.id=? LIMIT 1"
        );
        $query->execute([$id]);
        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function find(int $id): ?array
    {
        $query = $this->pdo->prepare('SELECT * FROM actas_recepcion_video WHERE id=? LIMIT 1');
        $query->execute([$id]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['datos'] = json_decode((string) $row['datos_json'], true) ?: [];
        return $row;
    }

    public function listByAccidente(int $accidenteId): array
    {
        $query = $this->pdo->prepare('SELECT * FROM actas_recepcion_video WHERE accidente_id=? ORDER BY id DESC');
        $query->execute([$accidenteId]);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['datos'] = json_decode((string) $row['datos_json'], true) ?: [];
        }
        unset($row);
        return $rows;
    }

    public function save(?int $id, int $accidenteId, array $data, int $userId): int
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if ($id === null) {
            $query = $this->pdo->prepare('INSERT INTO actas_recepcion_video (accidente_id, datos_json, creado_por, actualizado_por) VALUES (?, ?, ?, ?)');
            $query->execute([$accidenteId, $json, $userId, $userId]);
            return (int) $this->pdo->lastInsertId();
        }
        $query = $this->pdo->prepare('UPDATE actas_recepcion_video SET datos_json=?, actualizado_por=? WHERE id=? AND accidente_id=? LIMIT 1');
        $query->execute([$json, $userId, $id, $accidenteId]);
        return $id;
    }
}
