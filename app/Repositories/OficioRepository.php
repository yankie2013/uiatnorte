<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\OficioContenido;
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

    private function activeTable(string $table): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $table)) {
            throw new \InvalidArgumentException('Tabla no válida.');
        }
        $active = $table . '_activos';
        return '`' . ($this->tableExists($active) ? $active : $table) . '`';
    }

    public function columnExists(string $table, string $column): bool
    {
        return in_array($column, $this->tableColumns($table), true);
    }

    public function gestionContext(): array
    {
        $st = $this->pdo->prepare("SELECT u.id, u.nombre, u.grado FROM usuarios u JOIN usuarios actor ON actor.id=? WHERE u.activo=1 AND u.rol='jefe_emi' AND TRIM(COALESCE(u.unidad,''))=TRIM(COALESCE(actor.unidad,'')) ORDER BY u.nombre");
        $st->execute([(int) (\App\Support\Auth::user()['id'] ?? 0)]);
        return [
            'comisarias' => $this->pdo->query('SELECT id,nombre FROM comisarias ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC),
            'encargados' => $st->fetchAll(PDO::FETCH_ASSOC),
            'expedientes' => $this->pdo->query("SELECT a.id,a.comisaria_id,a.responsable_id,a.fecha_accidente,a.lugar,
                COALESCE((SELECT GROUP_CONCAT(DISTINCT m.nombre ORDER BY m.nombre SEPARATOR ', ')
                    FROM accidente_modalidad_activos am JOIN modalidad_accidente m ON m.id=am.modalidad_id
                    WHERE am.accidente_id=a.id),'Sin tipo registrado') AS tipo_accidente,
                CONCAT('Fecha: ',COALESCE(DATE_FORMAT(a.fecha_accidente,'%d/%m/%Y %H:%i'),'Sin fecha'),
                    ' · Lugar: ',COALESCE(a.lugar,'Sin lugar'),
                    ' · Tipo: ',COALESCE((SELECT GROUP_CONCAT(DISTINCT m.nombre ORDER BY m.nombre SEPARATOR ', ')
                        FROM accidente_modalidad_activos am JOIN modalidad_accidente m ON m.id=am.modalidad_id
                        WHERE am.accidente_id=a.id),'Sin tipo registrado')) AS label
                FROM accidentes_activos a ORDER BY a.id DESC")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function validateGestion(int $comisariaId, ?int $encargadoId, int $accidenteId): void
    {
        $ctx = $this->gestionContext();
        if ($comisariaId !== 0 && !in_array($comisariaId, array_map('intval', array_column($ctx['comisarias'], 'id')), true)) {
            throw new \InvalidArgumentException('Selecciona una comisaría válida.');
        }
        if ($encargadoId !== null && !in_array($encargadoId, array_map('intval', array_column($ctx['encargados'], 'id')), true)) {
            throw new \InvalidArgumentException('El encargado debe ser JEFE EMI activo de tu misma unidad.');
        }
        if ($accidenteId > 0) {
            foreach ($ctx['expedientes'] as $case) {
                if ((int)$case['id'] === $accidenteId && $encargadoId !== null && $comisariaId > 0 && (int)$case['responsable_id'] === $encargadoId && (int)$case['comisaria_id'] === $comisariaId) return;
            }
            throw new \InvalidArgumentException('El expediente debe coincidir con la comisaría y el encargado seleccionados.');
        }
    }

    public function entidades(): array
    {
        return $this->pdo->query("SELECT id, nombre, COALESCE(siglas,'') AS siglas, COALESCE(categoria,'') AS categoria FROM oficio_entidad ORDER BY categoria, nombre")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function entidadCategorias(): array
    {
        if (!$this->tableExists('oficio_categoria_entidad')) {
            return [];
        }
        return $this->pdo->query("SELECT codigo, nombre FROM oficio_categoria_entidad WHERE COALESCE(activo,1)=1 ORDER BY nombre, codigo")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function entidadExists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $st = $this->pdo->prepare('SELECT COUNT(*) FROM oficio_entidad WHERE id = ?');
        $st->execute([$id]);
        return (int) $st->fetchColumn() > 0;
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

    public function tiposDiligencia(): array
    {
        if (!$this->tableExists('tipo_diligencia')) {
            return [];
        }

        return $this->pdo->query("SELECT id, nombre, COALESCE(descripcion,'') AS descripcion FROM tipo_diligencia ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
        $sql = "SELECT id, tipo, nombre, COALESCE(detalle,'') AS detalle
                FROM oficio_asunto
                WHERE COALESCE(activo,1)=1
                ORDER BY id ASC";
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $groups = [];
        $keysById = [];
        foreach ($rows as $row) {
            $key = (string) $row['tipo'] . ':' . $this->asuntoCatalogKey((string) ($row['nombre'] ?? ''));
            $keysById[(int) $row['id']] = $key;
            if (!isset($groups[$key])) {
                $groups[$key] = $row;
                $groups[$key]['busqueda'] = (string) $row['detalle'];
                $groups[$key]['ids'] = [(int) $row['id']];
                $groups[$key]['textos'] = [];
                continue;
            }

            $groups[$key]['busqueda'] .= ' ' . (string) $row['detalle'];
            $groups[$key]['ids'][] = (int) $row['id'];

            if ($preferredId > 0 && (int) $row['id'] === $preferredId) {
                $row['busqueda'] = $groups[$key]['busqueda'];
                $row['ids'] = $groups[$key]['ids'];
                $row['textos'] = $groups[$key]['textos'];
                $groups[$key] = $row;
            }
        }

        foreach ($rows as $row) {
            $key = $keysById[(int) $row['id']];
            $texto = trim((string) ($row['detalle'] ?? ''));
            if ($texto !== '') $groups[$key]['textos'][mb_strtolower($texto, 'UTF-8')] = $texto;
        }
        $oficiosTable = $this->activeTable('oficios');
        foreach ($this->pdo->query("SELECT asunto_id, motivo FROM {$oficiosTable} WHERE asunto_id IS NOT NULL AND TRIM(COALESCE(motivo,'')) <> '' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $keysById[(int) $row['asunto_id']] ?? null;
            if ($key === null) continue;
            $texto = OficioContenido::asuntoBase((string) $row['motivo']);
            if ($texto !== '') $groups[$key]['textos'][mb_strtolower($texto, 'UTF-8')] = $texto;
        }

        $items = [];
        foreach ($groups as $row) {
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nombre' => $this->asuntoCatalogLabel((string) ($row['nombre'] ?? '')),
                'tipo' => (string) $row['tipo'],
                'detalle' => (string) $row['detalle'],
                'busqueda' => trim((string) $row['busqueda']),
                'ids' => $row['ids'],
                'textos' => array_values($row['textos']),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
        });

        return $items;
    }

    public function findAsuntoByExactName(string $tipo, string $nombre): ?array
    {
        $key = $this->asuntoCatalogKey($nombre);
        $st = $this->pdo->prepare("SELECT id, entidad_id, tipo, nombre, COALESCE(detalle,'') AS detalle FROM oficio_asunto WHERE tipo = ? AND COALESCE(activo,1)=1 ORDER BY id");
        $st->execute([$tipo]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($this->asuntoCatalogKey((string) $row['nombre']) === $key) {
                return $row;
            }
        }
        return null;
    }

    public function sameAsuntoCatalogName(string $first, string $second): bool
    {
        return $this->asuntoCatalogKey($first) === $this->asuntoCatalogKey($second);
    }

    /** Called inside the oficio transaction so failed saves do not leave catalog entries. */
    public function resolveRecipientValues(array $payload): array
    {
        foreach ([['entidad_nombre_nueva','oficio_entidad','entidad_id_destino'], ['grado_cargo_nombre_nuevo','grado_cargo','grado_cargo_id']] as [$key,$table,$field]) {
            $name = trim((string)($payload[$key] ?? ''));
            if ($name === '') continue;
            $sql = "SELECT id FROM `$table` WHERE TRIM(nombre)=?";
            if ($table === 'grado_cargo') $sql .= ' AND COALESCE(activo,1)=1';
            $st = $this->pdo->prepare($sql . ' ORDER BY id LIMIT 1');
            $st->execute([$name]);
            $id = $st->fetchColumn();
            if ($id === false) {
                $st = $this->pdo->prepare($table === 'oficio_entidad'
                    ? "INSERT INTO oficio_entidad (nombre,tipo) VALUES (?,'OTRA')"
                    : "INSERT INTO grado_cargo (nombre,tipo,activo) VALUES (?,'CARGO',1)");
                $st->execute([$name]);
                $id = $this->pdo->lastInsertId();
            }
            $payload[$field] = (int)$id;
        }
        return $payload;
    }

    public function saveWithTemplate(array $payload, string $newTemplate = '', ?int $id = null): int
    {
        $this->pdo->beginTransaction();
        try {
            $payload = $this->resolveRecipientValues($payload);
            if ($newTemplate !== '') {
                $existing = $this->findAsuntoByExactName((string) $payload['tipo'], $newTemplate);
                if ($existing !== null) {
                    $payload['asunto_id'] = (int) $existing['id'];
                } else {
                    $st = $this->pdo->prepare('INSERT INTO oficio_asunto (entidad_id, tipo, nombre, detalle, orden, activo) VALUES (?, ?, ?, ?, ?, 1)');
                    $st->execute([(int) $payload['entidad_id_destino'], (string) $payload['tipo'], $newTemplate, '', 0]);
                    $payload['asunto_id'] = (int) $this->pdo->lastInsertId();
                }
            }
            if ($id === null) {
                $id = $this->create($payload);
            } else {
                $this->update($id, $payload);
            }
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
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
        $key = $this->asuntoCatalogKey((string) ($base['nombre'] ?? ''));
        $st = $this->pdo->prepare('SELECT id, nombre, COALESCE(detalle,\'\') AS detalle FROM oficio_asunto WHERE tipo = ? AND COALESCE(activo,1)=1 ORDER BY COALESCE(orden,999999), id');
        $st->execute([(string) $base['tipo']]);

        $items = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if ($this->asuntoCatalogKey((string) ($row['nombre'] ?? '')) === $key) {
                $items[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'detalle' => (string) ($row['detalle'] ?? ''),
                ];
            }
        }

        return $items;
    }

    public function latestPresetForAsunto(int $asuntoId): ?array
    {
        $base = $this->asuntoInfo($asuntoId);
        if ($base === null) {
            return null;
        }

        $key = $this->presetFamilyKey((string) ($base['nombre'] ?? ''));
        $diligenciasSelect = $this->columnExists('oficios', 'diligencias_solicitadas')
            ? "COALESCE(o.diligencias_solicitadas,'') AS diligencias_solicitadas"
            : "'' AS diligencias_solicitadas";
        $personaManualSelect = $this->columnExists('oficios', 'persona_destino_manual')
            ? "COALESCE(o.persona_destino_manual,'') AS persona_destino_manual"
            : "'' AS persona_destino_manual";

        $oficiosTable = $this->activeTable('oficios');
        $sql = "SELECT o.entidad_id_destino,
                       o.subentidad_destino_id,
                       o.persona_destino_id,
                       {$personaManualSelect},
                       o.grado_cargo_id,
                       o.asunto_id,
                       o.involucrado_vehiculo_id,
                       o.involucrado_persona_id,
                       COALESCE(o.motivo,'') AS motivo,
                       COALESCE(o.referencia_texto,'') AS referencia_texto,
                       {$diligenciasSelect},
                       oa.nombre AS asunto_nombre,
                       oa.tipo AS asunto_tipo,
                       COALESCE(oa.detalle,'') AS asunto_detalle
                FROM {$oficiosTable} o
                LEFT JOIN oficio_asunto oa ON oa.id = o.asunto_id
                WHERE o.asunto_id IS NOT NULL
                ORDER BY o.id DESC
";
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            if ($this->presetFamilyKey((string) ($row['asunto_nombre'] ?? '')) === $key && (string)$row['asunto_tipo'] === (string)$base['tipo']) {
                return $row;
            }
        }

        return null;
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

        $accidentTable = $this->activeTable('accidentes');
        $select = implode(',', array_map(static fn (string $column): string => $column === 'id' ? 'id' : "`{$column}`", $cols));
        try {
            $rows = $this->pdo->query("SELECT {$select} FROM {$accidentTable} ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $rows = $this->pdo->query("SELECT id FROM {$accidentTable} ORDER BY id DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
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
        $accidentTable = $this->activeTable('accidentes');
        $st = $this->pdo->prepare("SELECT id FROM {$accidentTable} WHERE registro_sidpol = ? LIMIT 1");
        $st->execute([$sidpol]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function nextNumero(int $anio): int
    {
        // El correlativo considera todos los oficios existentes, aunque no figuren en la vista activa.
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

    public function accidenteExists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $accidentTable = $this->activeTable('accidentes');
        $st = $this->pdo->prepare("SELECT COUNT(*) FROM {$accidentTable} WHERE id = ?");
        $st->execute([$id]);
        return (int) $st->fetchColumn() > 0;
    }

    public function vehiculosByAccidente(int $accidenteId): array
    {
        if ($accidenteId <= 0) {
            return [];
        }
        $vehicleTable = $this->activeTable('involucrados_vehiculos');
        $sql = "SELECT iv.id,
                       CONCAT(COALESCE(iv.orden_participacion,''),
                              CASE WHEN COALESCE(v.placa,'') = '' THEN '' ELSE CONCAT(' - ', v.placa) END) AS nombre
                FROM {$vehicleTable} iv
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

        $vehicleTable = $this->activeTable('involucrados_vehiculos');
        $st = $this->pdo->prepare("SELECT COUNT(*) FROM {$vehicleTable} WHERE id = ? AND accidente_id = ?");
        $st->execute([$involucradoVehiculoId, $accidenteId]);
        return (int) $st->fetchColumn() > 0;
    }

    public function latestPeritajePreset(): ?array
    {
        $oficiosTable = $this->activeTable('oficios');
        $sql = "SELECT o.entidad_id_destino,
                       o.subentidad_destino_id,
                       o.persona_destino_id,
                       o.grado_cargo_id,
                       o.asunto_id,
                       COALESCE(o.motivo,'') AS motivo
                FROM {$oficiosTable} o
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
        $oficiosTable = $this->activeTable('oficios');
        $sql = "SELECT o.entidad_id_destino,
                       o.subentidad_destino_id,
                       o.persona_destino_id,
                       o.grado_cargo_id,
                       o.asunto_id,
                       COALESCE(o.motivo,'') AS motivo
                FROM {$oficiosTable} o
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
        $personasTable = $this->activeTable('involucrados_personas');
        $sql = "SELECT ip.id,
                       TRIM(CONCAT(COALESCE(pe.nombres,''), ' ', COALESCE(pe.apellido_paterno,''), ' ', COALESCE(pe.apellido_materno,''))) AS nombre
                FROM {$personasTable} ip
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

        $personasTable = $this->activeTable('involucrados_personas');
        $sql = "SELECT COUNT(*)
                FROM {$personasTable} ip
                WHERE ip.id = ? AND ip.accidente_id = ? AND (" . implode(' OR ', $filters) . ')';
        $st = $this->pdo->prepare($sql);
        $st->execute([$involucradoPersonaId, $accidenteId]);
        return (int) $st->fetchColumn() > 0;
    }

    public function categoriasCompartidas(): array
    {
        $queries = [];
        $oficiosTable = $this->activeTable('oficios');
        $documentosTable = $this->activeTable('documentos_recibidos');
        if ($this->columnExists('oficios', 'categoria')) {
            $queries[] = "SELECT categoria COLLATE utf8mb4_unicode_ci AS categoria FROM {$oficiosTable} WHERE categoria IS NOT NULL AND categoria <> ''";
        }
        if ($this->columnExists('documentos_recibidos', 'categoria')) {
            $queries[] = "SELECT categoria COLLATE utf8mb4_unicode_ci AS categoria FROM {$documentosTable} WHERE categoria IS NOT NULL AND categoria <> ''";
        }
        if ($queries === []) {
            return [];
        }

        return $this->pdo->query('SELECT DISTINCT categoria FROM (' . implode(' UNION ALL ', $queries) . ') categorias_compartidas ORDER BY categoria')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function personasInformeMedicoByAccidente(int $accidenteId): array
    {
        if ($accidenteId <= 0 || !$this->columnExists('involucrados_personas', 'lesion')) {
            return [];
        }

        $personasTable = $this->activeTable('involucrados_personas');
        $sql = "SELECT ip.id,
                       CONCAT(
                           TRIM(CONCAT(COALESCE(pe.nombres,''), ' ', COALESCE(pe.apellido_paterno,''), ' ', COALESCE(pe.apellido_materno,''))),
                           CASE WHEN COALESCE(ip.lesion,'') <> '' THEN CONCAT(' - ', ip.lesion) ELSE '' END
                       ) AS nombre
                FROM {$personasTable} ip
                LEFT JOIN personas pe ON pe.id = ip.persona_id
                WHERE ip.accidente_id = ?
                  AND (LOWER(COALESCE(ip.lesion,'')) LIKE '%herid%'
                       OR LOWER(COALESCE(ip.lesion,'')) LIKE '%lesion%'
                       OR LOWER(COALESCE(ip.lesion,'')) LIKE '%fallec%')
                ORDER BY pe.apellido_paterno, pe.apellido_materno, pe.nombres";
        $st = $this->pdo->prepare($sql);
        $st->execute([$accidenteId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function personaInformeMedicoBelongsAccidente(int $accidenteId, int $involucradoPersonaId): bool
    {
        if ($accidenteId <= 0 || $involucradoPersonaId <= 0 || !$this->columnExists('involucrados_personas', 'lesion')) {
            return false;
        }

        $personasTable = $this->activeTable('involucrados_personas');
        $sql = "SELECT COUNT(*)
                FROM {$personasTable} ip
                WHERE ip.id = ? AND ip.accidente_id = ?
                  AND (LOWER(COALESCE(ip.lesion,'')) LIKE '%herid%'
                       OR LOWER(COALESCE(ip.lesion,'')) LIKE '%lesion%'
                       OR LOWER(COALESCE(ip.lesion,'')) LIKE '%fallec%')";
        $st = $this->pdo->prepare($sql);
        $st->execute([$involucradoPersonaId, $accidenteId]);
        return (int) $st->fetchColumn() > 0;
    }

    public function availableYears(): array
    {
        return array_map('intval', $this->pdo->query('SELECT DISTINCT anio FROM oficios ORDER BY anio DESC')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function search(array $filters): array
    {
        $oficiosTable = $this->activeTable('oficios');
        $accidentTable = $this->activeTable('accidentes');
        $vehicleTable = $this->activeTable('involucrados_vehiculos');
        $select = [
            'o.creado_por', 'o.gestion', 'o.encargado_id', 'o.comisaria_id', 'o.responsable_documento', 'o.id', 'o.numero', 'o.anio', 'o.fecha_emision', 'o.estado', 'o.accidente_id',
            "COALESCE(o.motivo,'') AS motivo",
            'COALESCE(NULLIF(e.siglas, \'\'), e.nombre) AS entidad',
            'COALESCE(o.persona_destino_manual, \'\') AS persona_destino_manual',
            'a.registro_sidpol',
            'a.id AS accid',
            'COALESCE(s.detalle,\'\') AS detalle',
            'COALESCE(s.nombre,\'\') AS asunto_nombre',
            'COALESCE(s.tipo,\'\') AS asunto_tipo'
        ];
        if ($this->columnExists('oficios', 'creado_por')) {
            $select[] = "COALESCE(u.nombre,'') AS registrante_nombre";
            $select[] = "COALESCE(u.grado,'') AS registrante_grado";
        } else {
            $select[] = "'' AS registrante_nombre";
            $select[] = "'' AS registrante_grado";
        }
        $select[] = 'CASE WHEN o.gestion=1 THEN o.encargado_id ELSE a.responsable_id END AS editor_encargado_id';
        $select[] = "CASE WHEN o.gestion=1 THEN TRIM(CONCAT(COALESCE(enc.grado,''),' ',COALESCE(enc.nombre,''))) ELSE TRIM(CONCAT(COALESCE(jefe.grado,''),' ',COALESCE(jefe.nombre,''))) END AS encargado_nombre";
        $select[] = $this->columnExists('oficios', 'categoria') ? "COALESCE(o.categoria,'') AS categoria" : "'' AS categoria";
        $joins = [
            'LEFT JOIN oficio_entidad e ON e.id = o.entidad_id_destino',
            "LEFT JOIN {$accidentTable} a ON a.id = o.accidente_id",
            'LEFT JOIN oficio_asunto s ON s.id = o.asunto_id',
            'LEFT JOIN usuarios enc ON enc.id = o.encargado_id',
            'LEFT JOIN usuarios jefe ON jefe.id = a.responsable_id'
        ];
        if ($this->columnExists('oficios', 'creado_por')) {
            $joins[] = 'LEFT JOIN usuarios u ON u.id = o.creado_por';
        }

        if ($this->columnExists('oficios', 'involucrado_vehiculo_id')) {
            $joins[] = "LEFT JOIN {$vehicleTable} iv ON iv.id = o.involucrado_vehiculo_id";
            $joins[] = 'LEFT JOIN vehiculos v ON v.id = iv.vehiculo_id';
            $select[] = 'COALESCE(v.placa,\'\') AS veh_placa';
            $select[] = 'COALESCE(iv.orden_participacion,\'\') AS veh_ut';
        } else {
            $select[] = "'' AS veh_placa";
            $select[] = "'' AS veh_ut";
        }

        if ($this->columnExists('oficios', 'involucrado_persona_id')) {
            $select[] = 'o.involucrado_persona_id AS inv_per_id';
            $personTable = $this->activeTable('involucrados_personas');
            $joins[] = "LEFT JOIN {$personTable} ip ON ip.id = o.involucrado_persona_id";
            $joins[] = 'LEFT JOIN personas pe ON pe.id = ip.persona_id';
            $select[] = "TRIM(CONCAT(COALESCE(pe.nombres,''),' ',COALESCE(pe.apellido_paterno,''),' ',COALESCE(pe.apellido_materno,''))) AS persona_nombre";
        } else {
            $select[] = 'NULL AS inv_per_id';
            $select[] = "'' AS persona_nombre";
        }

        $sql = 'SELECT ' . implode(', ', $select) . " FROM {$oficiosTable} o " . implode(' ', $joins) . ' WHERE 1=1';
        $params = [];

        if (!empty($filters['anio'])) {
            $sql .= ' AND o.anio = ?';
            $params[] = (int) $filters['anio'];
        }
        if (!empty($filters['encargado_id'])) {
            $sql .= ' AND (CASE WHEN o.gestion=1 THEN o.encargado_id ELSE a.responsable_id END) = ?';
            $params[] = (int) $filters['encargado_id'];
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
        if (!empty($filters['categoria'])) {
            $sql .= ' AND o.categoria = ?';
            $params[] = $filters['categoria'];
        }
        if (!empty($filters['tipo'])) {
            $sql .= ' AND s.tipo = ?';
            $params[] = $filters['tipo'];
        }
        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $sql .= ' AND (o.numero LIKE ? OR COALESCE(o.referencia_texto,\'\') LIKE ? OR COALESCE(o.motivo,\'\') LIKE ? OR COALESCE(a.registro_sidpol,\'\') LIKE ? OR COALESCE(s.detalle,\'\') LIKE ? OR COALESCE(s.nombre,\'\') LIKE ? OR COALESCE(e.nombre,\'\') LIKE ? OR COALESCE(e.siglas,\'\') LIKE ? OR COALESCE(v.placa,\'\') LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like);
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
        if ($this->columnExists('oficios', 'diligencias_solicitadas')) {
            $columns[] = 'diligencias_solicitadas';
            $values[] = $payload['diligencias_solicitadas'] ?? null;
        }
        if ($this->columnExists('oficios', 'categoria')) {
            $columns[] = 'categoria';
            $values[] = $payload['categoria'] ?? null;
        }
        if ($this->columnExists('oficios', 'creado_por')) {
            $columns[] = 'creado_por';
            $values[] = $payload['creado_por'] ?? null;
        }
        foreach (['gestion','comisaria_id','encargado_id'] as $field) {
            $columns[] = $field;
            $values[] = $payload[$field] ?? ($field === 'gestion' ? 0 : null);
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
        if ($this->columnExists('oficios', 'diligencias_solicitadas')) {
            $sets[] = 'diligencias_solicitadas = ?';
            $values[] = $payload['diligencias_solicitadas'] ?? null;
        }
        if ($this->columnExists('oficios', 'categoria')) {
            $sets[] = 'categoria = ?';
            $values[] = $payload['categoria'] ?? null;
        }
        foreach (['comisaria_id','encargado_id'] as $field) {
            $sets[] = $field . ' = ?';
            $values[] = $payload[$field] ?? null;
        }
        $values[] = $id;
        $sql = 'UPDATE oficios SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($values);
    }

    public function delete(int $id): void
    {
        if (!\App\Support\Access::admin()) throw new \RuntimeException('Solo el administrador puede eliminar oficios.');
        $st = $this->pdo->prepare('DELETE FROM oficios WHERE id = ? LIMIT 1');
        $st->execute([$id]);
    }

    public function find(int $id): ?array
    {
        $oficiosTable = $this->activeTable('oficios');
        $st = $this->pdo->prepare("SELECT o.*, CASE WHEN o.gestion=1 THEN o.encargado_id ELSE a.responsable_id END AS editor_encargado_id FROM {$oficiosTable} o LEFT JOIN accidentes a ON a.id=o.accidente_id WHERE o.id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function detail(int $id): ?array
    {
        $oficiosTable = $this->activeTable('oficios');
        $accidentTable = $this->activeTable('accidentes');
        $vehicleTable = $this->activeTable('involucrados_vehiculos');
        $personTable = $this->activeTable('involucrados_personas');
        $select = [
            'o.*',
            'CASE WHEN o.gestion=1 THEN o.encargado_id ELSE a.responsable_id END AS editor_encargado_id',
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
            "LEFT JOIN {$personTable} ipf ON ipf.id = o.involucrado_persona_id",
            'LEFT JOIN personas pf ON pf.id = ipf.persona_id',
            "LEFT JOIN {$accidentTable} a ON a.id = o.accidente_id",
            "LEFT JOIN {$vehicleTable} iv ON iv.id = o.involucrado_vehiculo_id",
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
        $sql = 'SELECT ' . implode(', ', $select) . " FROM {$oficiosTable} o " . implode(' ', $joins) . ' WHERE o.id = ? LIMIT 1';
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

    private function presetFamilyKey(string $name): string
    {
        $text = $this->normalizeCatalogText($name);
        if (str_contains($text,'necropsia') || str_contains($text,'autopsia')) return 'protocolo-necropsia';
        if (str_contains($text,'peritaje') && (str_contains($text,'constat') || str_contains($text,'dano'))) return 'peritaje-danos';
        return $this->asuntoCatalogKey($name);
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
