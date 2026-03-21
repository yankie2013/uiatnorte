<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ComisariaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        $st = $this->pdo->query('SELECT id, nombre, tipo, direccion, telefono, correo, lat, lon, notas, activo, creado_en, actualizado_en FROM comisarias ORDER BY nombre ASC');
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT id, nombre, tipo, direccion, telefono, correo, lat, lon, notas, activo, creado_en, actualizado_en FROM comisarias WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO comisarias (nombre, tipo, direccion, telefono, correo, lat, lon, notas, activo, creado_en, actualizado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['nombre'],
            $payload['tipo'],
            $payload['direccion'],
            $payload['telefono'],
            $payload['correo'],
            $payload['lat'],
            $payload['lon'],
            $payload['notas'],
            $payload['activo'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sql = 'UPDATE comisarias SET nombre = ?, tipo = ?, direccion = ?, telefono = ?, correo = ?, lat = ?, lon = ?, notas = ?, activo = ?, actualizado_en = NOW() WHERE id = ?';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $payload['nombre'],
            $payload['tipo'],
            $payload['direccion'],
            $payload['telefono'],
            $payload['correo'],
            $payload['lat'],
            $payload['lon'],
            $payload['notas'],
            $payload['activo'],
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM comisarias WHERE id = ?');
        $st->execute([$id]);
    }
}
