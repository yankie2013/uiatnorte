<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
if (!class_exists(\PhpOffice\PhpWord\PhpWord::class) && is_file(__DIR__ . '/PHPWord-1.4.0/vendor/autoload.php')) {
    require_once __DIR__ . '/PHPWord-1.4.0/vendor/autoload.php';
}

if (!isset($pdo) && isset($db) && $db instanceof PDO) {
    $pdo = $db;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('SET NAMES utf8mb4');

function exp_ai_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $st->execute([$table]);
    return $cache[$table] = (int) $st->fetchColumn() > 0;
}

function exp_ai_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([$table, $column]);
    return $cache[$key] = (int) $st->fetchColumn() > 0;
}

function exp_ai_fetch_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function exp_ai_fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function exp_ai_rows_by_accidente(PDO $pdo, string $table, int $accidenteId, string $order = 'id ASC'): array
{
    if (!exp_ai_table_exists($pdo, $table) || !exp_ai_column_exists($pdo, $table, 'accidente_id')) {
        return [];
    }

    $sql = "SELECT * FROM `{$table}` WHERE accidente_id = ? ORDER BY {$order}";
    return exp_ai_fetch_all($pdo, $sql, [$accidenteId]);
}

function exp_ai_person(PDO $pdo, ?int $personaId): ?array
{
    if (!$personaId || !exp_ai_table_exists($pdo, 'personas')) {
        return null;
    }

    return exp_ai_fetch_one($pdo, 'SELECT * FROM personas WHERE id = ? LIMIT 1', [$personaId]);
}

function exp_ai_person_name(?array $person): string
{
    if (!$person) {
        return '';
    }

    $name = trim((string) ($person['nombres'] ?? '') . ' ' . (string) ($person['apellido_paterno'] ?? '') . ' ' . (string) ($person['apellido_materno'] ?? ''));
    return preg_replace('/\s+/', ' ', $name) ?: '';
}

function exp_ai_person_label(?array $person): string
{
    if (!$person) {
        return 'Sin persona registrada';
    }

    $name = exp_ai_person_name($person);
    $doc = trim((string) ($person['num_doc'] ?? ''));
    return trim($name . ($doc !== '' ? ' - ' . (string) ($person['tipo_doc'] ?? 'Doc') . ' ' . $doc : ''));
}

function exp_ai_quality(?string $lesion): string
{
    $text = mb_strtolower(trim((string) $lesion), 'UTF-8');
    if ($text === '') {
        return 'Sin calidad registrada';
    }
    if (str_contains($text, 'falle')) {
        return 'Fallecido';
    }
    if (str_contains($text, 'herid') || str_contains($text, 'lesion')) {
        return 'Herido';
    }
    if (str_contains($text, 'iles')) {
        return 'Ileso';
    }
    return (string) $lesion;
}

function exp_ai_safe_filename(string $value, string $fallback): string
{
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($value));
    $value = trim((string) $value, '._-');
    return $value !== '' ? $value : $fallback;
}

function exp_ai_tmp_dir(): string
{
    $candidates = [
        __DIR__ . '/tmp',
        sys_get_temp_dir(),
    ];

    foreach ($candidates as $dir) {
        if ($dir === '') {
            continue;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }

    return sys_get_temp_dir();
}

function exp_ai_clean($value)
{
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = exp_ai_clean($item);
        }
        return $out;
    }
    if ($value === null) {
        return null;
    }
    if (is_string($value)) {
        return trim(preg_replace('/[ \t]+/', ' ', str_replace("\r\n", "\n", $value)) ?? $value);
    }
    return $value;
}

function exp_ai_add_line(array &$lines, string $label, $value): void
{
    $text = trim((string) ($value ?? ''));
    if ($text !== '') {
        $lines[] = $label . ': ' . $text;
    }
}

function exp_ai_word_text($value): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
    $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
    return $text;
}

function exp_ai_word_join(array $items): string
{
    $items = array_values(array_filter(array_map('exp_ai_word_text', $items), static fn(string $item): bool => $item !== ''));
    return $items !== [] ? implode(' - ', $items) : '-';
}

function exp_ai_word_add_kv($section, string $label, $value): void
{
    $text = exp_ai_word_text($value);
    if ($text === '') {
        $text = '-';
    }

    $run = $section->addTextRun(['spaceAfter' => 80]);
    $run->addText($label . ': ', ['bold' => true]);
    $run->addText($text);
}

function exp_ai_word_add_table($section, array $rows): void
{
    $rows = array_values(array_filter($rows, static function (array $row): bool {
        return exp_ai_word_text($row[1] ?? '') !== '';
    }));
    if ($rows === []) {
        $section->addText('Sin datos registrados.', ['italic' => true, 'color' => '666666']);
        return;
    }

    $table = $section->addTable([
        'borderSize' => 4,
        'borderColor' => 'D9E2EC',
        'cellMargin' => 90,
    ]);
    foreach ($rows as $row) {
        $table->addRow();
        $table->addCell(3600, ['bgColor' => 'F3F6FA'])->addText(exp_ai_word_text($row[0] ?? ''), ['bold' => true]);
        $table->addCell(6200)->addText(exp_ai_word_text($row[1] ?? ''));
    }
}

function exp_ai_word_add_record_list($section, string $emptyText, array $rows, array $fields): void
{
    if ($rows === []) {
        $section->addText($emptyText, ['italic' => true, 'color' => '666666']);
        return;
    }

    foreach ($rows as $index => $row) {
        $section->addText('Registro ' . ($index + 1), ['bold' => true]);
        $items = [];
        foreach ($fields as $label => $key) {
            $items[] = [$label, $row[$key] ?? ''];
        }
        exp_ai_word_add_table($section, $items);
    }
}

function exp_ai_download_word(array $export, string $baseFilename): void
{
    if (!class_exists(\PhpOffice\PhpWord\PhpWord::class) || !class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
        http_response_code(500);
        exit('PhpWord no esta disponible para generar el Word.');
    }

    $tmpDir = exp_ai_tmp_dir();

    \PhpOffice\PhpWord\Settings::setTempDir($tmpDir);
    \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);

    $word = new \PhpOffice\PhpWord\PhpWord();
    $word->setDefaultFontName('Calibri');
    $word->setDefaultFontSize(10);
    $word->addTitleStyle(1, ['bold' => true, 'size' => 16, 'color' => '1F2937'], ['spaceAfter' => 180]);
    $word->addTitleStyle(2, ['bold' => true, 'size' => 13, 'color' => '1F2937'], ['spaceBefore' => 260, 'spaceAfter' => 120]);
    $word->addTitleStyle(3, ['bold' => true, 'size' => 11, 'color' => '374151'], ['spaceBefore' => 180, 'spaceAfter' => 80]);

    $section = $word->addSection([
        'marginTop' => 850,
        'marginBottom' => 850,
        'marginLeft' => 850,
        'marginRight' => 850,
    ]);

    $acc = $export['accidente'] ?? [];
    $personas = $export['personas_involucradas'] ?? [];
    $vehiculos = $export['vehiculos_involucrados'] ?? [];
    $docs = $export['documentos_realizados_y_recibidos'] ?? [];
    $policia = $export['policia']['intervinientes'] ?? [];
    $itps = $export['itp_completo'] ?? [];

    $section->addTitle('Resumen integral del accidente', 1);
    exp_ai_word_add_table($section, [
        ['Accidente ID', $acc['id'] ?? ''],
        ['SIDPOL', $acc['registro_sidpol'] ?? ($acc['sidpol'] ?? '')],
        ['Fecha accidente', $acc['fecha_accidente'] ?? ''],
        ['Lugar', $acc['lugar'] ?? ''],
        ['Referencia', $acc['referencia'] ?? ''],
        ['Comisaria', $acc['comisaria_nombre'] ?? ''],
        ['Informe policial', $acc['nro_informe_policial'] ?? ''],
        ['Estado', $acc['estado'] ?? ''],
    ]);

    $section->addTitle('Conteo documental', 2);
    exp_ai_word_add_table($section, [
        ['Personas involucradas', (string) count($personas)],
        ['Vehiculos involucrados', (string) count($vehiculos)],
        ['ITP registrados', (string) count($itps)],
        ['Oficios', (string) count($docs['oficios'] ?? [])],
        ['Documentos recibidos', (string) count($docs['documentos_recibidos'] ?? [])],
        ['Actas', (string) (count($docs['actas_entrega_vehiculo'] ?? []) + count($docs['actas_visualizacion'] ?? []))],
        ['Licencias de conducir', (string) count($docs['licencias_conducir'] ?? [])],
        ['RML', (string) count($docs['rml'] ?? [])],
        ['Manifestaciones', (string) count($docs['manifestaciones'] ?? [])],
        ['Dosajes', (string) count($docs['dosajes'] ?? [])],
        ['Documentos de occiso', (string) count($docs['occisos'] ?? [])],
        ['Documentos vehiculares', (string) count($docs['documentos_vehiculares'] ?? [])],
    ]);

    $section->addTitle('Personas involucradas', 2);
    if ($personas === []) {
        $section->addText('No hay personas involucradas registradas.', ['italic' => true, 'color' => '666666']);
    }
    foreach ($personas as $entry) {
        $person = $entry['persona'] ?? [];
        $invol = $entry['involucrado'] ?? [];
        $docPersona = $entry['documentos_personales'] ?? [];

        $section->addTitle(exp_ai_word_text($entry['nombre_completo'] ?? 'Persona involucrada'), 3);
        exp_ai_word_add_table($section, [
            ['Rol', $entry['rol'] ?? ''],
            ['Calidad', $entry['calidad'] ?? ''],
            ['Documento', exp_ai_word_join([$person['tipo_doc'] ?? '', $person['num_doc'] ?? ''])],
            ['Edad', $person['edad'] ?? ''],
            ['Domicilio', $person['domicilio'] ?? ''],
            ['Celular', $person['celular'] ?? ''],
            ['Observaciones', $invol['observaciones'] ?? ''],
            ['Vehiculo vinculado ID', $invol['vehiculo_id'] ?? ''],
        ]);

        $section->addText('Documentos de la persona', ['bold' => true]);
        exp_ai_word_add_table($section, [
            ['Licencias de conducir', (string) count($docPersona['licencias_conducir'] ?? [])],
            ['RML', (string) count($docPersona['rml'] ?? [])],
            ['Manifestaciones', (string) count($docPersona['manifestaciones'] ?? [])],
            ['Dosajes', (string) count($docPersona['dosajes'] ?? [])],
            ['Actas relacionadas', (string) count($docPersona['actas_relacionadas'] ?? [])],
            ['Documentos occiso', (string) count($docPersona['documentos_occiso'] ?? [])],
        ]);

        exp_ai_word_add_record_list($section, 'Sin licencia de conducir registrada.', $docPersona['licencias_conducir'] ?? [], [
            'Clase' => 'clase',
            'Categoria' => 'categoria',
            'Numero' => 'numero',
            'Expedido por' => 'expedido_por',
            'Vigente desde' => 'vigente_desde',
            'Vigente hasta' => 'vigente_hasta',
            'Restricciones' => 'restricciones',
        ]);
        exp_ai_word_add_record_list($section, 'Sin RML registrado.', $docPersona['rml'] ?? [], [
            'Numero' => 'numero',
            'Fecha' => 'fecha',
            'Incapacidad medico legal' => 'incapacidad_medico',
            'Atencion facultativa' => 'atencion_facultativo',
            'Observaciones' => 'observaciones',
        ]);
        exp_ai_word_add_record_list($section, 'Sin dosaje registrado.', $docPersona['dosajes'] ?? [], [
            'Numero' => 'numero',
            'Registro' => 'numero_registro',
            'Fecha extraccion' => 'fecha_extraccion',
            'Resultado cualitativo' => 'resultado_cualitativo',
            'Resultado cuantitativo' => 'resultado_cuantitativo',
            'Observaciones' => 'observaciones',
        ]);
        exp_ai_word_add_record_list($section, 'Sin manifestaciones registradas.', $docPersona['manifestaciones'] ?? [], [
            'Fecha' => 'fecha',
            'Hora inicio' => 'horario_inicio',
            'Hora termino' => 'hora_termino',
            'Modalidad' => 'modalidad',
        ]);

        foreach ($docPersona['documentos_occiso_detallados'] ?? [] as $occisoIndex => $occiso) {
            $section->addText('Documento de occiso ' . ($occisoIndex + 1), ['bold' => true]);
            exp_ai_word_add_table($section, [
                ['Acta levantamiento', json_encode($occiso['acta_levantamiento'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ['Informe pericial', json_encode($occiso['informe_pericial_recepcion_cadaver'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ['Protocolo necropsia', json_encode($occiso['protocolo_necropsia'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ['Epicrisis', json_encode($occiso['epicrisis'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ]);
        }

        if (!empty($entry['familiares_si_fallecido'])) {
            $section->addText('Familiares de fallecido', ['bold' => true]);
            foreach ($entry['familiares_si_fallecido'] as $fam) {
                exp_ai_word_add_table($section, [
                    ['Familiar', exp_ai_word_join([$fam['nombres'] ?? '', $fam['apellido_paterno'] ?? '', $fam['apellido_materno'] ?? ''])],
                    ['Documento', exp_ai_word_join([$fam['tipo_doc'] ?? '', $fam['num_doc'] ?? ''])],
                    ['Parentesco', $fam['parentesco'] ?? ''],
                    ['Domicilio', $fam['domicilio'] ?? ''],
                    ['Celular', $fam['celular'] ?? ''],
                    ['Observaciones', $fam['observaciones'] ?? ''],
                ]);
            }
        }
    }

    $section->addTitle('Vehiculos involucrados', 2);
    if ($vehiculos === []) {
        $section->addText('No hay vehiculos involucrados registrados.', ['italic' => true, 'color' => '666666']);
    }
    foreach ($vehiculos as $entry) {
        $vehicle = $entry['involucrado_vehiculo'] ?? [];
        $section->addTitle(exp_ai_word_join([$vehicle['orden_participacion'] ?? '', 'Placa ' . ($vehicle['placa'] ?? '')]), 3);
        exp_ai_word_add_table($section, [
            ['Tipo participacion', $vehicle['tipo'] ?? ''],
            ['Placa', $vehicle['placa'] ?? ''],
            ['Marca / modelo', exp_ai_word_join([$vehicle['marca'] ?? '', $vehicle['modelo'] ?? ''])],
            ['Tipo vehiculo', $vehicle['tipo_vehiculo'] ?? ''],
            ['Categoria', $vehicle['categoria_vehiculo'] ?? ''],
            ['Carroceria', $vehicle['carroceria'] ?? ''],
            ['Anio', $vehicle['anio'] ?? ''],
            ['Color', $vehicle['color'] ?? ''],
            ['VIN / Serie', $vehicle['serie_vin'] ?? ''],
            ['Motor', $vehicle['nro_motor'] ?? ''],
            ['Observaciones', $vehicle['observaciones'] ?? ''],
        ]);

        $section->addText('Personas vinculadas', ['bold' => true]);
        foreach ($entry['personas_vinculadas'] ?? [] as $personEntry) {
            $section->addText(exp_ai_word_join([
                $personEntry['nombre_completo'] ?? '',
                $personEntry['rol'] ?? '',
                $personEntry['calidad'] ?? '',
            ]));
        }

        $section->addText('Propietarios', ['bold' => true]);
        if (empty($entry['propietarios'])) {
            $section->addText('Sin propietario registrado.', ['italic' => true, 'color' => '666666']);
        }
        foreach ($entry['propietarios'] ?? [] as $owner) {
            exp_ai_word_add_table($section, [
                ['Tipo propietario', $owner['tipo_propietario'] ?? ''],
                ['Propietario', exp_ai_word_join([$owner['propietario_nombres'] ?? '', $owner['propietario_apellido_paterno'] ?? '', $owner['propietario_apellido_materno'] ?? '', $owner['razon_social'] ?? ''])],
                ['Documento / RUC', exp_ai_word_join([$owner['propietario_tipo_doc'] ?? '', $owner['propietario_num_doc'] ?? '', $owner['ruc'] ?? ''])],
                ['Representante', exp_ai_word_join([$owner['representante_nombres'] ?? '', $owner['representante_apellido_paterno'] ?? '', $owner['representante_apellido_materno'] ?? ''])],
                ['Rol legal', $owner['rol_legal'] ?? ''],
                ['Observaciones', $owner['observaciones'] ?? ''],
            ]);
        }

        $section->addText('Documentos vehiculares', ['bold' => true]);
        foreach ($entry['documentos_vehiculo_detallados'] ?? [] as $docVehIndex => $docVeh) {
            $section->addText('Documento vehicular ' . ($docVehIndex + 1), ['bold' => true]);
            exp_ai_word_add_table($section, [
                ['Tarjeta de propiedad', json_encode($docVeh['tarjeta_propiedad'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ['SOAT', json_encode($docVeh['soat'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ['Inspeccion tecnica vehicular', json_encode($docVeh['certificado_inspeccion_tecnica_vehicular'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ['Peritaje', json_encode($docVeh['peritaje'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ]);
        }
        if (empty($entry['documentos_vehiculo_detallados'])) {
            $section->addText('Sin documentos vehiculares registrados.', ['italic' => true, 'color' => '666666']);
        }
    }

    $section->addTitle('ITP completo', 2);
    exp_ai_word_add_record_list($section, 'Sin ITP registrado.', $itps, [
        'Fecha ITP' => 'fecha_itp',
        'Hora ITP' => 'hora_itp',
        'Forma via' => 'forma_via',
        'Punto referencia' => 'punto_referencia',
        'Ubicacion GPS' => 'ubicacion_gps',
        'Ocurrencia policial' => 'ocurrencia_policial',
        'Llegada al lugar' => 'llegada_lugar',
        'Localizacion unidades' => 'localizacion_unidades',
        'Via 1 descripcion' => 'descripcion_via1',
        'Via 1 configuracion' => 'configuracion_via1',
        'Via 1 material' => 'material_via1',
        'Via 1 señalizacion' => 'señalizacion_via1',
        'Via 1 ordenamiento' => 'ordenamiento_via1',
        'Via 1 iluminacion' => 'iluminacion_via1',
        'Via 1 visibilidad' => 'visibilidad_via1',
        'Via 1 intensidad' => 'intensidad_via1',
        'Via 1 fluidez' => 'fluidez_via1',
        'Via 1 medidas' => 'medidas_via1',
        'Via 1 observaciones' => 'observaciones_via1',
        'Via 2 descripcion' => 'descripcion_via2',
        'Via 2 configuracion' => 'configuracion_via2',
        'Via 2 material' => 'material_via2',
        'Via 2 señalizacion' => 'señalizacion_via2',
        'Via 2 ordenamiento' => 'ordenamiento_via2',
        'Via 2 iluminacion' => 'iluminacion_via2',
        'Via 2 visibilidad' => 'visibilidad_via2',
        'Via 2 intensidad' => 'intensidad_via2',
        'Via 2 fluidez' => 'fluidez_via2',
        'Via 2 medidas' => 'medidas_via2',
        'Via 2 observaciones' => 'observaciones_via2',
        'Evidencia biologica' => 'evidencia_biologica',
        'Evidencia fisica' => 'evidencia_fisica',
        'Evidencia material' => 'evidencia_material',
    ]);

    $section->addTitle('Policia interviniente', 2);
    exp_ai_word_add_record_list($section, 'Sin policia interviniente registrada.', $policia, [
        'Grado' => 'grado_policial',
        'CIP' => 'cip',
        'Dependencia' => 'dependencia_policial',
        'Funcion' => 'rol_funcion',
        'Observaciones' => 'observaciones',
        'DNI' => 'num_doc',
        'Nombres' => 'nombres',
    ]);

    $section->addTitle('Oficios y documentos', 2);
    exp_ai_word_add_record_list($section, 'Sin oficios registrados.', $docs['oficios'] ?? [], [
        'Numero' => 'numero',
        'Anio' => 'anio',
        'Fecha emision' => 'fecha_emision',
        'Entidad destino' => 'entidad_destino',
        'Asunto' => 'asunto_nombre',
        'Motivo' => 'motivo',
        'Estado' => 'estado',
    ]);
    exp_ai_word_add_record_list($section, 'Sin documentos recibidos registrados.', $docs['documentos_recibidos'] ?? [], [
        'Tipo documento' => 'tipo_documento',
        'Numero documento' => 'numero_documento',
        'Entidad/persona' => 'entidad_persona',
        'Asunto' => 'asunto',
        'Contenido' => 'contenido',
        'Estado' => 'estado',
    ]);

    $fileName = $baseFilename . '_resumen.docx';
    $filePath = $tmpDir . '/' . $fileName;
    \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save($filePath);

    while (ob_get_level()) {
        @ob_end_clean();
    }
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    @unlink($filePath);
    exit;
}

function exp_ai_select_catalog_name(PDO $pdo, string $table, string $idColumn, string $nameColumn, $id): string
{
    if (!$id || !exp_ai_table_exists($pdo, $table)) {
        return '';
    }
    if (!exp_ai_column_exists($pdo, $table, $idColumn) || !exp_ai_column_exists($pdo, $table, $nameColumn)) {
        return '';
    }
    $row = exp_ai_fetch_one($pdo, "SELECT `{$nameColumn}` AS nombre FROM `{$table}` WHERE `{$idColumn}` = ? LIMIT 1", [(int) $id]);
    return (string) ($row['nombre'] ?? '');
}

function exp_ai_pick(array $row, array $keys): array
{
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = $row[$key] ?? null;
    }
    return $out;
}

function exp_ai_rows_by_person(PDO $pdo, string $table, int $personaId, string $order = 'id DESC'): array
{
    if ($personaId <= 0 || !exp_ai_table_exists($pdo, $table) || !exp_ai_column_exists($pdo, $table, 'persona_id')) {
        return [];
    }

    $sql = "SELECT * FROM `{$table}` WHERE persona_id = ? ORDER BY {$order}";
    return exp_ai_fetch_all($pdo, $sql, [$personaId]);
}

function exp_ai_rows_by_accidente_person(PDO $pdo, string $table, int $accidenteId, int $personaId, string $order = 'id DESC'): array
{
    if ($personaId <= 0 || !exp_ai_table_exists($pdo, $table) || !exp_ai_column_exists($pdo, $table, 'persona_id')) {
        return [];
    }

    if (exp_ai_column_exists($pdo, $table, 'accidente_id')) {
        $sql = "SELECT * FROM `{$table}` WHERE accidente_id = ? AND persona_id = ? ORDER BY {$order}";
        return exp_ai_fetch_all($pdo, $sql, [$accidenteId, $personaId]);
    }

    return exp_ai_rows_by_person($pdo, $table, $personaId, $order);
}

function exp_ai_rows_by_person_ids(PDO $pdo, string $table, array $personIds, string $order = 'id DESC'): array
{
    $personIds = array_values(array_unique(array_filter(array_map('intval', $personIds))));
    if ($personIds === [] || !exp_ai_table_exists($pdo, $table) || !exp_ai_column_exists($pdo, $table, 'persona_id')) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($personIds), '?'));
    $sql = "SELECT * FROM `{$table}` WHERE persona_id IN ({$placeholders}) ORDER BY {$order}";
    return exp_ai_fetch_all($pdo, $sql, $personIds);
}

function exp_ai_licencias_conducir(PDO $pdo, int $personaId): array
{
    foreach (['documento_lc', 'doc_lc', 'documento_licencia', 'licencia_conducir'] as $table) {
        $rows = exp_ai_rows_by_person($pdo, $table, $personaId, 'id DESC');
        if ($rows !== []) {
            return $rows;
        }
    }

    return [];
}

function exp_ai_licencias_conducir_personas(PDO $pdo, array $personIds): array
{
    foreach (['documento_lc', 'doc_lc', 'documento_licencia', 'licencia_conducir'] as $table) {
        $rows = exp_ai_rows_by_person_ids($pdo, $table, $personIds, 'id DESC');
        if ($rows !== []) {
            return $rows;
        }
    }

    return [];
}

function exp_ai_actas_persona(PDO $pdo, int $accidenteId, int $involucradoPersonaId): array
{
    if (!exp_ai_table_exists($pdo, 'actas')) {
        return [];
    }

    if ($involucradoPersonaId > 0 && exp_ai_column_exists($pdo, 'actas', 'conductor_involucrado_persona_id')) {
        return exp_ai_fetch_all(
            $pdo,
            'SELECT * FROM actas WHERE accidente_id = ? AND conductor_involucrado_persona_id = ? ORDER BY id DESC',
            [$accidenteId, $involucradoPersonaId]
        );
    }

    return [];
}

function exp_ai_occiso_detallado(array $row): array
{
    return [
        'registro_completo' => $row,
        'acta_levantamiento' => exp_ai_pick($row, [
            'fecha_levantamiento', 'hora_levantamiento', 'lugar_levantamiento',
            'posicion_cuerpo_levantamiento', 'lesiones_levantamiento',
            'presuntivo_levantamiento', 'legista_levantamiento', 'cmp_legista',
            'observaciones_levantamiento',
        ]),
        'informe_pericial_recepcion_cadaver' => exp_ai_pick($row, [
            'numero_pericial', 'fecha_pericial', 'hora_pericial', 'observaciones_pericial',
        ]),
        'protocolo_necropsia' => exp_ai_pick($row, [
            'numero_protocolo', 'fecha_protocolo', 'hora_protocolo',
            'lesiones_protocolo', 'presuntivo_protocolo', 'dosaje_protocolo',
            'toxicologico_protocolo',
        ]),
        'epicrisis' => exp_ai_pick($row, [
            'nosocomio_epicrisis', 'numero_historia_epicrisis',
            'tratamiento_epicrisis', 'hora_alta_epicrisis',
        ]),
    ];
}

function exp_ai_documentos_persona(PDO $pdo, int $accidenteId, array $involucradoRow): array
{
    $personaId = (int) ($involucradoRow['persona_id'] ?? 0);
    $involucradoPersonaId = (int) ($involucradoRow['id'] ?? 0);
    $occisos = exp_ai_rows_by_accidente_person($pdo, 'documento_occiso', $accidenteId, $personaId, 'id DESC');

    return [
        'licencias_conducir' => exp_ai_licencias_conducir($pdo, $personaId),
        'rml' => exp_ai_rows_by_accidente_person($pdo, 'documento_rml', $accidenteId, $personaId, 'fecha DESC, id DESC'),
        'manifestaciones' => exp_ai_table_exists($pdo, 'Manifestacion')
            ? exp_ai_rows_by_accidente_person($pdo, 'Manifestacion', $accidenteId, $personaId, 'fecha DESC, horario_inicio DESC, id DESC')
            : [],
        'dosajes' => exp_ai_rows_by_accidente_person($pdo, 'documento_dosaje', $accidenteId, $personaId, 'fecha_extraccion DESC, id DESC'),
        'actas_relacionadas' => exp_ai_actas_persona($pdo, $accidenteId, $involucradoPersonaId),
        'documentos_occiso' => $occisos,
        'documentos_occiso_detallados' => array_map('exp_ai_occiso_detallado', $occisos),
    ];
}

function exp_ai_documento_vehiculo_detallado(array $row): array
{
    return [
        'registro_completo' => $row,
        'tarjeta_propiedad' => exp_ai_pick($row, [
            'numero_propiedad', 'titulo_propiedad', 'partida_propiedad', 'sede_propiedad',
        ]),
        'soat' => exp_ai_pick($row, [
            'numero_soat', 'aseguradora_soat', 'vigente_soat', 'vencimiento_soat',
        ]),
        'certificado_inspeccion_tecnica_vehicular' => exp_ai_pick($row, [
            'numero_revision', 'certificadora_revision', 'vigente_revision', 'vencimiento_revision',
        ]),
        'peritaje' => exp_ai_pick($row, [
            'numero_peritaje', 'fecha_peritaje', 'perito_peritaje',
            'sistema_electrico_peritaje', 'sistema_frenos_peritaje',
            'sistema_direccion_peritaje', 'sistema_transmision_peritaje',
            'sistema_suspension_peritaje', 'planta_motriz_peritaje',
            'otros_peritaje', 'danos_peritaje',
        ]),
    ];
}

function exp_ai_actas_vehiculo(PDO $pdo, int $accidenteId, int $involucradoVehiculoId): array
{
    if (!exp_ai_table_exists($pdo, 'actas') || !exp_ai_column_exists($pdo, 'actas', 'involucrado_vehiculo_id')) {
        return [];
    }

    return exp_ai_fetch_all(
        $pdo,
        'SELECT * FROM actas WHERE accidente_id = ? AND involucrado_vehiculo_id = ? ORDER BY id DESC',
        [$accidenteId, $involucradoVehiculoId]
    );
}

$accidenteId = (int) ($_GET['accidente_id'] ?? $_GET['id'] ?? 0);
if ($accidenteId <= 0) {
    http_response_code(400);
    exit('Falta accidente_id.');
}

$accidente = exp_ai_fetch_one($pdo, "
    SELECT a.*,
           c.nombre AS comisaria_nombre
      FROM accidentes a
 LEFT JOIN comisarias c ON c.id = a.comisaria_id
     WHERE a.id = ?
     LIMIT 1
", [$accidenteId]);

if (!$accidente) {
    http_response_code(404);
    exit('No se encontro el accidente.');
}

$personRows = exp_ai_fetch_all($pdo, "
    SELECT ip.*, pp.Nombre AS rol_nombre, pp.Orden AS rol_orden
      FROM involucrados_personas ip
 LEFT JOIN participacion_persona pp ON pp.Id = ip.rol_id
     WHERE ip.accidente_id = ?
  ORDER BY COALESCE(pp.Orden, 999), ip.orden_persona, ip.id
", [$accidenteId]);

$peopleByVehicle = [];
$personas = [];
$personIds = [];
foreach ($personRows as $row) {
    $person = exp_ai_person($pdo, (int) ($row['persona_id'] ?? 0));
    $personIds[] = (int) ($row['persona_id'] ?? 0);
    $abogados = exp_ai_fetch_all($pdo, 'SELECT * FROM abogados WHERE accidente_id = ? AND persona_id = ? ORDER BY id', [$accidenteId, (int) ($row['persona_id'] ?? 0)]);
    $familiares = [];
    if (str_contains(mb_strtolower((string) ($row['lesion'] ?? ''), 'UTF-8'), 'falle')) {
        $familiares = exp_ai_fetch_all($pdo, "
            SELECT ff.*, p.tipo_doc, p.num_doc, p.apellido_paterno, p.apellido_materno, p.nombres, p.domicilio, p.celular, p.email
              FROM familiar_fallecido ff
              JOIN personas p ON p.id = ff.familiar_persona_id
             WHERE ff.accidente_id = ? AND ff.fallecido_inv_id = ?
          ORDER BY ff.id
        ", [$accidenteId, (int) ($row['id'] ?? 0)]);
    }

    $documentosPersona = exp_ai_documentos_persona($pdo, $accidenteId, $row);
    $entry = [
        'involucrado' => $row,
        'persona' => $person,
        'nombre_completo' => exp_ai_person_label($person),
        'rol' => (string) ($row['rol_nombre'] ?? ''),
        'calidad' => exp_ai_quality($row['lesion'] ?? ''),
        'abogados' => $abogados,
        'familiares_si_fallecido' => $familiares,
        'documentos_personales' => $documentosPersona,
        'nota_documental' => [
            'es_conductor' => str_contains(mb_strtolower((string) ($row['rol_nombre'] ?? ''), 'UTF-8'), 'conductor'),
            'licencias_conducir' => count($documentosPersona['licencias_conducir']),
            'rml' => count($documentosPersona['rml']),
            'manifestaciones' => count($documentosPersona['manifestaciones']),
            'dosajes' => count($documentosPersona['dosajes']),
            'actas_relacionadas' => count($documentosPersona['actas_relacionadas']),
            'documentos_occiso' => count($documentosPersona['documentos_occiso']),
        ],
    ];

    $personas[] = $entry;
    $vehiculoId = (int) ($row['vehiculo_id'] ?? 0);
    if ($vehiculoId > 0) {
        $peopleByVehicle[$vehiculoId][] = $entry;
    }
}
$personIds = array_values(array_unique(array_filter($personIds)));

$vehicleRows = exp_ai_fetch_all($pdo, "
    SELECT iv.*,
           v.placa, v.serie_vin, v.nro_motor, v.anio, v.color, v.largo_mm, v.ancho_mm, v.alto_mm, v.notas,
           tv.nombre AS tipo_vehiculo,
           cv.descripcion AS categoria_vehiculo,
           car.nombre AS carroceria,
           mar.nombre AS marca,
           modv.nombre AS modelo
      FROM involucrados_vehiculos iv
      JOIN vehiculos v ON v.id = iv.vehiculo_id
 LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
 LEFT JOIN categoria_vehiculos cv ON cv.id = v.categoria_id
 LEFT JOIN carroceria_vehiculo car ON car.id = v.carroceria_id
 LEFT JOIN marcas_vehiculo mar ON mar.id = v.marca_id
 LEFT JOIN modelos_vehiculo modv ON modv.id = v.modelo_id
     WHERE iv.accidente_id = ?
  ORDER BY FIELD(iv.orden_participacion,'UT-1','UT-2','UT-3','UT-4','UT-5','UT-6','UT-7'), iv.id
", [$accidenteId]);

$vehiculos = [];
foreach ($vehicleRows as $vehicle) {
    $owners = exp_ai_fetch_all($pdo, "
        SELECT pv.*,
               pn.tipo_doc AS propietario_tipo_doc, pn.num_doc AS propietario_num_doc, pn.apellido_paterno AS propietario_apellido_paterno, pn.apellido_materno AS propietario_apellido_materno, pn.nombres AS propietario_nombres, pn.domicilio AS propietario_domicilio, pn.celular AS propietario_celular, pn.email AS propietario_email,
               pr.tipo_doc AS representante_tipo_doc, pr.num_doc AS representante_num_doc, pr.apellido_paterno AS representante_apellido_paterno, pr.apellido_materno AS representante_apellido_materno, pr.nombres AS representante_nombres, pr.domicilio AS representante_domicilio, pr.celular AS representante_celular, pr.email AS representante_email
          FROM propietario_vehiculo pv
     LEFT JOIN personas pn ON pn.id = pv.propietario_persona_id
     LEFT JOIN personas pr ON pr.id = pv.representante_persona_id
         WHERE pv.accidente_id = ? AND pv.vehiculo_inv_id = ?
      ORDER BY pv.id
    ", [$accidenteId, (int) ($vehicle['id'] ?? 0)]);

    $vehiclePeople = $peopleByVehicle[(int) ($vehicle['vehiculo_id'] ?? 0)] ?? [];
    $conductores = array_values(array_filter($vehiclePeople, static function (array $entry): bool {
        return str_contains(mb_strtolower((string) ($entry['rol'] ?? ''), 'UTF-8'), 'conductor');
    }));

    $documentosVehiculo = exp_ai_table_exists($pdo, 'documento_vehiculo')
        ? exp_ai_fetch_all($pdo, 'SELECT * FROM documento_vehiculo WHERE involucrado_vehiculo_id = ? OR vehiculo_id = ? ORDER BY id', [(int) ($vehicle['id'] ?? 0), (int) ($vehicle['vehiculo_id'] ?? 0)])
        : [];

    $vehiculos[] = [
        'involucrado_vehiculo' => $vehicle,
        'personas_vinculadas' => $vehiclePeople,
        'conductores' => $conductores,
        'propietarios' => $owners,
        'documentos_vehiculo' => $documentosVehiculo,
        'documentos_vehiculo_detallados' => array_map('exp_ai_documento_vehiculo_detallado', $documentosVehiculo),
        'actas_relacionadas' => exp_ai_actas_vehiculo($pdo, $accidenteId, (int) ($vehicle['id'] ?? 0)),
        'nota_para_gpt' => $conductores !== [] && $owners !== []
            ? 'Existe conductor vinculado al vehiculo y se incluye informacion de propietario.'
            : 'Revisar si falta conductor o propietario registrado para este vehiculo.',
    ];
}

$modalidades = exp_ai_fetch_all($pdo, "
    SELECT am.*, m.nombre AS modalidad_nombre
     FROM accidente_modalidad am
 LEFT JOIN modalidad_accidente m ON m.id = am.modalidad_id
     WHERE am.accidente_id = ?
  ORDER BY am.modalidad_id
", [$accidenteId]);

$consecuencias = exp_ai_fetch_all($pdo, "
    SELECT ac.*, c.nombre AS consecuencia_nombre
      FROM accidente_consecuencia ac
 LEFT JOIN consecuencia_accidente c ON c.id = ac.consecuencia_id
     WHERE ac.accidente_id = ?
  ORDER BY ac.consecuencia_id
", [$accidenteId]);

$oficios = exp_ai_fetch_all($pdo, "
    SELECT o.*,
           oe.nombre AS entidad_destino, os.nombre AS subentidad_destino, oa.nombre AS asunto_nombre,
           gc.nombre AS grado_cargo_nombre
      FROM oficios o
 LEFT JOIN oficio_entidad oe ON oe.id = o.entidad_id_destino
 LEFT JOIN oficio_subentidad os ON os.id = o.subentidad_destino_id
 LEFT JOIN oficio_asunto oa ON oa.id = o.asunto_id
 LEFT JOIN grado_cargo gc ON gc.id = o.grado_cargo_id
     WHERE o.accidente_id = ?
  ORDER BY o.anio DESC, o.numero DESC, o.id DESC
", [$accidenteId]);

$documentos = [
    'oficios' => $oficios,
    'documentos_recibidos' => exp_ai_rows_by_accidente($pdo, 'documentos_recibidos', $accidenteId, 'id DESC'),
    'actas_entrega_vehiculo' => exp_ai_rows_by_accidente($pdo, 'actas', $accidenteId, 'id DESC'),
    'actas_visualizacion' => exp_ai_rows_by_accidente($pdo, 'actas_visualizacion', $accidenteId, 'id DESC'),
    'citaciones' => exp_ai_rows_by_accidente($pdo, 'citacion', $accidenteId, 'id DESC'),
    'diligencias_pendientes' => exp_ai_rows_by_accidente($pdo, 'diligencias_pendientes', $accidenteId, 'id DESC'),
    'licencias_conducir' => exp_ai_licencias_conducir_personas($pdo, $personIds),
    'rml' => exp_ai_rows_by_accidente($pdo, 'documento_rml', $accidenteId, 'id DESC'),
    'dosajes' => exp_ai_rows_by_person_ids($pdo, 'documento_dosaje', $personIds, 'fecha_extraccion DESC, id DESC'),
    'occisos' => exp_ai_rows_by_accidente($pdo, 'documento_occiso', $accidenteId, 'id DESC'),
    'occisos_detallados' => array_map('exp_ai_occiso_detallado', exp_ai_rows_by_accidente($pdo, 'documento_occiso', $accidenteId, 'id DESC')),
    'manifestaciones' => exp_ai_table_exists($pdo, 'Manifestacion') ? exp_ai_rows_by_accidente($pdo, 'Manifestacion', $accidenteId, 'id DESC') : [],
    'documentos_vehiculares' => exp_ai_table_exists($pdo, 'documento_vehiculo')
        ? exp_ai_fetch_all($pdo, "
            SELECT dv.*, iv.accidente_id, iv.orden_participacion, v.placa
              FROM documento_vehiculo dv
         LEFT JOIN involucrados_vehiculos iv ON iv.id = dv.involucrado_vehiculo_id
         LEFT JOIN vehiculos v ON v.id = COALESCE(dv.vehiculo_id, iv.vehiculo_id)
             WHERE iv.accidente_id = ?
          ORDER BY iv.orden_participacion, dv.id
        ", [$accidenteId])
        : [],
];
$documentos['documentos_vehiculares_detallados'] = array_map('exp_ai_documento_vehiculo_detallado', $documentos['documentos_vehiculares']);

$policia = [
    'intervinientes' => exp_ai_fetch_all($pdo, "
        SELECT pi.*, p.tipo_doc, p.num_doc, p.apellido_paterno, p.apellido_materno, p.nombres, p.domicilio, p.celular, p.email
          FROM policial_interviniente pi
     LEFT JOIN personas p ON p.id = pi.persona_id
         WHERE pi.accidente_id = ?
      ORDER BY pi.id
    ", [$accidenteId]),
    'efectivos_intervinientes' => exp_ai_rows_by_accidente($pdo, 'efectivos_intervinientes', $accidenteId),
    'efectivos_policiales' => exp_ai_rows_by_accidente($pdo, 'efectivos_policiales', $accidenteId),
];

$export = exp_ai_clean([
    'tipo_exportacion' => 'paquete_accidente_para_gpt',
    'generado_en' => date('c'),
    'uso_sugerido' => 'Subir accidente.json y contexto_para_gpt.txt a un Proyecto o GPT para trabajar con toda la informacion del accidente.',
    'accidente' => $accidente,
    'modalidades' => $modalidades,
    'consecuencias' => $consecuencias,
    'personas_involucradas' => $personas,
    'personas_sin_vehiculo' => array_values(array_filter($personas, static fn(array $entry): bool => (int) ($entry['involucrado']['vehiculo_id'] ?? 0) <= 0)),
    'vehiculos_involucrados' => $vehiculos,
    'familiares_fallecidos' => exp_ai_rows_by_accidente($pdo, 'familiar_fallecido', $accidenteId),
    'propietarios_vehiculo' => exp_ai_rows_by_accidente($pdo, 'propietario_vehiculo', $accidenteId),
    'abogados' => exp_ai_rows_by_accidente($pdo, 'abogados', $accidenteId),
    'policia' => $policia,
    'itp_completo' => exp_ai_rows_by_accidente($pdo, 'itp', $accidenteId, 'id DESC'),
    'documentos_realizados_y_recibidos' => $documentos,
]);

$lines = [];
$lines[] = 'PAQUETE DE CONTEXTO PARA GPT';
$lines[] = 'Accidente ID: ' . $accidenteId;
exp_ai_add_line($lines, 'SIDPOL', $accidente['registro_sidpol'] ?? ($accidente['sidpol'] ?? ''));
exp_ai_add_line($lines, 'Fecha accidente', $accidente['fecha_accidente'] ?? '');
exp_ai_add_line($lines, 'Lugar', $accidente['lugar'] ?? '');
exp_ai_add_line($lines, 'Referencia', $accidente['referencia'] ?? '');
exp_ai_add_line($lines, 'Comisaria', $accidente['comisaria_nombre'] ?? '');
exp_ai_add_line($lines, 'Informe policial', $accidente['nro_informe_policial'] ?? '');
$lines[] = '';
$lines[] = 'RESUMEN PARA USO EN GPT';
$lines[] = '- Personas involucradas: ' . count($personas);
$lines[] = '- Vehiculos involucrados: ' . count($vehiculos);
$lines[] = '- ITP registrados: ' . count($export['itp_completo']);
$lines[] = '- Oficios: ' . count($oficios);
$lines[] = '- Documentos recibidos: ' . count($documentos['documentos_recibidos']);
$lines[] = '- Actas: ' . (count($documentos['actas_entrega_vehiculo']) + count($documentos['actas_visualizacion']));
$lines[] = '- Licencias de conducir: ' . count($documentos['licencias_conducir']);
$lines[] = '- RML: ' . count($documentos['rml']);
$lines[] = '- Manifestaciones: ' . count($documentos['manifestaciones']);
$lines[] = '- Dosajes: ' . count($documentos['dosajes']);
$lines[] = '- Documentos de occiso: ' . count($documentos['occisos']);
$lines[] = '- Documentos vehiculares: ' . count($documentos['documentos_vehiculares']);
$lines[] = '';
$lines[] = 'PERSONAS';
foreach ($personas as $entry) {
    $lines[] = '- ' . $entry['nombre_completo'] . ' | Rol: ' . ($entry['rol'] ?: '-') . ' | Calidad: ' . $entry['calidad'];
    $docs = $entry['documentos_personales'];
    $lines[] = '  Documentos: licencias=' . count($docs['licencias_conducir'])
        . ', RML=' . count($docs['rml'])
        . ', manifestaciones=' . count($docs['manifestaciones'])
        . ', dosajes=' . count($docs['dosajes'])
        . ', actas=' . count($docs['actas_relacionadas'])
        . ', occiso=' . count($docs['documentos_occiso']);
    if (!empty($entry['familiares_si_fallecido'])) {
        foreach ($entry['familiares_si_fallecido'] as $fam) {
            $famName = trim((string) ($fam['nombres'] ?? '') . ' ' . (string) ($fam['ap_fam'] ?? $fam['apellido_paterno'] ?? '') . ' ' . (string) ($fam['am_fam'] ?? $fam['apellido_materno'] ?? ''));
            $lines[] = '  Familiar fallecido: ' . trim($famName) . ' | Parentesco: ' . (string) ($fam['parentesco'] ?? '');
        }
    }
}
$lines[] = '';
$lines[] = 'VEHICULOS';
foreach ($vehiculos as $entry) {
    $v = $entry['involucrado_vehiculo'];
    $lines[] = '- ' . (string) ($v['orden_participacion'] ?? '') . ' | Placa: ' . (string) ($v['placa'] ?? '') . ' | ' . trim((string) ($v['marca'] ?? '') . ' ' . (string) ($v['modelo'] ?? '')) . ' | Color: ' . (string) ($v['color'] ?? '');
    $lines[] = '  Documentos vehiculo: registros=' . count($entry['documentos_vehiculo'])
        . ', actas=' . count($entry['actas_relacionadas'])
        . ' | Incluye tarjeta de propiedad, SOAT, inspeccion tecnica y peritaje cuando estan registrados.';
    foreach ($entry['personas_vinculadas'] as $personEntry) {
        $lines[] = '  Persona vinculada: ' . $personEntry['nombre_completo'] . ' | Rol: ' . ($personEntry['rol'] ?: '-') . ' | Calidad: ' . $personEntry['calidad'];
    }
    foreach ($entry['propietarios'] as $owner) {
        $ownerName = trim((string) ($owner['propietario_nombres'] ?? '') . ' ' . (string) ($owner['propietario_apellido_paterno'] ?? '') . ' ' . (string) ($owner['propietario_apellido_materno'] ?? ''));
        $lines[] = '  Propietario: ' . ($ownerName !== '' ? $ownerName : (string) ($owner['razon_social'] ?? '')) . ' | Tipo: ' . (string) ($owner['tipo_propietario'] ?? '');
    }
}
$lines[] = '';
$lines[] = 'DOCUMENTOS';
$lines[] = '- Oficios, documentos recibidos, citaciones, actas, ITP, RML, dosajes, manifestaciones y documentos de occiso estan detallados en accidente.json.';
$contextText = implode("\n", $lines) . "\n";

$json = json_encode($export, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if (!is_string($json)) {
    http_response_code(500);
    exit('No se pudo generar JSON.');
}

$sidpol = exp_ai_safe_filename((string) ($accidente['registro_sidpol'] ?? $accidente['sidpol'] ?? ''), 'accidente_' . $accidenteId);
$baseFilename = 'paquete_ia_' . $sidpol . '_' . date('Ymd_His');
$format = mb_strtolower(trim((string) ($_GET['format'] ?? $_GET['tipo'] ?? 'zip')), 'UTF-8');

if (in_array($format, ['word', 'docx', 'resumen_word'], true)) {
    exp_ai_download_word($export, $baseFilename);
}

if (!class_exists('ZipArchive')) {
    while (ob_get_level()) {
        @ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $baseFilename . '.json"');
    echo $json;
    exit;
}

$tmpDir = exp_ai_tmp_dir();
$zipTmp = tempnam($tmpDir, 'paquete_ia_');
if ($zipTmp === false) {
    http_response_code(500);
    exit('No se pudo crear archivo temporal para el ZIP.');
}
$zipPath = $zipTmp . '.zip';
@rename($zipTmp, $zipPath);
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($zipTmp);
    @unlink($zipPath);
    http_response_code(500);
    exit('No se pudo crear el ZIP.');
}

$zip->addFromString('accidente.json', $json);
$zip->addFromString('contexto_para_gpt.txt', $contextText);

$candidateFiles = [];
foreach ($oficios as $oficio) {
    foreach (['archivo_pdf'] as $fileColumn) {
        $file = trim((string) ($oficio[$fileColumn] ?? ''));
        if ($file !== '') {
            $candidateFiles[] = $file;
        }
    }
}
foreach ($documentos['documentos_recibidos'] as $doc) {
    foreach (['archivo', 'archivo_path', 'ruta_archivo', 'path'] as $fileColumn) {
        $file = trim((string) ($doc[$fileColumn] ?? ''));
        if ($file !== '') {
            $candidateFiles[] = $file;
        }
    }
}
foreach (glob(__DIR__ . '/documentos_generados/*_' . $accidenteId . '_*') ?: [] as $generatedPath) {
    if (is_file($generatedPath)) {
        $candidateFiles[] = 'documentos_generados/' . basename($generatedPath);
    }
}

$added = [];
foreach ($candidateFiles as $file) {
    $file = ltrim(str_replace(['\\', "\0"], ['/', ''], $file), '/');
    $path = __DIR__ . '/' . $file;
    if (!is_file($path) || isset($added[$path])) {
        continue;
    }
    $added[$path] = true;
    $zip->addFile($path, 'archivos/' . basename($path));
}

$closed = $zip->close();
if (!$closed || !is_file($zipPath) || filesize($zipPath) <= 0) {
    @unlink($zipPath);
    http_response_code(500);
    exit('No se pudo finalizar el ZIP del paquete IA.');
}

while (ob_get_level()) {
    @ob_end_clean();
}
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $baseFilename . '.zip"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
@unlink($zipPath);
exit;
