<?php
declare(strict_types=1);

function word_manifestation_text($value, string $fallback = '-'): string
{
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? $value : $fallback;
}

function word_manifestation_name(array $row): string
{
    return word_manifestation_text(trim(
        (string) ($row['nombres'] ?? '') . ' ' .
        (string) ($row['apellido_paterno'] ?? '') . ' ' .
        (string) ($row['apellido_materno'] ?? '')
    ));
}

function word_manifestation_date($value): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '-';
    }
    $ts = strtotime($value);
    if (!$ts) {
        return $value;
    }
    static $months = [
        1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN',
        7 => 'JUL', 8 => 'AGO', 9 => 'SET', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC',
    ];
    return date('d', $ts) . $months[(int) date('n', $ts)] . date('Y', $ts);
}

function word_manifestation_time($value): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '-';
    }
    $ts = strtotime($value);
    return $ts ? date('H:i', $ts) : $value;
}

function word_manifestation_register_person(array &$people, array $row, string $role): void
{
    $personaId = (int) ($row['persona_id'] ?? 0);
    if ($personaId <= 0) {
        return;
    }

    if (!isset($people[$personaId])) {
        $people[$personaId] = [
            'persona_id' => $personaId,
            'nombre' => word_manifestation_name($row),
            'documento' => trim(word_manifestation_text($row['tipo_doc'] ?? '', '') . ' ' . word_manifestation_text($row['num_doc'] ?? '', '')),
            'roles' => [],
        ];
    }

    $role = word_manifestation_text($role, '');
    if ($role !== '' && !in_array($role, $people[$personaId]['roles'], true)) {
        $people[$personaId]['roles'][] = $role;
    }
}

function word_manifestation_fetch_all(PDO $pdo, string $sql, array $params): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function word_manifestation_load_people(PDO $pdo, int $accidenteId): array
{
    $people = [];

    try {
        $rows = word_manifestation_fetch_all($pdo, "
            SELECT ip.persona_id,
                   COALESCE(pp.Nombre, 'Involucrado') AS rol,
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.tipo_doc, p.num_doc
              FROM involucrados_personas ip
              JOIN personas p ON p.id = ip.persona_id
         LEFT JOIN participacion_persona pp ON pp.Id = ip.rol_id
             WHERE ip.accidente_id = :a
             ORDER BY ip.id ASC
        ", [':a' => $accidenteId]);
        foreach ($rows as $row) {
            word_manifestation_register_person($people, $row, (string) ($row['rol'] ?? 'Involucrado'));
        }
    } catch (Throwable $e) {
        // No interrumpe la generacion del Word si alguna tabla auxiliar no existe.
    }

    try {
        $rows = word_manifestation_fetch_all($pdo, "
            SELECT pv.propietario_persona_id AS persona_id,
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.tipo_doc, p.num_doc
              FROM propietario_vehiculo pv
              JOIN personas p ON p.id = pv.propietario_persona_id
             WHERE pv.accidente_id = :a
               AND pv.propietario_persona_id IS NOT NULL
               AND pv.propietario_persona_id > 0
             ORDER BY pv.id ASC
        ", [':a' => $accidenteId]);
        foreach ($rows as $row) {
            word_manifestation_register_person($people, $row, 'Propietario');
        }
    } catch (Throwable $e) {
    }

    try {
        $rows = word_manifestation_fetch_all($pdo, "
            SELECT pv.representante_persona_id AS persona_id,
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.tipo_doc, p.num_doc
              FROM propietario_vehiculo pv
              JOIN personas p ON p.id = pv.representante_persona_id
             WHERE pv.accidente_id = :a
               AND pv.representante_persona_id IS NOT NULL
               AND pv.representante_persona_id > 0
             ORDER BY pv.id ASC
        ", [':a' => $accidenteId]);
        foreach ($rows as $row) {
            word_manifestation_register_person($people, $row, 'Representante legal');
        }
    } catch (Throwable $e) {
    }

    try {
        $rows = word_manifestation_fetch_all($pdo, "
            SELECT ff.familiar_persona_id AS persona_id,
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.tipo_doc, p.num_doc
              FROM familiar_fallecido ff
              JOIN personas p ON p.id = ff.familiar_persona_id
             WHERE ff.accidente_id = :a
               AND ff.familiar_persona_id IS NOT NULL
               AND ff.familiar_persona_id > 0
             ORDER BY ff.id ASC
        ", [':a' => $accidenteId]);
        foreach ($rows as $row) {
            word_manifestation_register_person($people, $row, 'Familiar');
        }
    } catch (Throwable $e) {
    }

    try {
        $rows = word_manifestation_fetch_all($pdo, "
            SELECT pi.persona_id,
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.tipo_doc, p.num_doc
              FROM policial_interviniente pi
              JOIN personas p ON p.id = pi.persona_id
             WHERE pi.accidente_id = :a
             ORDER BY pi.id ASC
        ", [':a' => $accidenteId]);
        foreach ($rows as $row) {
            word_manifestation_register_person($people, $row, 'Efectivo policial');
        }
    } catch (Throwable $e) {
    }

    return array_values($people);
}

function word_manifestation_load_records(PDO $pdo, int $accidenteId, int $personaId): array
{
    if ($personaId <= 0) {
        return [];
    }

    try {
        return word_manifestation_fetch_all($pdo, "
            SELECT id, accidente_id, persona_id, fecha, horario_inicio, hora_termino, modalidad
              FROM Manifestacion
             WHERE accidente_id = :a
               AND persona_id = :p
             ORDER BY fecha DESC, horario_inicio DESC, id DESC
        ", [':a' => $accidenteId, ':p' => $personaId]);
    } catch (Throwable $e) {
        return [];
    }
}

function word_manifestation_record_summary(array $record): string
{
    return 'Fecha: ' . word_manifestation_date($record['fecha'] ?? '') .
        ' | Inicio: ' . word_manifestation_time($record['horario_inicio'] ?? '') .
        ' | Termino: ' . word_manifestation_time($record['hora_termino'] ?? '') .
        ' | Modalidad: ' . word_manifestation_text($record['modalidad'] ?? '');
}

function word_manifestation_first(PDO $pdo, int $accidenteId, ?int $personaId): array
{
    return word_manifestation_load_records($pdo, $accidenteId, (int) ($personaId ?? 0))[0] ?? [];
}

function word_manifestation_values(array $manifestacion): array
{
    return [
        'fecha' => word_manifestation_date($manifestacion['fecha'] ?? ''),
        'hora_inicio' => word_manifestation_time($manifestacion['horario_inicio'] ?? ''),
        'hora_termino' => word_manifestation_time($manifestacion['hora_termino'] ?? ''),
        'modalidad' => word_manifestation_text($manifestacion['modalidad'] ?? '', ''),
        'resumen' => $manifestacion !== [] ? word_manifestation_record_summary($manifestacion) : '',
    ];
}

function word_manifestation_set_array(array &$markers, array $prefixes, string $base, array $manifestacion): void
{
    foreach (word_manifestation_values($manifestacion) as $suffix => $value) {
        foreach ($prefixes as $prefix) {
            $markers[$prefix . $base . '_' . $suffix] = word_manifestation_text($value, '');
        }
    }
}

function word_manifestation_set_template($template, string $base, array $manifestacion): void
{
    foreach (word_manifestation_values($manifestacion) as $suffix => $value) {
        $template->setValue($base . '_' . $suffix, word_manifestation_text($value, ''));
    }
}

function word_manifestation_owner_person_id(array $owner): int
{
    $tipo = strtoupper(trim((string) ($owner['tipo_propietario'] ?? '')));
    $propietarioId = (int) ($owner['propietario_persona_id'] ?? $owner['persona_id'] ?? 0);
    $representanteId = (int) ($owner['representante_persona_id'] ?? 0);
    if ($tipo === 'JURIDICA' && $representanteId > 0) {
        return $representanteId;
    }
    return $propietarioId > 0 ? $propietarioId : $representanteId;
}

function word_manifestation_fill_global_array(array &$markers, PDO $pdo, int $accidenteId, int $limit = 5): void
{
    $policias = [];
    try {
        $policias = word_manifestation_fetch_all($pdo, "
            SELECT pi.persona_id
              FROM policial_interviniente pi
             WHERE pi.accidente_id = :a
             ORDER BY pi.id ASC
             LIMIT {$limit}
        ", [':a' => $accidenteId]);
    } catch (Throwable $e) {
    }

    for ($i = 1; $i <= $limit; $i++) {
        $manifestacion = isset($policias[$i - 1])
            ? word_manifestation_first($pdo, $accidenteId, (int) ($policias[$i - 1]['persona_id'] ?? 0))
            : [];
        word_manifestation_set_array($markers, ['efec' . $i . '_', 'policia' . $i . '_'], 'man', $manifestacion);
    }

    $testigos = [];
    try {
        $testigos = word_manifestation_fetch_all($pdo, "
            SELECT ip.persona_id
              FROM involucrados_personas ip
         LEFT JOIN participacion_persona pp ON pp.Id = ip.rol_id
             WHERE ip.accidente_id = :a
               AND LOWER(COALESCE(pp.Nombre, '')) LIKE '%testig%'
             ORDER BY ip.id ASC
             LIMIT {$limit}
        ", [':a' => $accidenteId]);
    } catch (Throwable $e) {
    }

    for ($i = 1; $i <= $limit; $i++) {
        $manifestacion = isset($testigos[$i - 1])
            ? word_manifestation_first($pdo, $accidenteId, (int) ($testigos[$i - 1]['persona_id'] ?? 0))
            : [];
        word_manifestation_set_array($markers, ['testigo' . $i . '_'], 'man', $manifestacion);
    }
}

function word_manifestation_fill_global_template($template, PDO $pdo, int $accidenteId, int $limit = 5): void
{
    $markers = [];
    word_manifestation_fill_global_array($markers, $pdo, $accidenteId, $limit);
    foreach ($markers as $key => $value) {
        $template->setValue($key, $value);
    }
}
