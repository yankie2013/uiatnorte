<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class CatalogoOficioRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function entidades(): array
    {
        $st = $this->pdo->query('SELECT id, tipo, nombre, siglas, direccion, telefono, correo, pagina_web FROM oficio_entidad ORDER BY nombre ASC');
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findEntidad(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT id, tipo, nombre, siglas, direccion, telefono, correo, pagina_web FROM oficio_entidad WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createEntidad(array $payload): int
    {
        $sql = 'INSERT INTO oficio_entidad (tipo, nombre, siglas, direccion, telefono, correo, pagina_web) VALUES (?, ?, ?, ?, ?, ?, ?)';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['tipo'],
            $payload['nombre'],
            $payload['siglas'],
            $payload['direccion'],
            $payload['telefono'],
            $payload['correo'],
            $payload['pagina_web'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateEntidad(int $id, array $payload): void
    {
        $sql = 'UPDATE oficio_entidad SET tipo = ?, nombre = ?, siglas = ?, direccion = ?, telefono = ?, correo = ?, pagina_web = ? WHERE id = ?';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['tipo'],
            $payload['nombre'],
            $payload['siglas'],
            $payload['direccion'],
            $payload['telefono'],
            $payload['correo'],
            $payload['pagina_web'],
            $id,
        ]);
    }

    public function createAsunto(array $payload): int
    {
        $sql = 'INSERT INTO oficio_asunto (entidad_id, tipo, nombre, detalle, orden, activo) VALUES (?, ?, ?, ?, ?, 1)';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['entidad_id'],
            $payload['tipo'],
            $payload['nombre'],
            $payload['detalle'],
            $payload['orden'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function subentidadesByEntidad(int $entidadId): array
    {
        $st = $this->pdo->prepare('SELECT id, entidad_id, nombre, siglas, tipo, codigo, parent_id FROM oficio_subentidad WHERE entidad_id = ? ORDER BY nombre ASC');
        $st->execute([$entidadId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createSubentidad(array $payload): int
    {
        $sql = 'INSERT INTO oficio_subentidad (entidad_id, nombre, siglas, tipo, codigo, pagina_web, direccion, telefono, correo, parent_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['entidad_id'],
            $payload['nombre'],
            $payload['siglas'],
            $payload['tipo'],
            $payload['codigo'],
            $payload['pagina_web'],
            $payload['direccion'],
            $payload['telefono'],
            $payload['correo'],
            $payload['parent_id'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createPersonaEntidad(array $payload): int
    {
        $sql = 'INSERT INTO oficio_persona_entidad (entidad_id, nombres, apellido_paterno, apellido_materno, telefono, direccion, pagina_web, correo, observacion, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['entidad_id'],
            $payload['nombres'],
            $payload['apellido_paterno'],
            $payload['apellido_materno'],
            $payload['telefono'],
            $payload['direccion'],
            $payload['pagina_web'],
            $payload['correo'],
            $payload['observacion'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createGradoCargo(array $payload): int
    {
        $sql = 'INSERT INTO grado_cargo (tipo, nombre, abreviatura, orden, activo) VALUES (?, ?, ?, ?, ?)';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['tipo'],
            $payload['nombre'],
            $payload['abreviatura'],
            $payload['orden'],
            $payload['activo'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function oficialAnoRows(): array
    {
        $st = $this->pdo->query('SELECT id, anio, nombre, decreto, vigente FROM oficio_oficial_ano ORDER BY anio DESC');
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createOficialAno(array $payload): int
    {
        try {
            $this->pdo->beginTransaction();
            if ((int) $payload['vigente'] === 1) {
                $this->pdo->exec('UPDATE oficio_oficial_ano SET vigente = 0');
            }
            $st = $this->pdo->prepare('INSERT INTO oficio_oficial_ano (anio, nombre, decreto, vigente) VALUES (?, ?, ?, ?)');
            $st->execute([
                $payload['anio'],
                $payload['nombre'],
                $payload['decreto'],
                $payload['vigente'],
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
