<?php
// Shared embedded view for the supporting participants of an expediente.
$listConfigs = [
    'familiar' => ['Familiares de fallecidos', 'Agregar familiar', 'familiar_fallecido', '👥'],
    'propietario' => ['Propietarios de vehículos', 'Agregar propietario', 'propietario_vehiculo', '🔑'],
    'abogado' => ['Abogados', 'Agregar abogado', 'abogado', '⚖️'],
    'policia' => ['Efectivos policiales', 'Agregar efectivo', 'policial_interviniente', '👮'],
];
[$listTitle, $addLabel, $routePrefix, $listIcon] = $listConfigs[$participantListKind];
$joinName = static fn(array $parts): string => trim(implode(' ', array_filter($parts, static fn($part) => trim((string) $part) !== '')));
$returnQuery = urlencode($returnTo);
?>
<header class="people-heading">
  <div><h1><?= h($listTitle) ?> <span><?= count($rows) ?></span></h1><p>Identificación y participación en el expediente.</p></div>
  <a class="btn primary" href="<?= h($routePrefix) ?>_nuevo.php?accidente_id=<?= (int) $accidenteId ?>">＋ <?= h($addLabel) ?></a>
</header>
<?php if ($ok !== ''): ?><div class="ok" role="status"><?= h(['created' => 'Registro creado correctamente.', 'updated' => 'Registro actualizado correctamente.', 'deleted' => 'Registro eliminado correctamente.'][$ok] ?? 'Operación realizada correctamente.') ?></div><?php endif; ?>
<?php if ($err !== ''): ?><div class="err" role="alert"><?= h($err) ?></div><?php endif; ?>
<div class="people-list" role="list">
<?php foreach ($rows as $row):
    $id = (int) $row['id'];
    $chips = [];
    $meta = [];
    $extraActions = [];
    $viewUrl = $routePrefix . '_leer.php?id=' . $id . '&return_to=' . $returnQuery;
    $editUrl = $routePrefix . '_editar.php?id=' . $id . '&return_to=' . $returnQuery;
    $deleteUrl = $routePrefix . '_eliminar.php?id=' . $id . '&return_to=' . $returnQuery;
    switch ($participantListKind) {
        case 'familiar':
            $name = $joinName([$row['no_fam'] ?? '', $row['ap_fam'] ?? '', $row['am_fam'] ?? '']);
            $chips[] = $row['parentesco'] ?? '';
            $meta = [
                'Documento: ' . $joinName([$row['tipo_doc_fam'] ?? '', $row['dni_fam'] ?? '']),
                'Familiar de: ' . $joinName([$row['no_fall'] ?? '', $row['ap_fall'] ?? '', $row['am_fall'] ?? '']),
                $row['cel_fam'] ?? '', $row['em_fam'] ?? '',
            ];
            $extraActions[] = ['📄', 'Manifestación', 'marcador_manifestacion_familiar.php?fam_id=' . $id];
            break;
        case 'propietario':
            $natural = ($row['tipo_propietario'] ?? '') === 'NATURAL';
            $name = $natural ? $joinName([$row['no_nat'] ?? '', $row['ap_nat'] ?? '', $row['am_nat'] ?? '']) : trim((string) ($row['razon_social'] ?? ''));
            $chips[] = $row['tipo_propietario'] ?? '';
            $meta = [
                $natural ? 'Documento: ' . $joinName([$row['tipo_doc_nat'] ?? '', $row['dni_nat'] ?? '']) : 'RUC: ' . ($row['ruc'] ?? ''),
                $joinName([$row['orden_participacion'] ?? '', $row['placa'] ?? '']),
            ];
            if (!$natural) {
                $representative = $joinName([$row['no_rep'] ?? '', $row['ap_rep'] ?? '', $row['am_rep'] ?? '']);
                if ($representative !== '') $meta[] = 'Representante: ' . $representative;
                $meta[] = $row['rol_legal'] ?? '';
            }
            break;
        case 'abogado':
            $name = $joinName([$row['nombres'] ?? '', $row['apellido_paterno'] ?? '', $row['apellido_materno'] ?? '']);
            $chips[] = !empty($row['es_actual']) ? 'Actual' : 'Anterior';
            $chips[] = $row['condicion_representado'] ?? '';
            $meta = ['Colegiatura: ' . ($row['colegiatura'] ?? ''), 'Representa a: ' . ($row['persona_rep_nom'] ?? ''), $row['celular'] ?? '', $row['email'] ?? ''];
            $viewUrl = 'abogado_ver.php?id=' . $id . '&return=' . $returnQuery;
            $editUrl = 'abogado_editar.php?id=' . $id . '&return=' . $returnQuery;
            $extraActions[] = ['📄', 'Notificación', 'marcador_abogado.php?abogado_id=' . $id . '&return_to=' . $returnQuery];
            break;
        case 'policia':
            $name = $joinName([$row['nombres'] ?? '', $row['apellido_paterno'] ?? '', $row['apellido_materno'] ?? '']);
            $chips[] = $row['grado_policial'] ?? '';
            $chips[] = $row['rol_funcion'] ?? '';
            $meta = [
                'Documento: ' . $joinName([$row['tipo_doc'] ?? '', $row['num_doc'] ?? '']),
                'CIP: ' . ($row['cip'] ?? ''), $row['dependencia_policial'] ?? '', $row['celular'] ?? '', $row['email'] ?? '',
            ];
            $extraActions[] = ['📄', 'Descargar manifestación', 'marcador_manifestacion_policia.php?policia_id=' . $id . '&accidente_id=' . (int) ($row['accidente_id'] ?? $accidenteId) . '&download=1'];
            break;
    }
    $name = $name !== '' ? $name : 'Nombre no registrado';
    $meta = array_values(array_filter($meta, static fn($value) => trim((string) $value) !== ''));
    $chips = array_values(array_filter($chips, static fn($value) => trim((string) $value) !== ''));
?>
  <article class="people-item" role="listitem">
    <div class="people-identity"><span class="people-symbol" aria-hidden="true"><?= h($listIcon) ?></span><div>
      <div class="people-name-line"><h2 title="<?= h($name) ?>"><?= h($name) ?></h2><?php foreach ($chips as $chip): ?><span class="people-condition" title="<?= h($chip) ?>"><?= h($chip) ?></span><?php endforeach; ?></div>
      <p class="people-meta" title="<?= h(implode(' · ', $meta)) ?>"><?php foreach ($meta as $value): ?><span><?= h($value) ?></span><?php endforeach; ?></p>
    </div></div>
    <div class="people-actions">
      <a class="btn people-icon-action" href="<?= h($viewUrl) ?>" title="Ver detalles" aria-label="Ver detalles de <?= h($name) ?>">👁️</a>
      <?php foreach ($extraActions as [$icon, $label, $url]): ?><a class="btn people-icon-action" href="<?= h($url) ?>" title="<?= h($label) ?>" aria-label="<?= h($label . ' de ' . $name) ?>"><?= h($icon) ?></a><?php endforeach; ?>
      <a class="btn people-icon-action" data-participant-edit href="<?= h($editUrl) ?>" title="Editar" aria-label="Editar a <?= h($name) ?>">✏️</a>
      <a class="btn people-icon-action people-remove" href="<?= h($deleteUrl) ?>" title="Eliminar" aria-label="Eliminar a <?= h($name) ?>">X</a>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php if (!$rows): ?><div class="people-empty"><strong>No hay registros en este listado.</strong><p>Usa «<?= h($addLabel) ?>» para agregar el primero.</p></div><?php endif; ?>
