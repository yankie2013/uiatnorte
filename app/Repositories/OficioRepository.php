<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class OficioRepository
{
    private array $columnCache = [];
    private array $tableCache = [];

    public function __construct(private PDO $pdo)
    {
    }

    public function tableExists(string $table): bool
    {
        if (isset($this->tableCache[$table])) {
            return $this->tableCache[$table];
        }
        $st = $this->pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$table]);
        return $this->tableCache[$table] = (bool) $st->fetchColumn();
    }

    public function columnExists(string $table, string $column): bool
    {
        return in_array($column, $this->tableColumns($table), true);
    }

    public function entidades(): array
    {
        return $this->pdo->query('SELECT id, nombre, COALESCE(siglas,\'\') AS siglas FROM oficio_entidad ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findEntidadByNameLike(string $term): ?array
    {
        $term = trim($term);
        if ($term === '') {
            return null;
        }

        $st = $this->pdo->prepare('SELECT id, nombre, COALESCE(siglas, \'\') AS siglas FROM oficio_entidad WHERE nombre LIKE ? ORDER BY id ASC LIMIT 1');
        $st->execute(['%' . $term . '%']);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function oficialAnos(): array
    {
        return $this->pdo->query('SELECT id, anio, nombre, COALESCE(vigente,0) AS vigente FROM oficio_oficial_ano ORDER BY anio DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function gradoCargo(): array
    {
        if (!$this->tableExists('grado_cargo')) {
            return [];
        }
        return $this->pdo->query("SELECT id, tipo, nombre, COALESCE(abreviatura,'') AS abrev FROM grado_cargo WHERE COALESCE(activo,1)=1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function subentidadesByEntidad(int $entidadId): array
    {
        if ($entidadId <= 0 || !$this->tableExists('oficio_subentidad')) {
            return [];
        }
        $sql = 'SELECT id, nombre, COALESCE(tipo,\'\') AS tipo FROM oficio_subentidad WHERE entidad_id = ? AND COALESCE(activo,1)=1 ORDER BY tipo, nombre';
        $st = $this->pdo->prepare($sql);
        $st->execute([$entidadId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function personasByEntidad(int $entidadId): array
    {
        if ($entidadId <= 0 || !$this->tableExists('oficio_persona_entidad')) {
            return [];
        }
        $sql = "SELECT id, CONCAT(COALESCE(nombres,''),' ',COALESCE(apellido_paterno,''),' ',COALESCE(apellido_materno,'')) AS nombre
                FROM oficio_persona_entidad
                WHERE entidad_id = ? AND COALESCE(activo,1)=1
                ORDER BY nombres, apellido_paterno, apellido_materno";
        $st = $this->pdo->prepare($sql);
        $st->execute([$entidadId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function asuntosByEntidadTipo(int $entidadId, string $tipo): array
    {
        if ($entidadId <= 0) {
            return [];
        }
        $sql = "SELECT id, nombre FROM oficio_asunto WHERE entidad_id = ? AND tipo = ? AND COALESCE(activo,1)=1 ORDER BY COALESCE(orden,999999), nombre";
        $st = $this->pdo->prepare($sql);
        $st->execute([$entidadId, $tipo]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function allAsuntos(?int $preferredId = null): array
    {
        $sql = "SELECT id, nombre
                FROM oficio_asunto
                WHERE COALESCE(activo,1)=1
                ORDER BY id ASC";
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $groups = [];
        foreach ($rows as $row) {
            $key = $this->asuntoCatalogKey((string) ($row['nombre'] ?? ''));
            if (!isset($groups[$key])) {
                $groups[$key] = $row;
                continue;
            }

            if ($preferredId > 0 && (int) $row['id'] === $preferredId) {
                $groups[$key] = $row;
            }
        }

        $items = [];
        foreach ($groups as $row) {
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nombre' => $this->asuntoCatalogLabel((string) ($row['nombre'] ?? '')),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
        });

        return $items;
    }

    public function asuntoInfo(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT id, entidad_id, tipo, nombre, COALESCE(detalle,\'\') AS detalle FROM oficio_asunto WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findAsuntoByEntidadAndNameLike(int $entidadId, string $tipo, string $term): ?array
    {
        if ($entidadId <= 0) {
            return null;
        }

        $term = trim($term);
        if ($term === '') {
            return null;
        }

        $sql = "SELECT id, entidad_id, tipo, nombre, COALESCE(detalle,'') AS detalle
                FROM oficio_asunto
                WHERE entidad_id = ? AND tipo = ? AND COALESCE(activo,1)=1
                  AND (nombre LIKE ? OR COALESCE(detalle,'') LIKE ?)
                ORDER BY COALESCE(orden,999999), id
                LIMIT 1";
        $like = '%' . $term . '%';
        $st = $this->pdo->prepare($sql);
        $st->execute([$entidadId, $tipo, $like, $like]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findAsuntoByNameLike(string $tipo, string $term): ?array
    {
        $term = trim($term);
        if ($term === '') {
            return null;
        }

        $sql = "SELECT id, entidad_id, tipo, nombre, COALESCE(detalle,'') AS detalle
                FROM oficio_asunto
                WHERE tipo = ? AND COALESCE(activo,1)=1
                  AND (nombre LIKE ? OR COALESCE(detalle,'') LIKE ?)
                ORDER BY COALESCE(orden,999999), id
                LIMIT 1";
        $like = '%' . $term . '%';
        $st = $this->pdo->prepare($sql);
        $st->execute([$tipo, $like, $like]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function asuntoVariantes(int $id): array
    {
        $base = $this->asuntoInfo($id);
        if ($base === null) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT id, COALESCE(detalle,\'\') AS detalle FROM oficio_asunto WHERE entidad_id = ? AND tipo = ? AND nombre = ? AND COALESCE(activo,1)=1 ORDER BY COALESCE(orden,999999), id');
        $st->execute([(int) $base['entidad_id'], (string) $base['tipo'], (string) $base['nombre']]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function accidentes(): array
    {
        $preferred = ['registro_sidpol', 'numero', 'codigo', 'expediente', 'parte', 'n_parte', 'numero_parte', 'folio', 'caso'];
        $info = ['fecha_accidente', 'fecha', 'lugar', 'distrito', 'via', 'placa'];
        $available = array_flip($this->tableColumns('accidentes'));
        $cols = ['id'];
        foreach ($preferred as $column) {
            if (isset($available[$column])) {
                $cols[] = $column;
            }
        }
        foreach ($info as $column) {
            if (isset($available[$column])) {
                $cols[] = $column;
            }
        }

        $select = implode(',', array_map(static fn (string $column): string => $column === 'id' ? 'id' : "`{$column}`", $cols));
        try {
            $rows = $this->pdo->query("SELECT {$select} FROM accidentes ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $rows = $this->pdo->query('SELECT id FROM accidentes ORDER BY id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);
        }

        $items = [];
        foreach ($rows as $row) {
            $parts = [];
            foreach ($preferred as $column) {
                if (!empty($row[$column])) {
                    $parts[] = $column === 'registro_sidpol' ? ('SIDPOL ' . $row[$column]) : $row[$column];
                    break;
                }
            }
            foreach ($info as $column) {
                if (!empty($row[$column])) {
                    $parts[] = $row[$column];
                }
            }
            $label = 'ACCID-' . $row['id'];
            if ($parts !== []) {
                $label .= ' - ' . implode(' · ', $parts);
            }
            $items[] = ['id' => (int) $row['id'], 'label' => $label];
        }

        return $items;
    }

    public function accidenteIdBySidpol(string $sidpol): ?int
    {
        if ($sidpol === '' || !$this->columnExists('accidentes', 'registro_sidpol')) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT id FROM accidentes WHERE registro_sidpol = ? LIMIT 1');
        $st->execute([$sidpol]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function nextNumero(int $anio): int
    {
        $st = $this->pdo->prepare('SELECT COALESCE(MAX(numero),0)+1 FROM oficios WHERE anio = ?');
        $st->execute([$anio]);
        return max(1, (int) $st->fetchColumn());
    }

    public function numeroExists(int $anio, int $numero, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM oficios WHERE anio = ? AND numero = ?';
        $params = [$anio, $numero];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn() > 0;
    }

    public function vehiculosByAccidente(int $accidenteId): array
    {
        if ($accidenteId <= 0) {
            return [];
        }
        $sql = "SELECT iv.id,
                       CONCAT(COALESCE(iv.orden_participacion,''),
                              CASE WHEN COALESCE(v.placa,'') = '' THEN '' ELSE CONCAT(' - ', v.placa) END) AS nombre
                FROM involucrados_vehiculos iv
                LEFT JOIN vehiculos v ON v.id = iv.vehiculo_id
                WHERE iv.accidente_id = ?
                ORDER BY iv.orden_participacion, iv.id";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function vehiculoBelongsAccidente(int $accidenteId, int $involucradoVehiculoId): bool
    {
        if ($accidenteId <= 0 || $involucradoVehiculoId <= 0) {
            return false;
        }

        $st = $this->pdo->prepare('SELECT COUNT(*) FROM involucrados_vehiculos WHERE id = ? AND accidente_id = ?');
        $st->execute([$involucradoVehiculoId, $accidenteId]);
        return (int) $st->fetchColumn() > 0;
    }

    public function latestPeritajePreset(): ?array
    {
        $sql = "SELECT o.entidad_id_destino,
                       o.subentidad_destino_id,
                       o.persona_destino_id,
                       o.grado_cargo_id,
                       o.asunto_id,
                       COALESCE(o.motivo,'') AS motivo
                FROM oficios o
                LEFT JOIN oficio_asunto oa ON oa.id = o.asunto_id
                WHERE LOWER(COALESCE(oa.nombre,'')) LIKE '%peritaje%'
                   OR LOWER(COALESCE(oa.detalle,'')) LIKE '%peritaje%'
                ORDER BY o.id DESC
                LIMIT 1";
        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function latestNecropsiaPreset(): ?array
    {
        $sql = "SELECT o.entidad_id_destino,
                       o.subentidad_destino_id,
                       o.persona_destino_id,
                       o.grado_cargo_id,
                       o.asunto_id,
                       COALESCE(o.motivo,'') AS motivo
                FROM oficios o
                LEFT JOIN oficio_asunto oa ON oa.id = o.asunto_id
                WHERE LOWER(COALESCE(oa.nombre,'')) LIKE '%necrops%'
                   OR LOWER(COALESCE(oa.detalle,'')) LIKE '%necrops%'
                   OR LOWER(COALESCE(oa.nombre,'')) LIKE '%autops%'
                   OR LOWER(COALESCE(oa.detalle,'')) LIKE '%autops%'
                ORDER BY o.id DESC
                LIMIT 1";
        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function fallecidosByAccidente(int $accidenteId): array
    {
        if ($accidenteId <= 0) {
            return [];
        }
        $candidates = ['estado_lesion', 'lesion', 'condicion_lesion', 'condicion', 'calidad_lesion'];
        $filters = [];
        foreach ($candidates as $column) {
            if ($this->columnExists('involucrados_personas', $column)) {
                $filters[] = "ip.`{$column}` = 'FALLECIDO'";
                $filters[] = "ip.`{$column}` = 'Fallecido'";
            }
        }
        if ($filters === [] && $this->columnExists('involucrados_personas', 'rol')) {
            $filters[] = "ip.`rol` = 'FALLECIDO'";
            $filters[] = "ip.`rol` = 'Fallecido'";
        }
        if ($filters === []) {
            return [];
        }
        $sql = "SELECT ip.id,
                       TRIM(CONCAT(COALESCE(pe.apellido_paterno,''), ' ', COALESCE(pe.apellido_materno,''), ' ', COALESCE(pe.nombres,''))) AS nombre
                FROM involucrados_personas ip
                LEFT JOIN personas pe ON pe.id = ip.persona_id
                WHERE ip.accidente_id = ? AND (" . implode(' OR ', $filters) . ")
                ORDER BY pe.apellido_paterno, pe.apellido_materno, pe.nombres";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fallecidoBelongsAccidente(int $accidenteId, int $involucradoPersonaId): bool
    {
        if ($accidenteId <= 0 || $involucradoPersonaId <= 0) {
            return false;
        }

        $candidates = ['estado_lesion', 'lesion', 'condicion_lesion', 'condicion', 'calidad_lesion'];
        $filters = [];
        foreach ($candidates as $column) {
            if ($this->columnExists('involucrados_personas', $column)) {
                $filters[] = "ip.`{$column}` = 'FALLECIDO'";
                $filters[] = "ip.`{$column}` = 'Fallecido'";
            }
        }
        if ($filters === [] && $this->columnExists('involucrados_personas', 'rol')) {
            $filters[] = "ip.`rol` = 'FALLECIDO'";
            $filters[] = "ip.`rol` = 'Fallecido'";
        }
        if ($filters === []) {
            return false;
        }

        $sql = "SELECT COUNT(*)
                FROM involucrados_personas ip
                WHERE ip.id = ? AND ip.accidente_id = ? AND (" . implode(' OR ', $filters) . ')';
        $st = $this->pdo->prepare($sql);
        $st->execute([$involucradoPersonaId, $accidenteId]);
        return (int) $st->fetchColumn() > 0;
    }

    public function search(array $filters): array
    {
        $select = [
            'o.id', 'o.numero', 'o.anio', 'o.fecha_emision', 'o.estado', 'o.accidente_id',
            'COALESCE(e.siglas, e.nombre) AS entidad',
            'a.registro_sidpol',
            'a.id AS accid',
            'COALESCE(s.detalle,\'\') AS detalle',
            'COALESCE(s.nombre,\'\') AS asunto_nombre',
            'COALESCE(s.tipo,\'\') AS asunto_tipo'
        ];
        $joins = [
            'LEFT JOIN oficio_entidad e ON e.id = o.entidad_id_destino',
            'LEFT JOIN accidentes a ON a.id = o.accidente_id',
            'LEFT JOIN oficio_asunto s ON s.id = o.asunto_id'
        ];

        if ($this->columnExists('oficios', 'involucrado_vehiculo_id')) {
            $joins[] = 'LEFT JOIN involucrados_vehiculos iv ON iv.id = o.involucrado_vehiculo_id';
            $joins[] = 'LEFT JOIN vehiculos v ON v.id = iv.vehiculo_id';
            $select[] = 'COALESCE(v.placa,\'\') AS veh_placa';
            $select[] = 'COALESCE(iv.orden_participacion,\'\') AS veh_ut';
        } else {
            $select[] = "'' AS veh_placa";
            $select[] = "'' AS veh_ut";
        }

        if ($this->columnExists('oficios', 'involucrado_persona_id')) {
            $select[] = 'o.involucrado_persona_id AS inv_per_id';
        } else {
            $select[] = 'NULL AS inv_per_id';
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM oficios o ' . implode(' ', $joins) . ' WHERE 1=1';
        $params = [];

        if (!empty($filters['anio'])) {
            $sql .= ' AND o.anio = ?';
            $params[] = (int) $filters['anio'];
        }
        if (!empty($filters['entidad_id'])) {
            $sql .= ' AND o.entidad_id_destino = ?';
            $params[] = (int) $filters['entidad_id'];
        }
        if (!empty($filters['accidente_id'])) {
            $sql .= ' AND o.accidente_id = ?';
            $params[] = (int) $filters['accidente_id'];
        }
        if (!empty($filters['sidpol'])) {
            $sql .= ' AND a.registro_sidpol = ?';
            $params[] = $filters['sidpol'];
        }
        if (!empty($filters['estado'])) {
            $sql .= ' AND o.estado = ?';
            $params[] = $filters['estado'];
        }
        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $sql .= ' AND (o.numero LIKE ? OR COALESCE(o.referencia_texto,\'\') LIKE ? OR COALESCE(a.registro_sidpol,\'\') LIKE ? OR COALESCE(s.detalle,\'\') LIKE ? OR COALESCE(s.nombre,\'\') LIKE ? OR COALESCE(e.nombre,\'\') LIKE ? OR COALESCE(e.siglas,\'\') LIKE ? OR COALESCE(v.placa,\'\') LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
        }

        $sql .= ' ORDER BY o.anio DESC, o.numero DESC LIMIT 300';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateEstado(int $id, string $estado): void
    {
        $st = $this->pdo->prepare('UPDATE oficios SET estado = ? WHERE id = ? LIMIT 1');
        $st->execute([$estado, $id]);
    }

    public function create(array $payload): int
    {
        $columns = [
            'accidente_id', 'involucrado_vehiculo_id', 'numero', 'anio', 'fecha_emision',
            'entidad_id_destino', 'subentidad_destino_id', 'persona_destino_id', 'grado_cargo_id',
            'asunto_id', 'motivo', 'referencia_texto', 'oficial_ano_id', 'estado'
        ];
        $values = [
            $payload['accidente_id'], $payload['involucrado_vehiculo_id'], $payload['numero'], $payload['anio'], $payload['fecha_emision'],
            $payload['entidad_id_destino'], $payload['subentidad_destino_id'], $payload['persona_destino_id'], $payload['grado_cargo_id'],
            $payload['asunto_id'], $payload['motivo'], $payload['referencia_texto'], $payload['oficial_ano_id'], $payload['estado']
        ];
        if ($this->columnExists('oficios', 'involucrado_persona_id')) {
            $columns[] = 'involucrado_persona_id';
            $values[] = $payload['involucrado_persona_id'];
        }
        if ($this->columnExists('oficios', 'persona_destino_manual')) {
            $columns[] = 'persona_destino_manual';
            $values[] = $payload['persona_destino_manual'];
        }
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO oficios (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')';
        $st = $this->pdo->prepare($sql);
        $st->execute($values);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sets = [
            'accidente_id = ?',
            'involucrado_vehiculo_id = ?',
            'numero = ?',
            'anio = ?',
            'fecha_emision = ?',
            'entidad_id_destino = ?',
            'subentidad_destino_id = ?',
            'persona_destino_id = ?',
            'grado_cargo_id = ?',
            'asunto_id = ?',
            'motivo = ?',
            'referencia_texto = ?',
            'oficial_ano_id = ?',
            'estado = ?'
        ];
        $values = [
            $payload['accidente_id'], $payload['involucrado_vehiculo_id'], $payload['numero'], $payload['anio'], $payload['fecha_emision'],
            $payload['entidad_id_destino'], $payload['subentidad_destino_id'], $payload['persona_destino_id'], $payload['grado_cargo_id'],
            $payload['asunto_id'], $payload['motivo'], $payload['referencia_texto'], $payload['oficial_ano_id'], $payload['estado']
        ];
        if ($this->columnExists('oficios', 'involucrado_persona_id')) {
            $sets[] = 'involucrado_persona_id = ?';
            $values[] = $payload['involucrado_persona_id'];
        }
        if ($this->columnExists('oficios', 'persona_destino_manual')) {
            $sets[] = 'persona_destino_manual = ?';
            $values[] = $payload['persona_destino_manual'];
        }
        $values[] = $id;
        $sql = 'UPDATE oficios SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($values);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM oficios WHERE id = ? LIMIT 1');
        $st->execute([$id]);
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM oficios WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function detail(int $id): ?array
    {
        $select = [
            'o.*',
            'e.nombre AS entidad',
            'COALESCE(e.siglas,\'\') AS entidad_siglas',
            'se.nombre AS subentidad',
            'p.nombres AS per_nombres',
            'p.apellido_paterno AS per_ap',
            'p.apellido_materno AS per_am',
            'pf.nombres AS fall_nombres',
            'pf.apellido_paterno AS fall_ap',
            'pf.apellido_materno AS fall_am',
            'a.registro_sidpol',
            'a.lugar',
            'a.fecha_accidente',
            'COALESCE(iv.orden_participacion, \'\') AS veh_ut',
            'COALESCE(v.placa, \'\') AS veh_placa',
            'ao.nombre AS nombre_anio',
            'ao.anio AS anio_nom',
            'oa.nombre AS asunto_nombre',
            'oa.tipo AS asunto_tipo'
        ];
        if ($this->columnExists('oficios', 'persona_destino_manual')) {
            $select[] = 'COALESCE(o.persona_destino_manual, \'\') AS persona_destino_manual';
        } else {
            $select[] = '\'\' AS persona_destino_manual';
        }
        $joins = [
            'LEFT JOIN oficio_entidad e ON e.id = o.entidad_id_destino',
            'LEFT JOIN oficio_subentidad se ON se.id = o.subentidad_destino_id',
            'LEFT JOIN oficio_persona_entidad p ON p.id = o.persona_destino_id',
            'LEFT JOIN involucrados_personas ipf ON ipf.id = o.involucrado_persona_id',
            'LEFT JOIN personas pf ON pf.id = ipf.persona_id',
            'LEFT JOIN accidentes a ON a.id = o.accidente_id',
            'LEFT JOIN involucrados_vehiculos iv ON iv.id = o.involucrado_vehiculo_id',
            'LEFT JOIN vehiculos v ON v.id = iv.vehiculo_id',
            'LEFT JOIN oficio_oficial_ano ao ON ao.id = o.oficial_ano_id',
            'LEFT JOIN oficio_asunto oa ON oa.id = o.asunto_id'
        ];
        if ($this->tableExists('grado_cargo') && $this->columnExists('oficios', 'grado_cargo_id')) {
            $select[] = 'gc.nombre AS grado_cargo_nombre';
            $joins[] = 'LEFT JOIN grado_cargo gc ON gc.id = o.grado_cargo_id';
        } else {
            $select[] = 'NULL AS grado_cargo_nombre';
        }
        $sql = 'SELECT ' . implode(', ', $select) . ' FROM oficios o ' . implode(' ', $joins) . ' WHERE o.id = ? LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function tableColumns(string $table): array
    {
        if (isset($this->columnCache[$table])) {
            return $this->columnCache[$table];
        }
        $st = $this->pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$table]);
        return $this->columnCache[$table] = $st->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
    }

    private function asuntoCatalogKey(string $name): string
    {
        $normalized = $this->normalizeCatalogText($name);

        if (str_contains($normalized, 'camara') && str_contains($normalized, 'video')) {
            return 'camaras-video-vigilancia';
        }

        if (str_contains($normalized, 'remitir') && str_contains($normalized, 'diligenc')) {
            return 'remitir-diligencias';
        }

        return $normalized;
    }

    private function asuntoCatalogLabel(string $name): string
    {
        $normalized = $this->normalizeCatalogText($name);

        if (str_contains($normalized, 'camara') && str_contains($normalized, 'video')) {
            return 'Camaras de video vigilancia';
        }

        if (str_contains($normalized, 'remitir') && str_contains($normalized, 'diligenc')) {
            return 'Remitir diligencias';
        }

        return trim($name);
    }

    private function normalizeCatalogText(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n',
        ]);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
