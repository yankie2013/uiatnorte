<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentoManifestacionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function accidenteLabel(int $id): string
    {
        $sidpolColumn = $this->columnExists('accidentes', 'registro_sidpol') ? 'registro_sidpol' : ($this->columnExists('accidentes', 'sidpol') ? 'sidpol' : null);
        $select = [];
        if ($sidpolColumn) {
            $select[] = "`{$sidpolColumn}` AS sidpol";
        }
        if ($this->columnExists('accidentes', 'fecha_accidente')) {
            $select[] = "DATE_FORMAT(fecha_accidente,'%Y-%m-%d') AS fecha";
        }
        if ($this->columnExists('accidentes', 'lugar')) {
            $select[] = 'lugar';
        }
        if ($select === []) {
            return '#' . $id;
        }
        $st = $this->pdo->prepare('SELECT ' . implode(',', $select) . ' FROM accidentes WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return '#' . $id . ' - ' . trim(($row['sidpol'] ?? '') . ' ' . ($row['fecha'] ?? '') . ' ' . ($row['lugar'] ?? ''));
    }

    public function personaLabel(int $id): string
    {
        $st = $this->pdo->prepare('SELECT num_doc, CONCAT(nombres,\' \' ,apellido_paterno,\' \' ,apellido_materno) AS nombre FROM personas WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return (($row['nombre'] ?? 'Persona') ?: 'Persona') . (!empty($row['num_doc']) ? (' - ' . $row['num_doc']) : '');
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM Manifestacion WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $payload): int
    {
        $st = $this->pdo->prepare('INSERT INTO Manifestacion (accidente_id,persona_id,fecha,horario_inicio,hora_termino,modalidad) VALUES (?,?,?,?,?,?)');
        $st->execute($payload);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $st = $this->pdo->prepare('UPDATE Manifestacion SET fecha=?, horario_inicio=?, hora_termino=?, modalidad=? WHERE id=? LIMIT 1');
        $st->execute([...$payload, $id]);
    }

    private function columnExists(string $table, string $column): bool
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$table, $column]);
        return (int) $st->fetchColumn() > 0;
    }
}
