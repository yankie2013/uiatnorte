<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class PersonaRepository
{
    private const FIELDS = [
        'tipo_doc',
        'num_doc',
        'apellido_paterno',
        'apellido_materno',
        'nombres',
        'sexo',
        'fecha_nacimiento',
        'edad',
        'estado_civil',
        'nacionalidad',
        'departamento_nac',
        'provincia_nac',
        'distrito_nac',
        'domicilio',
        'domicilio_departamento',
        'domicilio_provincia',
        'domicilio_distrito',
        'ocupacion',
        'grado_instruccion',
        'nombre_padre',
        'nombre_madre',
        'celular',
        'email',
        'notas',
        'foto_path',
        'api_fuente',
        'api_ref',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function paginate(string $query, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $where = 'WHERE 1=1';
        $params = [];
        if ($query !== '') {
            $where .= ' AND (num_doc LIKE :q1 OR apellido_paterno LIKE :q2 OR apellido_materno LIKE :q3 OR nombres LIKE :q4)';
            $like = '%' . $query . '%';
            $params = [
                ':q1' => $like,
                ':q2' => $like,
                ':q3' => $like,
                ':q4' => $like,
            ];
        }

        $st = $this->pdo->prepare("SELECT COUNT(*) FROM personas $where");
        $st->execute($params);
        $total = (int) $st->fetchColumn();
        $pages = max(1, (int) ceil($total / $limit));
        if ($page > $pages) {
            $page = $pages;
            $offset = ($page - 1) * $limit;
        }

        $sql = "SELECT id, tipo_doc, num_doc, apellido_paterno, apellido_materno, nombres, sexo, fecha_nacimiento, edad, celular, email
                FROM personas
                $where
                ORDER BY id DESC
                LIMIT :off, :lim";
        $st = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $st->bindValue($key, $value, PDO::PARAM_STR);
        }
        $st->bindValue(':off', $offset, PDO::PARAM_INT);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();

        return [
            'rows' => $st->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'limit' => $limit,
            'query' => $query,
        ];
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM personas WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByDocumentNumber(string $number): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM personas WHERE num_doc = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$number]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function existsDuplicate(string $tipoDoc, string $numDoc, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM personas WHERE tipo_doc = ? AND num_doc = ?';
        $params = [$tipoDoc, $numDoc];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    public function create(array $payload): int
    {
        $columns = implode(', ', self::FIELDS);
        $placeholders = implode(', ', array_map(static fn (string $field): string => ':' . $field, self::FIELDS));
        $sql = "INSERT INTO personas ($columns, creado_en) VALUES ($placeholders, NOW())";
        $st = $this->pdo->prepare($sql);
        $st->execute($this->bindPayload($payload));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sets = implode(', ', array_map(static fn (string $field): string => $field . ' = :' . $field, self::FIELDS));
        $sql = "UPDATE personas SET $sets WHERE id = :id LIMIT 1";
        $params = $this->bindPayload($payload);
        $params[':id'] = $id;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM personas WHERE id = ? LIMIT 1');
        $st->execute([$id]);
    }

    public function involvementCount(int $id): int
    {
        return $this->safeCount('SELECT COUNT(*) FROM involucrados_personas WHERE persona_id = ?', [$id]);
    }

    public function referenceSummary(int $id): array
    {
        $checks = [
            'Involucrados de persona' => ['SELECT COUNT(*) FROM involucrados_personas WHERE persona_id = ?', [$id]],
            'Intervinientes policiales' => ['SELECT COUNT(*) FROM policial_interviniente WHERE persona_id = ?', [$id]],
            'Propietario de vehiculo' => ['SELECT COUNT(*) FROM propietario_vehiculo WHERE propietario_persona_id = ?', [$id]],
            'Representante legal de vehiculo' => ['SELECT COUNT(*) FROM propietario_vehiculo WHERE representante_persona_id = ?', [$id]],
            'Familiar de fallecido' => ['SELECT COUNT(*) FROM familiar_fallecido WHERE familiar_persona_id = ?', [$id]],
            'Abogados' => ['SELECT COUNT(*) FROM abogados WHERE persona_id = ?', [$id]],
            'Citaciones' => ['SELECT COUNT(*) FROM citacion WHERE persona_id = ?', [$id]],
            'Manifestaciones' => ['SELECT COUNT(*) FROM Manifestacion WHERE persona_id = ?', [$id]],
            'Documento RML' => ['SELECT COUNT(*) FROM documento_rml WHERE persona_id = ?', [$id]],
            'Documento dosaje' => ['SELECT COUNT(*) FROM documento_dosaje WHERE persona_id = ?', [$id]],
            'Documento occiso' => ['SELECT COUNT(*) FROM documento_occiso WHERE persona_id = ?', [$id]],
            'Documento licencia de conducir' => ['SELECT COUNT(*) FROM documento_lc WHERE persona_id = ?', [$id]],
        ];

        $summary = [];
        foreach ($checks as $label => [$sql, $params]) {
            $count = $this->safeCount($sql, $params);
            if ($count > 0) {
                $summary[] = ['label' => $label, 'count' => $count];
            }
        }

        return $summary;
    }

    private function bindPayload(array $payload): array
    {
        $params = [];
        foreach (self::FIELDS as $field) {
            $params[':' . $field] = $payload[$field] ?? null;
        }
        return $params;
    }

    private function safeCount(string $sql, array $params): int
    {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            return (int) $st->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }
}
