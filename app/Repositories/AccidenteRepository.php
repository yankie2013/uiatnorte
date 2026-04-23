<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class AccidenteRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function departamentos(): array
    {
        return $this->pdo->query('SELECT cod_dep,nombre FROM ubigeo_departamento ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fiscalias(): array
    {
        return $this->pdo->query('SELECT id,nombre FROM fiscalia ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function accidenteById(int $accidenteId): ?array
    {
        $sql = 'SELECT id, sidpol, registro_sidpol, tipo_registro, lugar, referencia,
                       cod_dep, cod_prov, cod_dist, comisaria_id,
                       fecha_accidente, estado, fecha_comunicacion, fecha_intervencion,
                       comunicante_nombre, comunicante_telefono,
                       comunicacion_decreto, comunicacion_oficio, comunicacion_carpeta_nro,
                       fiscalia_id, fiscal_id, nro_informe_policial,
                       sentido, secuencia
                  FROM accidentes
                 WHERE id=?';
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        $accidente = $st->fetch(PDO::FETCH_ASSOC);

        if (!$accidente) {
            return null;
        }

        $accidente['cod_dep'] = str_pad((string) ($accidente['cod_dep'] ?? ''), 2, '0', STR_PAD_LEFT);
        $accidente['cod_prov'] = str_pad((string) ($accidente['cod_prov'] ?? ''), 2, '0', STR_PAD_LEFT);
        $accidente['cod_dist'] = str_pad((string) ($accidente['cod_dist'] ?? ''), 2, '0', STR_PAD_LEFT);

        return $accidente;
    }

    public function modalidades(): array
    {
        return $this->pdo->query('SELECT id,nombre FROM modalidad_accidente ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function consecuencias(): array
    {
        return $this->pdo->query('SELECT id,nombre FROM consecuencia_accidente ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function provinciasByDepartamento(string $dep): array
    {
        $st = $this->pdo->prepare('SELECT cod_dep,cod_prov,nombre FROM ubigeo_provincia WHERE cod_dep=? ORDER BY nombre');
        $st->execute([$dep]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function distritos(string $dep, string $prov): array
    {
        $st = $this->pdo->prepare('SELECT cod_dep,cod_prov,cod_dist,nombre FROM ubigeo_distrito WHERE cod_dep=? AND cod_prov=? ORDER BY nombre');
        $st->execute([$dep, $prov]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function comisariasByDistrito(string $dep, string $prov, string $dist): array
    {
        $sql = 'SELECT c.id, c.nombre
                  FROM comisarias c
                  JOIN comisaria_distrito cd ON cd.comisaria_id=c.id
                 WHERE cd.cod_dep=? AND cd.cod_prov=? AND cd.cod_dist=?
                 ORDER BY c.nombre';
        $st = $this->pdo->prepare($sql);
        $st->execute([$dep, $prov, $dist]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fiscalesByFiscalia(int $fiscaliaId): array
    {
        $sql = "SELECT id, CONCAT(nombres,' ',apellido_paterno,' ',apellido_materno) AS nombre
                  FROM fiscales
                 WHERE fiscalia_id=?
                 ORDER BY nombres,apellido_paterno";
        $st = $this->pdo->prepare($sql);
        $st->execute([$fiscaliaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fiscalTelefono(int $fiscalId): array
    {
        $st = $this->pdo->prepare('SELECT telefono FROM fiscales WHERE id=?');
        $st->execute([$fiscalId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function distritoExists(string $dep, string $prov, string $dist): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM ubigeo_distrito WHERE cod_dep=? AND cod_prov=? AND cod_dist=? LIMIT 1');
        $st->execute([$dep, $prov, $dist]);
        return (bool) $st->fetchColumn();
    }

    public function comisariaMappedToDistrito(int $comisariaId, string $dep, string $prov, string $dist): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM comisaria_distrito WHERE comisaria_id=? AND cod_dep=? AND cod_prov=? AND cod_dist=? LIMIT 1');
        $st->execute([$comisariaId, $dep, $prov, $dist]);
        return (bool) $st->fetchColumn();
    }

    public function fiscalBelongsToFiscalia(int $fiscalId, ?int $fiscaliaId): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM fiscales WHERE id=? AND fiscalia_id=? LIMIT 1');
        $st->execute([$fiscalId, $fiscaliaId ?: 0]);
        return (bool) $st->fetchColumn();
    }

    public function findComisariaIdByNombre(string $nombre): ?int
    {
        $st = $this->pdo->prepare('SELECT id FROM comisarias WHERE nombre COLLATE utf8mb4_unicode_ci=? LIMIT 1');
        $st->execute([$nombre]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function createComisaria(string $nombre): int
    {
        $st = $this->pdo->prepare('INSERT INTO comisarias (nombre) VALUES (?)');
        $st->execute([$nombre]);
        return (int) $this->pdo->lastInsertId();
    }

    public function comisariaById(int $comisariaId): ?array
    {
        $st = $this->pdo->prepare('SELECT id, nombre FROM comisarias WHERE id=?');
        $st->execute([$comisariaId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function mapComisariaToDistrito(int $comisariaId, string $dep, string $prov, string $dist): void
    {
        $st = $this->pdo->prepare('INSERT IGNORE INTO comisaria_distrito (comisaria_id,cod_dep,cod_prov,cod_dist) VALUES (?,?,?,?)');
        $st->execute([$comisariaId, $dep, $prov, $dist]);
    }

    public function createFiscal(int $fiscaliaId, string $nombres, ?string $apellidoPaterno, ?string $apellidoMaterno, ?string $cargo, ?string $telefono): int
    {
        $st = $this->pdo->prepare('INSERT INTO fiscales (fiscalia_id,nombres,apellido_paterno,apellido_materno,cargo,telefono) VALUES (?,?,?,?,?,?)');
        $st->execute([$fiscaliaId, $nombres, $apellidoPaterno, $apellidoMaterno, $cargo, $telefono]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findCatalogIdByName(string $table, string $column, string $nombre): ?int
    {
        $st = $this->pdo->prepare("SELECT id FROM {$table} WHERE {$column} COLLATE utf8mb4_unicode_ci=? LIMIT 1");
        $st->execute([$nombre]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function createCatalogItem(string $table, string $column, string $nombre): int
    {
        $st = $this->pdo->prepare("INSERT INTO {$table} ({$column}) VALUES (?)");
        $st->execute([$nombre]);
        return (int) $this->pdo->lastInsertId();
    }

    public function transaction(callable $callback): mixed
    {
        try {
            $this->pdo->beginTransaction();
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function insertAccidente(array $payload): int
    {
        $sql = 'INSERT INTO accidentes
            (registro_sidpol,tipo_registro,lugar,referencia,cod_dep,cod_prov,cod_dist,comisaria_id,
             fecha_accidente,estado,fecha_comunicacion,fecha_intervencion,
             comunicante_nombre,comunicante_telefono,comunicacion_decreto,comunicacion_oficio,comunicacion_carpeta_nro,
             fiscalia_id,fiscal_id,nro_informe_policial,sentido,secuencia)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';

        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['registro_sidpol'],
            $payload['tipo_registro'],
            $payload['lugar'],
            $payload['referencia'],
            $payload['cod_dep'],
            $payload['cod_prov'],
            $payload['cod_dist'],
            $payload['comisaria_id'],
            $payload['fecha_accidente'],
            $payload['estado'],
            $payload['fecha_comunicacion'],
            $payload['fecha_intervencion'],
            $payload['comunicante_nombre'],
            $payload['comunicante_telefono'],
            $payload['comunicacion_decreto'],
            $payload['comunicacion_oficio'],
            $payload['comunicacion_carpeta_nro'],
            $payload['fiscalia_id'],
            $payload['fiscal_id'],
            $payload['nro_informe_policial'],
            $payload['sentido'],
            $payload['secuencia'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateAccidente(int $accidenteId, array $payload): void
    {
        $sql = 'UPDATE accidentes SET
                  registro_sidpol=?,
                  tipo_registro=?,
                  lugar=?, referencia=?, cod_dep=?, cod_prov=?, cod_dist=?, comisaria_id=?,
                  fecha_accidente=?, estado=?, fecha_comunicacion=?, fecha_intervencion=?,
                  comunicante_nombre=?, comunicante_telefono=?, comunicacion_decreto=?, comunicacion_oficio=?, comunicacion_carpeta_nro=?,
                  fiscalia_id=?, fiscal_id=?, nro_informe_policial=?,
                  sentido=?, secuencia=?
                WHERE id=?';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['registro_sidpol'],
            $payload['tipo_registro'],
            $payload['lugar'],
            $payload['referencia'],
            $payload['cod_dep'],
            $payload['cod_prov'],
            $payload['cod_dist'],
            $payload['comisaria_id'],
            $payload['fecha_accidente'],
            $payload['estado'],
            $payload['fecha_comunicacion'],
            $payload['fecha_intervencion'],
            $payload['comunicante_nombre'],
            $payload['comunicante_telefono'],
            $payload['comunicacion_decreto'],
            $payload['comunicacion_oficio'],
            $payload['comunicacion_carpeta_nro'],
            $payload['fiscalia_id'],
            $payload['fiscal_id'],
            $payload['nro_informe_policial'],
            $payload['sentido'],
            $payload['secuencia'],
            $accidenteId,
        ]);
    }

    public function modalidadIdsForAccidente(int $accidenteId): array
    {
        $st = $this->pdo->prepare('SELECT modalidad_id FROM accidente_modalidad WHERE accidente_id=?');
        $st->execute([$accidenteId]);
        return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'modalidad_id'));
    }

    public function consecuenciaIdsForAccidente(int $accidenteId): array
    {
        $st = $this->pdo->prepare('SELECT consecuencia_id FROM accidente_consecuencia WHERE accidente_id=?');
        $st->execute([$accidenteId]);
        return array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'consecuencia_id'));
    }

    public function attachModalidades(int $accidenteId, array $modalidadIds): void
    {
        if ($modalidadIds === []) {
            return;
        }

        $st = $this->pdo->prepare('INSERT IGNORE INTO accidente_modalidad (accidente_id, modalidad_id) VALUES (?,?)');
        foreach ($modalidadIds as $modalidadId) {
            $st->execute([$accidenteId, $modalidadId]);
        }
    }

    public function attachConsecuencias(int $accidenteId, array $consecuenciaIds): void
    {
        if ($consecuenciaIds === []) {
            return;
        }

        $st = $this->pdo->prepare('INSERT IGNORE INTO accidente_consecuencia (accidente_id, consecuencia_id) VALUES (?,?)');
        foreach ($consecuenciaIds as $consecuenciaId) {
            $st->execute([$accidenteId, $consecuenciaId]);
        }
    }

    public function updateSidpol(int $accidenteId, string $sidpol): void
    {
        $st = $this->pdo->prepare('UPDATE accidentes SET sidpol=? WHERE id=?');
        $st->execute([$sidpol, $accidenteId]);
    }

    public function syncModalidades(int $accidenteId, array $modalidadIds): void
    {
        $current = $this->modalidadIdsForAccidente($accidenteId);
        $toAdd = array_diff($modalidadIds, $current);
        $toDelete = array_diff($current, $modalidadIds);

        if ($toAdd !== []) {
            $st = $this->pdo->prepare('INSERT IGNORE INTO accidente_modalidad (accidente_id, modalidad_id) VALUES (?,?)');
            foreach ($toAdd as $modalidadId) {
                if ((int) $modalidadId > 0) {
                    $st->execute([$accidenteId, $modalidadId]);
                }
            }
        }

        if ($toDelete !== []) {
            $st = $this->pdo->prepare('DELETE FROM accidente_modalidad WHERE accidente_id=? AND modalidad_id=?');
            foreach ($toDelete as $modalidadId) {
                $st->execute([$accidenteId, $modalidadId]);
            }
        }
    }

    public function syncConsecuencias(int $accidenteId, array $consecuenciaIds): void
    {
        $current = $this->consecuenciaIdsForAccidente($accidenteId);
        $toAdd = array_diff($consecuenciaIds, $current);
        $toDelete = array_diff($current, $consecuenciaIds);

        if ($toAdd !== []) {
            $st = $this->pdo->prepare('INSERT IGNORE INTO accidente_consecuencia (accidente_id, consecuencia_id) VALUES (?,?)');
            foreach ($toAdd as $consecuenciaId) {
                if ((int) $consecuenciaId > 0) {
                    $st->execute([$accidenteId, $consecuenciaId]);
                }
            }
        }

        if ($toDelete !== []) {
            $st = $this->pdo->prepare('DELETE FROM accidente_consecuencia WHERE accidente_id=? AND consecuencia_id=?');
            foreach ($toDelete as $consecuenciaId) {
                $st->execute([$accidenteId, $consecuenciaId]);
            }
        }
    }
}
