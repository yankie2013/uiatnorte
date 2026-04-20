<?php
require __DIR__ . '/auth.php';
require_login();

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$pdo = null;
$dbError = '';

try {
    $pdo = \App\Database\Database::connection();
    \App\Database\Database::defineLegacyConstants();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['catalogos_csrf'])) {
    $_SESSION['catalogos_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['catalogos_csrf'];

function catalogConfigs(): array
{
    return [
        'entidades' => [
            'label' => 'Entidades',
            'table' => 'oficio_entidad',
            'description' => 'Comisarias, fiscalias, municipalidades, empresas y otras entidades destino.',
            'order' => 'nombre ASC',
            'fields' => [
                ['name' => 'tipo', 'label' => 'Naturaleza', 'type' => 'select', 'required' => true, 'choices' => [
                    'PUBLICA' => 'PUBLICA',
                    'PRIVADA' => 'PRIVADA',
                    'PERSONA_NATURAL' => 'PERSONA NATURAL',
                    'OTRA' => 'OTRA',
                ]],
                ['name' => 'categoria', 'label' => 'Categoria', 'type' => 'select', 'choices' => [
                    'COMISARIA' => 'COMISARIA',
                    'FISCALIA' => 'FISCALIA',
                    'MILITARES' => 'MILITARES',
                    'POLICIALES' => 'POLICIALES',
                    'NECROPSIA' => 'NECROPSIA',
                    'MUNICIPALIDAD' => 'MUNICIPALIDAD',
                    'EMPRESA_PUBLICA' => 'EMPRESA PUBLICA',
                    'EMPRESA_PRIVADA' => 'EMPRESA PRIVADA',
                    'HOSPITAL' => 'HOSPITAL',
                    'CLINICA' => 'CLINICA',
                    'JUZGADO' => 'JUZGADO',
                    'ASEGURADORA' => 'ASEGURADORA',
                    'OTRA' => 'OTRA',
                ]],
                ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['name' => 'siglas', 'label' => 'Siglas', 'type' => 'text'],
                ['name' => 'direccion', 'label' => 'Direccion', 'type' => 'text'],
                ['name' => 'telefono', 'label' => 'Telefono', 'type' => 'text'],
                ['name' => 'telefono_fijo', 'label' => 'Telefono fijo', 'type' => 'text'],
                ['name' => 'telefono_movil', 'label' => 'Telefono movil', 'type' => 'text'],
                ['name' => 'correo', 'label' => 'Correo', 'type' => 'text'],
                ['name' => 'pagina_web', 'label' => 'Pagina web', 'type' => 'text'],
            ],
        ],
        'oficial_ano' => [
            'label' => 'Nombre oficial del ano',
            'table' => 'oficio_oficial_ano',
            'description' => 'Frases oficiales por ano para oficios.',
            'order' => 'anio DESC, id DESC',
            'fields' => [
                ['name' => 'anio', 'label' => 'Ano', 'type' => 'number', 'required' => true],
                ['name' => 'nombre', 'label' => 'Nombre oficial', 'type' => 'text', 'required' => true],
                ['name' => 'decreto', 'label' => 'Decreto', 'type' => 'text'],
                ['name' => 'vigente', 'label' => 'Vigente', 'type' => 'checkbox'],
            ],
        ],
        'grado_cargo' => [
            'label' => 'Grados y cargos',
            'table' => 'grado_cargo',
            'description' => 'Grados policiales y cargos usados en destinatarios.',
            'order' => 'tipo ASC, orden ASC, nombre ASC',
            'fields' => [
                ['name' => 'tipo', 'label' => 'Tipo', 'type' => 'select', 'required' => true, 'choices' => ['GRADO' => 'GRADO', 'CARGO' => 'CARGO']],
                ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['name' => 'abreviatura', 'label' => 'Abreviatura', 'type' => 'text'],
                ['name' => 'orden', 'label' => 'Orden', 'type' => 'number'],
                ['name' => 'activo', 'label' => 'Activo', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
        'asuntos' => [
            'label' => 'Asuntos de oficio',
            'table' => 'oficio_asunto',
            'description' => 'Asuntos predefinidos por entidad y tipo de oficio.',
            'order' => 'entidad_id ASC, tipo ASC, orden ASC, nombre ASC',
            'fields' => [
                ['name' => 'entidad_id', 'label' => 'Entidad', 'type' => 'relation', 'required' => true, 'source' => 'oficio_entidad'],
                ['name' => 'tipo', 'label' => 'Tipo', 'type' => 'select', 'required' => true, 'choices' => ['SOLICITAR' => 'SOLICITAR', 'REMITIR' => 'REMITIR']],
                ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['name' => 'detalle', 'label' => 'Detalle', 'type' => 'textarea'],
                ['name' => 'orden', 'label' => 'Orden', 'type' => 'number'],
                ['name' => 'activo', 'label' => 'Activo', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
        'subentidades' => [
            'label' => 'Subentidades',
            'table' => 'oficio_subentidad',
            'description' => 'Sedes, oficinas, areas y unidades internas de una entidad.',
            'order' => 'entidad_id ASC, tipo ASC, nombre ASC',
            'fields' => [
                ['name' => 'entidad_id', 'label' => 'Entidad', 'type' => 'relation', 'required' => true, 'source' => 'oficio_entidad'],
                ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['name' => 'siglas', 'label' => 'Siglas', 'type' => 'text'],
                ['name' => 'tipo', 'label' => 'Tipo', 'type' => 'select', 'choices' => [
                    'SEDE' => 'SEDE',
                    'GERENCIA' => 'GERENCIA',
                    'DIRECCION' => 'DIRECCION',
                    'OFICINA' => 'OFICINA',
                    'UNIDAD' => 'UNIDAD',
                    'DEPARTAMENTO' => 'DEPARTAMENTO',
                    'AREA' => 'AREA',
                    'OTRA' => 'OTRA',
                ]],
                ['name' => 'codigo', 'label' => 'Codigo', 'type' => 'text'],
                ['name' => 'parent_id', 'label' => 'Depende de', 'type' => 'relation', 'source' => 'oficio_subentidad', 'nullable' => true],
                ['name' => 'direccion', 'label' => 'Direccion', 'type' => 'text'],
                ['name' => 'telefono', 'label' => 'Telefono', 'type' => 'text'],
                ['name' => 'correo', 'label' => 'Correo', 'type' => 'text'],
                ['name' => 'pagina_web', 'label' => 'Pagina web', 'type' => 'text'],
                ['name' => 'activo', 'label' => 'Activo', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
        'personas_entidad' => [
            'label' => 'Personas destino',
            'table' => 'oficio_persona_entidad',
            'description' => 'Contactos o funcionarios asociados a entidades.',
            'order' => 'entidad_id ASC, apellido_paterno ASC, nombres ASC',
            'fields' => [
                ['name' => 'entidad_id', 'label' => 'Entidad', 'type' => 'relation', 'required' => true, 'source' => 'oficio_entidad'],
                ['name' => 'nombres', 'label' => 'Nombres', 'type' => 'text', 'required' => true],
                ['name' => 'apellido_paterno', 'label' => 'Apellido paterno', 'type' => 'text', 'required' => true],
                ['name' => 'apellido_materno', 'label' => 'Apellido materno', 'type' => 'text'],
                ['name' => 'telefono', 'label' => 'Telefono', 'type' => 'text'],
                ['name' => 'direccion', 'label' => 'Direccion', 'type' => 'text'],
                ['name' => 'pagina_web', 'label' => 'Pagina web', 'type' => 'text'],
                ['name' => 'correo', 'label' => 'Correo', 'type' => 'text'],
                ['name' => 'observacion', 'label' => 'Observacion', 'type' => 'textarea'],
                ['name' => 'activo', 'label' => 'Activo', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
        'categorias_vehiculo' => [
            'label' => 'Categorias de vehiculo',
            'table' => 'categoria_vehiculos',
            'description' => 'Categorias base para vehiculos.',
            'order' => 'codigo ASC',
            'fields' => [
                ['name' => 'codigo', 'label' => 'Codigo', 'type' => 'text', 'required' => true],
                ['name' => 'descripcion', 'label' => 'Descripcion', 'type' => 'textarea'],
            ],
        ],
        'marcas_vehiculo' => [
            'label' => 'Marcas de vehiculo',
            'table' => 'marcas_vehiculo',
            'description' => 'Marcas usadas en registro vehicular.',
            'order' => 'nombre ASC',
            'fields' => [
                ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['name' => 'pais_origen', 'label' => 'Pais de origen', 'type' => 'text'],
            ],
        ],
        'modelos_vehiculo' => [
            'label' => 'Modelos de vehiculo',
            'table' => 'modelos_vehiculo',
            'description' => 'Modelos asociados a una marca.',
            'order' => 'marca_id ASC, nombre ASC',
            'fields' => [
                ['name' => 'marca_id', 'label' => 'Marca', 'type' => 'relation', 'required' => true, 'source' => 'marcas_vehiculo'],
                ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
            ],
        ],
        'tipos_vehiculo' => [
            'label' => 'Tipos de vehiculo',
            'table' => 'tipos_vehiculo',
            'description' => 'Tipos por categoria vehicular.',
            'order' => 'categoria_id ASC, codigo ASC',
            'fields' => [
                ['name' => 'categoria_id', 'label' => 'Categoria', 'type' => 'relation', 'required' => true, 'source' => 'categoria_vehiculos'],
                ['name' => 'codigo', 'label' => 'Codigo', 'type' => 'text', 'required' => true],
                ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['name' => 'descripcion', 'label' => 'Descripcion', 'type' => 'textarea'],
            ],
        ],
        'carrocerias_vehiculo' => [
            'label' => 'Carrocerias',
            'table' => 'carroceria_vehiculo',
            'description' => 'Carrocerias asociadas a un tipo de vehiculo.',
            'order' => 'tipo_id ASC, nombre ASC',
            'fields' => [
                ['name' => 'tipo_id', 'label' => 'Tipo de vehiculo', 'type' => 'relation', 'required' => true, 'source' => 'tipos_vehiculo'],
                ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['name' => 'descripcion', 'label' => 'Descripcion', 'type' => 'textarea'],
            ],
        ],
        'enlaces' => [
            'label' => 'Enlaces de interes',
            'table' => 'enlace_interes',
            'description' => 'Enlaces rapidos y accesos de consulta.',
            'order' => 'activo DESC, orden ASC, nombre ASC',
            'fields' => [
                ['name' => 'categoria', 'label' => 'Categoria', 'type' => 'select', 'required' => true, 'choices' => [
                    'TRANSITO' => 'TRANSITO',
                    'VEHICULAR' => 'VEHICULAR',
                    'SEGUROS' => 'SEGUROS',
                    'PNP' => 'PNP',
                    'FISCALIA' => 'FISCALIA',
                    'MUNICIPAL' => 'MUNICIPAL',
                    'OTROS' => 'OTROS',
                ]],
                ['name' => 'nombre', 'label' => 'Nombre', 'type' => 'text', 'required' => true],
                ['name' => 'url', 'label' => 'URL', 'type' => 'text', 'required' => true],
                ['name' => 'descripcion', 'label' => 'Descripcion', 'type' => 'textarea'],
                ['name' => 'orden', 'label' => 'Orden', 'type' => 'number'],
                ['name' => 'activo', 'label' => 'Activo', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
    ];
}

function tableColumns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $st = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $st->execute([$table]);
    return $cache[$table] = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function tableExists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $st->execute([$table]);
    return (int) $st->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    return in_array($column, tableColumns($pdo, $table), true);
}

function availableCatalogs(?PDO $pdo, array $configs): array
{
    if (!$pdo) {
        return $configs;
    }
    return array_filter($configs, static fn (array $config): bool => tableExists($pdo, $config['table']));
}

function fieldsFor(PDO $pdo, array $config): array
{
    return array_values(array_filter(
        $config['fields'],
        static fn (array $field): bool => columnExists($pdo, $config['table'], $field['name'])
    ));
}

function relationOptions(PDO $pdo, string $source, ?int $excludeId = null): array
{
    if (!tableExists($pdo, $source)) {
        return [];
    }

    if ($source === 'oficio_entidad') {
        $siglasExpr = columnExists($pdo, 'oficio_entidad', 'siglas') ? "IF(COALESCE(siglas,'') <> '', CONCAT(' - ', siglas), '')" : "''";
        $sql = "SELECT id, CONCAT(nombre, {$siglasExpr}) AS label FROM oficio_entidad ORDER BY nombre ASC";
    } elseif ($source === 'oficio_subentidad') {
        $sql = "SELECT se.id, CONCAT(COALESCE(e.nombre,''), ' / ', se.nombre) AS label FROM oficio_subentidad se LEFT JOIN oficio_entidad e ON e.id = se.entidad_id";
        $sql .= $excludeId ? ' WHERE se.id <> ' . (int) $excludeId : '';
        $sql .= ' ORDER BY e.nombre ASC, se.nombre ASC';
    } elseif ($source === 'marcas_vehiculo') {
        $sql = 'SELECT id, nombre AS label FROM marcas_vehiculo ORDER BY nombre ASC';
    } elseif ($source === 'categoria_vehiculos') {
        $descExpr = columnExists($pdo, 'categoria_vehiculos', 'descripcion') ? "IF(COALESCE(descripcion,'') <> '', CONCAT(' - ', descripcion), '')" : "''";
        $sql = "SELECT id, CONCAT(codigo, {$descExpr}) AS label FROM categoria_vehiculos ORDER BY codigo ASC";
    } elseif ($source === 'tipos_vehiculo') {
        $sql = "SELECT tv.id, CONCAT(COALESCE(cv.codigo,''), ' / ', tv.codigo, ' - ', tv.nombre) AS label FROM tipos_vehiculo tv LEFT JOIN categoria_vehiculos cv ON cv.id = tv.categoria_id ORDER BY cv.codigo ASC, tv.codigo ASC";
    } else {
        return [];
    }

    return $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
}

function orderFor(PDO $pdo, array $config): string
{
    $parts = [];
    foreach (explode(',', (string) ($config['order'] ?? 'id DESC')) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^([a-zA-Z0-9_]+)(?:\s+(ASC|DESC))?$/i', $part, $match) && columnExists($pdo, $config['table'], $match[1])) {
            $parts[] = '`' . $match[1] . '` ' . strtoupper($match[2] ?? 'ASC');
        }
    }
    return $parts !== [] ? implode(', ', $parts) : '`id` DESC';
}

function relationLabel(PDO $pdo, string $source, mixed $id): string
{
    $id = (int) $id;
    if ($id <= 0) {
        return '';
    }
    $options = relationOptions($pdo, $source);
    return (string) ($options[$id] ?? ('#' . $id));
}

function loadRows(PDO $pdo, array $config, array $fields, string $query): array
{
    $table = $config['table'];
    $select = ['id'];
    foreach ($fields as $field) {
        $select[] = $field['name'];
    }
    $where = [];
    $params = [];
    if ($query !== '') {
        foreach ($fields as $field) {
            if (in_array($field['type'], ['text', 'textarea', 'select'], true)) {
                $where[] = '`' . $field['name'] . '` LIKE ?';
                $params[] = '%' . $query . '%';
            }
        }
    }

    $sql = 'SELECT `' . implode('`, `', $select) . '` FROM `' . $table . '`';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' OR ', $where);
    }
    $sql .= ' ORDER BY ' . orderFor($pdo, $config) . ' LIMIT 500';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function findRow(PDO $pdo, array $config, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM `' . $config['table'] . '` WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function normalizePayload(PDO $pdo, array $config, array $fields, array $input, ?int $editingId = null): array
{
    $payload = [];
    foreach ($fields as $field) {
        $name = $field['name'];
        $type = $field['type'];
        $required = !empty($field['required']);

        if ($type === 'checkbox') {
            $payload[$name] = !empty($input[$name]) ? 1 : 0;
            continue;
        }

        $raw = trim((string) ($input[$name] ?? ''));
        if ($required && $raw === '') {
            throw new InvalidArgumentException($field['label'] . ' es obligatorio.');
        }

        if ($raw === '' && !empty($field['nullable'])) {
            $payload[$name] = null;
            continue;
        }

        if ($type === 'number') {
            $payload[$name] = $raw === '' ? null : (int) $raw;
        } elseif ($type === 'relation') {
            $value = $raw === '' ? null : (int) $raw;
            if ($required && (!$value || $value <= 0)) {
                throw new InvalidArgumentException('Selecciona ' . mb_strtolower($field['label'], 'UTF-8') . '.');
            }
            if ($value && $editingId !== null && $config['table'] === 'oficio_subentidad' && $name === 'parent_id' && $value === $editingId) {
                throw new InvalidArgumentException('Una subentidad no puede depender de si misma.');
            }
            if ($value && !findRow($pdo, ['table' => $field['source']], $value)) {
                throw new InvalidArgumentException($field['label'] . ' no existe.');
            }
            $payload[$name] = $value;
        } elseif ($type === 'select') {
            $choices = $field['choices'] ?? [];
            if ($raw === '' && !$required) {
                $payload[$name] = null;
            } elseif ($choices !== [] && !array_key_exists($raw, $choices)) {
                throw new InvalidArgumentException($field['label'] . ' no es valido.');
            } else {
                $payload[$name] = $raw;
            }
        } else {
            if ($name === 'correo' && $raw !== '' && !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('El correo no tiene un formato valido.');
            }
            if ($name === 'url' && $raw !== '') {
                if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $raw)) {
                    $raw = 'https://' . ltrim($raw, '/');
                }
                if (!filter_var($raw, FILTER_VALIDATE_URL)) {
                    throw new InvalidArgumentException('La URL no tiene un formato valido.');
                }
            }
            $payload[$name] = $raw === '' ? null : $raw;
        }
    }
    return $payload;
}

function saveCatalogRow(PDO $pdo, array $config, array $fields, array $input, ?int $id): void
{
    $payload = normalizePayload($pdo, $config, $fields, $input, $id);
    $table = $config['table'];
    $setsVigente = $table === 'oficio_oficial_ano' && (int) ($payload['vigente'] ?? 0) === 1;
    $started = false;

    if ($setsVigente && !$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $started = true;
    }

    try {
        if ($setsVigente) {
            $pdo->exec('UPDATE oficio_oficial_ano SET vigente = 0');
        }

        if ($id !== null) {
            if (!findRow($pdo, $config, $id)) {
                throw new InvalidArgumentException('Registro no encontrado.');
            }
            $sets = [];
            $values = [];
            foreach ($payload as $column => $value) {
                $sets[] = '`' . $column . '` = ?';
                $values[] = $value;
            }
            $values[] = $id;
            $st = $pdo->prepare('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE id = ?');
            $st->execute($values);
        } else {
            $columns = array_keys($payload);
            $marks = array_fill(0, count($columns), '?');
            $st = $pdo->prepare('INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $marks) . ')');
            $st->execute(array_values($payload));
        }

        if ($started) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function manualReferences(): array
{
    return [
        'oficio_entidad' => [
            ['oficios', 'entidad_id_destino'],
            ['oficio_asunto', 'entidad_id'],
            ['oficio_subentidad', 'entidad_id'],
            ['oficio_persona_entidad', 'entidad_id'],
        ],
        'oficio_oficial_ano' => [['oficios', 'oficial_ano_id']],
        'grado_cargo' => [['oficios', 'grado_cargo_id'], ['personas', 'grado_cargo_id']],
        'oficio_asunto' => [['oficios', 'asunto_id']],
        'oficio_subentidad' => [['oficios', 'subentidad_destino_id'], ['oficio_subentidad', 'parent_id']],
        'oficio_persona_entidad' => [['oficios', 'persona_destino_id']],
        'categoria_vehiculos' => [['tipos_vehiculo', 'categoria_id'], ['vehiculos', 'categoria_id']],
        'marcas_vehiculo' => [['modelos_vehiculo', 'marca_id'], ['vehiculos', 'marca_id']],
        'modelos_vehiculo' => [['vehiculos', 'modelo_id']],
        'tipos_vehiculo' => [['carroceria_vehiculo', 'tipo_id'], ['vehiculos', 'tipo_id']],
        'carroceria_vehiculo' => [['vehiculos', 'carroceria_id']],
    ];
}

function referenceCounts(PDO $pdo, string $table, int $id): array
{
    $refs = manualReferences()[$table] ?? [];
    $st = $pdo->prepare(
        'SELECT TABLE_NAME, COLUMN_NAME
           FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE()
            AND REFERENCED_TABLE_NAME = ?
            AND REFERENCED_COLUMN_NAME = ?'
    );
    $st->execute([$table, 'id']);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $refs[] = [(string) $row['TABLE_NAME'], (string) $row['COLUMN_NAME']];
    }

    $unique = [];
    foreach ($refs as $ref) {
        [$refTable, $refColumn] = $ref;
        $key = $refTable . '.' . $refColumn;
        if (isset($unique[$key]) || !tableExists($pdo, $refTable) || !columnExists($pdo, $refTable, $refColumn)) {
            continue;
        }
        $unique[$key] = [$refTable, $refColumn];
    }

    $counts = [];
    foreach ($unique as [$refTable, $refColumn]) {
        $count = 0;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `' . $refTable . '` WHERE `' . $refColumn . '` = ?');
        $stmt->execute([$id]);
        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            $counts[] = $refTable . '.' . $refColumn . ': ' . $count;
        }
    }
    return $counts;
}

function deleteCatalogRow(PDO $pdo, array $config, int $id): void
{
    if ($id <= 0 || !findRow($pdo, $config, $id)) {
        throw new InvalidArgumentException('Registro no encontrado.');
    }
    $references = referenceCounts($pdo, $config['table'], $id);
    if ($references !== []) {
        throw new InvalidArgumentException('No se puede eliminar porque ya esta en uso: ' . implode(', ', $references) . '.');
    }
    $st = $pdo->prepare('DELETE FROM `' . $config['table'] . '` WHERE id = ?');
    $st->execute([$id]);
}

$configs = catalogConfigs();
$available = availableCatalogs($pdo, $configs);
$catalogKey = (string) ($_GET['catalog'] ?? ($_POST['catalog'] ?? array_key_first($available)));
if (!isset($available[$catalogKey])) {
    $catalogKey = (string) array_key_first($available);
}

$flash = (string) ($_GET['msg'] ?? '');
$error = '';

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            throw new InvalidArgumentException('Sesion expirada. Vuelve a intentarlo.');
        }
        if (!isset($available[$catalogKey])) {
            throw new InvalidArgumentException('Catalogo no disponible.');
        }
        $config = $available[$catalogKey];
        $fields = fieldsFor($pdo, $config);
        $action = (string) ($_POST['action'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);

        if ($action === 'delete') {
            deleteCatalogRow($pdo, $config, $id);
            header('Location: catalogos.php?catalog=' . urlencode($catalogKey) . '&msg=deleted');
            exit;
        }

        if ($action === 'save') {
            saveCatalogRow($pdo, $config, $fields, $_POST, $id > 0 ? $id : null);
            header('Location: catalogos.php?catalog=' . urlencode($catalogKey) . '&msg=saved');
            exit;
        }

        throw new InvalidArgumentException('Accion no reconocida.');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$selected = $catalogKey && isset($available[$catalogKey]) ? $available[$catalogKey] : null;
$fields = ($pdo && $selected) ? fieldsFor($pdo, $selected) : [];
$editId = (int) ($_GET['edit'] ?? 0);
$editRow = ($pdo && $selected && $editId > 0) ? findRow($pdo, $selected, $editId) : null;
$query = trim((string) ($_GET['q'] ?? ''));
$rows = ($pdo && $selected) ? loadRows($pdo, $selected, $fields, $query) : [];

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Catalogos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--accent:#0f766e;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--accent:#2dd4bf;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1440px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px}.small{font-size:12px;color:var(--muted)}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(37,99,235,.12);color:var(--primary);border:1px solid rgba(37,99,235,.18);font-size:11px}.btn{padding:10px 14px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:800;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#eff6ff;border-color:transparent}.btn.danger{color:var(--danger)}.layout{display:grid;grid-template-columns:280px minmax(0,1fr);gap:14px}.panel{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:16px}.catalog-list{display:flex;flex-direction:column;gap:8px}.catalog-link{display:flex;justify-content:space-between;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:8px;color:var(--text);text-decoration:none;background:rgba(148,163,184,.06)}.catalog-link.active{border-color:rgba(37,99,235,.45);background:rgba(37,99,235,.10);color:var(--primary)}.catalog-main{display:flex;flex-direction:column;gap:14px}.notice{padding:12px 14px;border-radius:8px;border:1px solid var(--border);background:var(--card)}.notice.ok{border-color:rgba(22,101,52,.25);color:var(--ok);background:rgba(22,163,74,.10)}.notice.err{border-color:rgba(185,28,28,.25);color:var(--danger);background:rgba(220,38,38,.10)}.form-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.field{grid-column:span 6;display:flex;flex-direction:column;gap:6px}.field.full{grid-column:span 12}.label{font-size:12px;color:var(--muted);font-weight:800}input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:transparent;color:var(--text);font:inherit}textarea{min-height:92px;resize:vertical}.check-row{flex-direction:row;align-items:center}.check-row input{width:auto}.actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:14px}.filters{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}.filters label{display:flex;flex-direction:column;gap:6px;flex:1;min-width:240px}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:8px;background:var(--card)}table{width:100%;border-collapse:collapse;min-width:920px}th,td{padding:11px 12px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}th{font-size:12px;color:var(--muted);text-transform:uppercase;background:rgba(148,163,184,.08)}tbody tr:hover{background:rgba(37,99,235,.05)}.cell-muted{color:var(--muted)}.row-actions{display:flex;gap:8px;flex-wrap:wrap}.row-actions form{margin:0}.empty{padding:24px;text-align:center;color:var(--muted)}@media(max-width:980px){.layout{grid-template-columns:1fr}.field{grid-column:span 12}.catalog-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}.filters label{min-width:100%}.filters .btn{width:100%}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0 0 6px">Catalogos <span class="badge">Mantenimiento</span></h1>
      <div class="small">Agrega, edita o elimina elementos usados por oficios, vehiculos y accesos rapidos.</div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="index.php">Panel</a>
      <a class="btn" href="oficio_entidades_listar.php">Prontuario entidades</a>
    </div>
  </div>

  <?php if ($dbError !== ''): ?>
    <div class="notice err">No se pudo conectar a la base de datos: <?= h($dbError) ?>.</div>
  <?php else: ?>
    <?php if ($flash === 'saved'): ?><div class="notice ok">Registro guardado correctamente.</div><?php endif; ?>
    <?php if ($flash === 'deleted'): ?><div class="notice ok">Registro eliminado correctamente.</div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice err"><?= h($error) ?></div><?php endif; ?>

    <div class="layout">
      <aside class="panel">
        <div style="font-weight:900;margin-bottom:10px">Catalogos disponibles</div>
        <div class="catalog-list">
          <?php foreach ($available as $key => $config): ?>
            <a class="catalog-link <?= $key === $catalogKey ? 'active' : '' ?>" href="catalogos.php?catalog=<?= h($key) ?>">
              <span><?= h($config['label']) ?></span>
              <span class="cell-muted">&#8250;</span>
            </a>
          <?php endforeach; ?>
        </div>
      </aside>

      <main class="catalog-main">
        <?php if (!$selected): ?>
          <div class="panel empty">No hay catalogos disponibles en esta base de datos.</div>
        <?php else: ?>
          <section class="panel">
            <div class="toolbar" style="margin-bottom:12px">
              <div>
                <h2 style="margin:0 0 4px"><?= h($editRow ? 'Editar: ' . $selected['label'] : 'Nuevo: ' . $selected['label']) ?></h2>
                <div class="small"><?= h($selected['description']) ?></div>
              </div>
              <?php if ($editRow): ?>
                <a class="btn" href="catalogos.php?catalog=<?= h($catalogKey) ?>">Nuevo registro</a>
              <?php endif; ?>
            </div>

            <form method="post">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="catalog" value="<?= h($catalogKey) ?>">
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="id" value="<?= (int) ($editRow['id'] ?? 0) ?>">
              <div class="form-grid">
                <?php foreach ($fields as $field): ?>
                  <?php
                  $name = $field['name'];
                  $value = $editRow[$name] ?? ($field['default'] ?? '');
                  $full = $field['type'] === 'textarea' || in_array($name, ['detalle', 'descripcion', 'observacion'], true);
                  ?>
                  <?php if ($field['type'] === 'checkbox'): ?>
                    <label class="field check-row">
                      <input type="checkbox" name="<?= h($name) ?>" value="1" <?= (int) $value === 1 ? 'checked' : '' ?>>
                      <span class="label"><?= h($field['label']) ?></span>
                    </label>
                  <?php elseif ($field['type'] === 'textarea'): ?>
                    <label class="field full">
                      <span class="label"><?= h($field['label']) ?><?= !empty($field['required']) ? '*' : '' ?></span>
                      <textarea name="<?= h($name) ?>"><?= h($value) ?></textarea>
                    </label>
                  <?php elseif ($field['type'] === 'select'): ?>
                    <label class="field <?= $full ? 'full' : '' ?>">
                      <span class="label"><?= h($field['label']) ?><?= !empty($field['required']) ? '*' : '' ?></span>
                      <select name="<?= h($name) ?>" <?= !empty($field['required']) ? 'required' : '' ?>>
                        <?php if (empty($field['required'])): ?><option value="">Sin seleccionar</option><?php endif; ?>
                        <?php foreach (($field['choices'] ?? []) as $optionValue => $optionLabel): ?>
                          <option value="<?= h($optionValue) ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>><?= h($optionLabel) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                  <?php elseif ($field['type'] === 'relation'): ?>
                    <?php $options = relationOptions($pdo, $field['source'], $editRow ? (int) $editRow['id'] : null); ?>
                    <label class="field <?= $full ? 'full' : '' ?>">
                      <span class="label"><?= h($field['label']) ?><?= !empty($field['required']) ? '*' : '' ?></span>
                      <select name="<?= h($name) ?>" <?= !empty($field['required']) ? 'required' : '' ?>>
                        <option value=""><?= !empty($field['required']) ? 'Selecciona' : 'Ninguna' ?></option>
                        <?php foreach ($options as $optionValue => $optionLabel): ?>
                          <option value="<?= (int) $optionValue ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>><?= h($optionLabel) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                  <?php else: ?>
                    <label class="field <?= $full ? 'full' : '' ?>">
                      <span class="label"><?= h($field['label']) ?><?= !empty($field['required']) ? '*' : '' ?></span>
                      <input type="<?= $field['type'] === 'number' ? 'number' : 'text' ?>" name="<?= h($name) ?>" value="<?= h($value) ?>" <?= !empty($field['required']) ? 'required' : '' ?>>
                    </label>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
              <div class="actions">
                <?php if ($editRow): ?><a class="btn" href="catalogos.php?catalog=<?= h($catalogKey) ?>">Cancelar</a><?php endif; ?>
                <button class="btn primary" type="submit">Guardar</button>
              </div>
            </form>
          </section>

          <section class="panel">
            <div class="toolbar">
              <div>
                <h2 style="margin:0 0 4px">Registros</h2>
                <div class="small">Mostrando hasta 500 resultados.</div>
              </div>
              <form class="filters" method="get">
                <input type="hidden" name="catalog" value="<?= h($catalogKey) ?>">
                <label>
                  <span class="label">Buscar</span>
                  <input type="search" name="q" value="<?= h($query) ?>" placeholder="Texto a buscar">
                </label>
                <button class="btn" type="submit">Buscar</button>
                <a class="btn" href="catalogos.php?catalog=<?= h($catalogKey) ?>">Limpiar</a>
              </form>
            </div>

            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>ID</th>
                    <?php foreach ($fields as $field): ?>
                      <th><?= h($field['label']) ?></th>
                    <?php endforeach; ?>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                  <tr><td colspan="<?= count($fields) + 2 ?>" class="empty">No hay registros para este catalogo.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $row): ?>
                    <tr>
                      <td>#<?= (int) $row['id'] ?></td>
                      <?php foreach ($fields as $field): ?>
                        <?php
                        $value = $row[$field['name']] ?? '';
                        if ($field['type'] === 'checkbox') {
                            $display = (int) $value === 1 ? 'Si' : 'No';
                        } elseif ($field['type'] === 'relation') {
                            $display = relationLabel($pdo, $field['source'], $value);
                        } else {
                            $display = (string) $value;
                        }
                        ?>
                        <td><?= $display !== '' ? h($display) : '<span class="cell-muted">-</span>' ?></td>
                      <?php endforeach; ?>
                      <td>
                        <div class="row-actions">
                          <a class="btn" href="catalogos.php?catalog=<?= h($catalogKey) ?>&edit=<?= (int) $row['id'] ?>">Editar</a>
                          <form method="post" onsubmit="return confirm('Eliminar este registro?');">
                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                            <input type="hidden" name="catalog" value="<?= h($catalogKey) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <button class="btn danger" type="submit">Eliminar</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>
        <?php endif; ?>
      </main>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
