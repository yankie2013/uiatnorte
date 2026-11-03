<?php
use App\Support\WorkspacePage as SearchPage;

$searchValue = static fn(mixed $value, string $fallback): string => trim((string) $value) !== '' ? trim((string) $value) : $fallback;
$searchPlate = static function (mixed $value): string {
    $plate = trim((string) $value);
    return $plate === '' || preg_match('/^SPLACA/i', $plate) ? 'Sin placa registrada' : $plate;
};
?>
<section class="search-results" aria-label="Resultados de la búsqueda">
  <div class="search-results-heading"><h2>Expedientes encontrados</h2><span><?= count($rows) ?><?= $more ? '+' : '' ?> en esta página</span></div>
  <?php if (!$rows): ?><p class="search-empty">No hay expedientes para esta búsqueda.</p><?php endif; ?>
  <?php foreach ($rows as $row):
      $case = (int) $row['id'];
      $href = 'gestion_expedientes.php?id=' . $case;
      $participants = $resultDetails['participants'][$case] ?? [];
      $vehicles = $resultDetails['vehicles'][$case] ?? [];
      $date = trim((string) ($row['fecha_accidente'] ?? ''));
      $parsedDate = $date !== '' && !str_starts_with($date, '0000-00-00') ? date_create_immutable($date) : false;
  ?>
    <article class="search-case" aria-labelledby="search-case-<?= $case ?>">
      <div class="search-case-heading">
        <div><span class="search-field-label">SIDPOL</span><h3 id="search-case-<?= $case ?>"><a class="js-case-modal-trigger" data-case-id="<?= $case ?>" href="<?= SearchPage::escape($href) ?>"><?= SearchPage::escape($searchValue($row['registro_sidpol'] ?? '', 'Sin SIDPOL registrado')) ?></a></h3></div>
        <span class="search-case-status"><?= SearchPage::escape($searchValue($row['estado'] ?? '', 'Sin estado registrado')) ?></span>
        <a class="button js-case-modal-trigger" data-case-id="<?= $case ?>" href="<?= SearchPage::escape($href) ?>">Ver gestión →</a>
      </div>
      <dl class="search-case-facts">
        <div><dt>Fecha y hora</dt><dd><?= SearchPage::escape($parsedDate ? $parsedDate->format('d/m/Y H:i') : 'Sin fecha y hora registradas') ?></dd></div>
        <div><dt>Lugar</dt><dd><?= SearchPage::escape($searchValue($row['lugar'] ?? '', 'Sin lugar registrado')) ?></dd></div>
        <div><dt>Jurisdicción</dt><dd><?= SearchPage::escape($searchValue($row['jurisdiccion'] ?? '', 'Sin jurisdicción registrada')) ?></dd></div>
        <div><dt>Responsable del caso</dt><dd><?= SearchPage::escape(responsible_label($row['responsable'] ?? null, $row['responsable_grado'] ?? null)) ?></dd></div>
      </dl>
      <div class="search-participants-heading"><h4>Participantes</h4><span><?= count($participants) ?> registrado<?= count($participants) === 1 ? '' : 's' ?></span></div>
      <ul class="search-participants">
        <?php foreach ($participants as $person):
            $name = trim(implode(' ', array_filter([$person['nombres'] ?? '', $person['apellido_paterno'] ?? '', $person['apellido_materno'] ?? ''], static fn($part): bool => trim((string) $part) !== '')));
        ?>
          <li><span class="search-person-role"><?= SearchPage::escape($searchValue($person['rol'] ?? '', 'Rol no registrado')) ?></span><strong><?= SearchPage::escape($searchValue($name, 'Sin nombre registrado')) ?></strong>
            <?php foreach ($person['vehicles'] as $vehicle): ?><span class="search-person-vehicle"><?= SearchPage::escape(trim((string) ($vehicle['orden_participacion'] ?? ''))) ?> · <?= SearchPage::escape($searchPlate($vehicle['placa'] ?? '')) ?></span><?php endforeach; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (!$participants): ?><p class="search-empty">Sin participantes registrados.</p><?php endif; ?>
      <?php if ($vehicles): ?><div class="search-vehicles"><span class="search-field-label">Vehículos del expediente</span><?php foreach ($vehicles as $vehicle): ?><span class="search-vehicle-chip"><?= SearchPage::escape(trim((string) ($vehicle['orden_participacion'] ?? ''))) ?> · <?= SearchPage::escape($searchPlate($vehicle['placa'] ?? '')) ?></span><?php endforeach; ?></div><?php endif; ?>
    </article>
  <?php endforeach; ?>
  <nav class="actions" aria-label="Páginas de resultados">
    <?php $query = ['q' => $q, 'oficio' => $oficio, 'filtro' => $filter]; ?>
    <?php if ($page > 1): ?><a href="?<?= SearchPage::escape(http_build_query($query + ['pagina' => $page - 1])) ?>">← Anterior</a><?php endif; ?>
    <?php if ($more): ?><a href="?<?= SearchPage::escape(http_build_query($query + ['pagina' => $page + 1])) ?>">Siguiente →</a><?php endif; ?>
  </nav>
</section>
