<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class CatalogoOficioRepository
{
    private array $columnCache = [];

    public function __construct(private PDO $pdo)
    {
    }

    public function entidades(): array
    {
        $st = $this->pdo->query('SELECT ' . $this->entidadSelectSql() . ' FROM oficio_entidad ORDER BY nombre ASC');
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findEntidad(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT ' . $this->entidadSelectSql() . ' FROM oficio_entidad WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function searchEntidades(array $filters = []): array
    {
        $where = [];
        $params = [];
        $query = trim((string) ($filters['q'] ?? ''));
        $tipo = trim((string) ($filters['tipo'] ?? ''));
        $categoria = trim((string) ($filters['categoria'] ?? ''));

        if ($query !== '') {
            $phoneExpr = $this->columnExists('oficio_entidad', 'telefono_fijo') || $this->columnExists('oficio_entidad', 'telefono_movil')
                ? "CONCAT_WS(' ', COALESCE(telefono_fijo,''), COALESCE(telefono_movil,''), COALESCE(telefono,''))"
                : "COALESCE(telefono,'')";
            $categoriaExpr = $this->columnExists('oficio_entidad', 'categoria') ? "COALESCE(categoria,'')" : "''";
            $where[] = "(nombre LIKE ? OR COALESCE(siglas,'') LIKE ? OR COALESCE(direccion,'') LIKE ? OR COALESCE(correo,'') LIKE ? OR {$phoneExpr} LIKE ? OR {$categoriaExpr} LIKE ?)";
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        if ($tipo !== '') {
            $where[] = 'COALESCE(tipo, \'\') = ?';
            $params[] = $tipo;
        }

        if ($categoria !== '' && $this->columnExists('oficio_entidad', 'categoria')) {
            $where[] = 'COALESCE(categoria, \'\') = ?';
            $params[] = $categoria;
        }

        $sql = 'SELECT ' . $this->entidadSelectSql() . ' FROM oficio_entidad';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY nombre ASC';

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createEntidad(array $payload): int
    {
        $columns = ['tipo', 'nombre', 'siglas', 'direccion', 'telefono', 'correo', 'pagina_web'];
        $values = [
            $payload['tipo'],
            $payload['nombre'],
            $payload['siglas'],
            $payload['direccion'],
            $payload['telefono'],
            $payload['correo'],
            $payload['pagina_web'],
        ];
        if ($this->columnExists('oficio_entidad', 'categoria')) {
            $columns[] = 'categoria';
            $values[] = $payload['categoria'];
        }
        if ($this->columnExists('oficio_entidad', 'telefono_fijo')) {
            $columns[] = 'telefono_fijo';
            $values[] = $payload['telefono_fijo'];
        }
        if ($this->columnExists('oficio_entidad', 'telefono_movil')) {
            $columns[] = 'telefono_movil';
            $values[] = $payload['telefono_movil'];
        }
        $sql = 'INSERT INTO oficio_entidad (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $st = $this->pdo->prepare($sql);
        $st->execute($values);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateEntidad(int $id, array $payload): void
    {
        $sets = [
            'tipo = ?',
            'nombre = ?',
            'siglas = ?',
            'direccion = ?',
            'telefono = ?',
            'correo = ?',
            'pagina_web = ?',
        ];
        $values = [
            $payload['tipo'],
            $payload['nombre'],
            $payload['siglas'],
            $payload['direccion'],
            $payload['telefono'],
            $payload['correo'],
            $payload['pagina_web'],
        ];
        if ($this->columnExists('oficio_entidad', 'categoria')) {
            $sets[] = 'categoria = ?';
            $values[] = $payload['categoria'];
        }
        if ($this->columnExists('oficio_entidad', 'telefono_fijo')) {
            $sets[] = 'telefono_fijo = ?';
            $values[] = $payload['telefono_fijo'];
        }
        if ($this->columnExists('oficio_entidad', 'telefono_movil')) {
            $sets[] = 'telefono_movil = ?';
            $values[] = $payload['telefono_movil'];
        }
        $sql = 'UPDATE oficio_entidad SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $st = $this->pdo->prepare($sql);
        $values[] = $id;
        $st->execute($values);
    }

    public function tiposEntidad(): array
    {
        return ['PUBLICA', 'PRIVADA', 'PERSONA_NATURAL', 'OTRA'];
    }

    public function categoriasEntidad(): array
    {
        return [
            'COMISARIA',
            'FISCALIA',
            'MILITARES',
            'POLICIALES',
            'NECROPSIA',
            'MUNICIPALIDAD',
            'EMPRESA_PUBLICA',
            'EMPRESA_PRIVADA',
            'HOSPITAL',
            'CLINICA',
            'JUZGADO',
            'ASEGURADORA',
            'OTRA',
        ];
    }

    public function enlaceInteresCategorias(): array
    {
        return [
            'TRANSITO',
            'VEHICULAR',
            'SEGUROS',
            'PNP',
            'FISCALIA',
            'MUNICIPAL',
            'OTROS',
        ];
    }

    public function searchEnlacesInteres(array $filters = []): array
    {
        $where = [];
        $params = [];
        $query = trim((string) ($filters['q'] ?? ''));
        $categoria = trim((string) ($filters['categoria'] ?? ''));
        $soloActivos = (string) ($filters['solo_activos'] ?? '1');

        if ($query !== '') {
            $where[] = "(nombre LIKE ? OR COALESCE(categoria, '') LIKE ? OR COALESCE(descripcion, '') LIKE ? OR url LIKE ?)";
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like);
        }

        if ($categoria !== '') {
            $where[] = "COALESCE(categoria, '') = ?";
            $params[] = $categoria;
        }

        if ($soloActivos === '1') {
            $where[] = 'activo = 1';
        }

        $sql = 'SELECT id, categoria, nombre, url, descripcion, orden, activo FROM enlace_interes';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY activo DESC, orden ASC, nombre ASC';

        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findEnlaceInteres(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT id, categoria, nombre, url, descripcion, orden, activo FROM enlace_interes WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createEnlaceInteres(array $payload): int
    {
        $st = $this->pdo->prepare('INSERT INTO enlace_interes (categoria, nombre, url, descripcion, orden, activo) VALUES (?, ?, ?, ?, ?, ?)');
        $st->execute([
            $payload['categoria'],
            $payload['nombre'],
            $payload['url'],
            $payload['descripcion'],
            $payload['orden'],
            $payload['activo'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateEnlaceInteres(int $id, array $payload): void
    {
        $st = $this->pdo->prepare('UPDATE enlace_interes SET categoria = ?, nombre = ?, url = ?, descripcion = ?, orden = ?, activo = ? WHERE id = ?');
        $st->execute([
            $payload['categoria'],
            $payload['nombre'],
            $payload['url'],
            $payload['descripcion'],
            $payload['orden'],
            $payload['activo'],
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

    public function columnExists(string $table, string $column): bool
    {
        return in_array($column, $this->tableColumns($table), true);
    }

    private function tableColumns(string $table): array
    {
        if (isset($this->columnCache[$table])) {
            return $this->columnCache[$table];
        }
        $st = $this->pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$table]);
        return $this->columnCache[$table] = array_map(static fn (mixed $value): string => (string) $value, $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private function entidadSelectSql(): string
    {
        $hasCategoria = $this->columnExists('oficio_entidad', 'categoria');
        $hasTelefonoFijo = $this->columnExists('oficio_entidad', 'telefono_fijo');
        $hasTelefonoMovil = $this->columnExists('oficio_entidad', 'telefono_movil');

        return implode(', ', [
            'id',
            'tipo',
            'nombre',
            "COALESCE(siglas, '') AS siglas",
            "COALESCE(direccion, '') AS direccion",
            "COALESCE(telefono, '') AS telefono",
            "COALESCE(correo, '') AS correo",
            "COALESCE(pagina_web, '') AS pagina_web",
            ($hasCategoria ? "COALESCE(categoria, '')" : "''") . ' AS categoria',
            ($hasTelefonoFijo ? "COALESCE(telefono_fijo, '')" : "COALESCE(telefono, '')") . ' AS telefono_fijo',
            ($hasTelefonoMovil ? "COALESCE(telefono_movil, '')" : "''") . ' AS telefono_movil',
        ]);
    }
}
