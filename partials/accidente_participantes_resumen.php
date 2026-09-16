<?php
(static function () use ($summaryUnits, $personas, $propietarios, $policias, $familiares, $abogados, $accidente_id, $manifestacionPersonaOptions) {
    $lastRoleGroup = '';
    $relativesShown = []; $lawyersShown = [];
    $renderPerson = null;
    $renderPerson = static function (array $person, string $role, string $target, string $name = '') use (&$lastRoleGroup, &$renderPerson, $familiares, $abogados, $propietarios, &$relativesShown, &$lawyersShown): void {
        $roleKey = mb_strtolower($role);
        $roleGroup = match (true) {
            str_contains($roleKey, 'conductor') => 'Conductor',
            str_contains($roleKey, 'ocupante') => 'Ocupantes',
            str_contains($roleKey, 'pasajero') => 'Pasajeros',
            str_contains($roleKey, 'propietario') => 'Propietario',
            str_contains($roleKey, 'peat') => 'Peatones',
            str_contains($roleKey, 'policial') => 'Efectivos policiales',
            str_contains($roleKey, 'familiar') => 'Familiares',
            str_contains($roleKey, 'abogado') => 'Abogados',
            default => $role,
        };
        $roleTone = match ($roleGroup) {
            'Conductor' => 'blue', 'Ocupantes', 'Pasajeros' => 'teal',
            'Propietario' => 'amber', 'Familiares' => 'violet',
            'Abogados' => 'indigo', 'Efectivos policiales' => 'green',
            default => 'blue',
        };
        $roleIcon = match ($roleGroup) {
            'Conductor' => '🚘',
            'Ocupantes', 'Pasajeros' => '💺',
            'Propietario' => '🔑',
            'Peatones' => '🚶',
            'Efectivos policiales' => '👮',
            'Familiares' => '👥',
            'Abogados' => '⚖️',
            default => '👤',
        };
        if ($lastRoleGroup !== $roleGroup) {
            echo '<h4 class="overview-role-group" data-tone="' . h($roleTone) . '">' . h($roleGroup) . ':</h4>';
            $lastRoleGroup = $roleGroup;
        }
        $related = [];
        $representedId = 0;
        if (str_starts_with($target, 'persona-')) {
            $representedId = (int) ($person['persona_id'] ?? 0);
            foreach ($familiares as $relative) {
                if ((int) ($relative['fallecido_inv_id'] ?? 0) !== (int) ($person['involucrado_id'] ?? 0)) continue;
                $relativesShown[(int) $relative['id']] = true;
                $related[] = [project_prefixed_record($relative, 'fam_'), 'Familiar · ' . ($relative['parentesco'] ?? ''), 'familiar-registro-' . (int) $relative['id']];
            }
        } elseif (str_starts_with($target, 'propietario-registro-')) {
            foreach ($propietarios as $owner) if ($target === 'propietario-registro-' . (int) $owner['id']) $representedId = (int) ($owner['propietario_persona_id'] ?? 0);
        } elseif (str_starts_with($target, 'familiar-registro-')) {
            foreach ($familiares as $relative) if ($target === 'familiar-registro-' . (int) $relative['id']) $representedId = (int) ($relative['familiar_persona_id'] ?? 0);
        }
        if ($representedId > 0) foreach ($abogados as $lawyer) {
            if ((int) ($lawyer['persona_id'] ?? 0) !== $representedId) continue;
            $lawyersShown[(int) $lawyer['id']] = true;
            $related[] = [$lawyer, 'Abogado', 'sidebar-abogado-' . (int) $lawyer['id']];
        }
        if ($related) echo '<div class="overview-linked-row">';
        $phone = preg_replace('/\D+/', '', (string) ($person['celular'] ?? ''));
        if (strlen($phone) === 9) $phone = '51' . $phone;
        ?>
        <div class="participant-overview-person<?= needs_occ($person) ? ' is-fatal' : '' ?>" data-tone="<?= h($roleTone) ?>">
          <span class="overview-person-icon" aria-hidden="true"><?= needs_occ($person) ? '💀' : h($roleIcon) ?></span>
          <div class="overview-person-info">
            <?php if ($role !== $roleGroup && $roleGroup !== 'Conductor'): ?><div class="overview-person-role"><?= h($role) ?></div><?php endif; ?>
            <strong><?= h($name !== '' ? $name : person_label($person)) ?></strong>
            <p><?php if (!empty($person['lesion'])): ?><span class="overview-condition <?= h(lesion_chip_class((string) $person['lesion'])) ?>"><?= h($person['lesion']) ?></span> <?php endif; ?><span class="overview-person-meta"><?= h(person_heading_meta($person)) ?></span></p>
          </div>
          <div class="case-person-actions">
            <?php if ($phone !== ''): ?><a class="btn-shell" href="https://wa.me/<?= h($phone) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
            <button type="button" class="case-person-view js-sidebar-view-person" data-person-target="<?= h($target) ?>" data-vehicle-only="0">Ver persona ↗</button>
          </div>
        </div>
        <?php
        if ($related) {
            $savedGroup = $lastRoleGroup;
            echo '<aside class="overview-related-people" aria-label="Familiares y defensa vinculados">';
            foreach ($related as [$linkedPerson, $linkedRole, $linkedTarget]) {
                $lastRoleGroup = '';
                $renderPerson($linkedPerson, $linkedRole, $linkedTarget);
            }
            echo '</aside></div>';
            $lastRoleGroup = $savedGroup;
        }
    };
    $seen = []; $ownersSeen = [];
    ?>
    <section class="participants-overview" aria-label="Resumen de todos los participantes">
      <div class="overview-title-actions">
        <div class="case-new-actions">
          <button class="btn-shell btn-nuevo case-new-trigger case-command-button js-case-new-trigger" type="button" aria-expanded="false" aria-controls="case-new-menu">NUEVO</button>
          <div class="case-new-menu" id="case-new-menu" hidden>
            <a class="case-new-item" href="involucrados_personas_listar.php?accidente_id=<?= (int) $accidente_id ?>"><span class="case-new-icon" aria-hidden="true">👤</span><span>Persona involucrada<small>Registrar participante</small></span></a>
            <a class="case-new-item" href="involucrados_vehiculos_listar.php?accidente_id=<?= (int) $accidente_id ?>"><span class="case-new-icon" aria-hidden="true">🚘</span><span>Vehículo involucrado<small>Registrar unidad participante</small></span></a>
            <a class="case-new-item" href="familiar_fallecido_nuevo.php?accidente_id=<?= (int) $accidente_id ?>"><span class="case-new-icon" aria-hidden="true">👥</span><span>Familiar<small>Registrar familiar de fallecido</small></span></a>
            <a class="case-new-item" href="propietario_vehiculo_nuevo.php?accidente_id=<?= (int) $accidente_id ?>"><span class="case-new-icon" aria-hidden="true">🔑</span><span>Propietario<small>Registrar propietario de vehículo</small></span></a>
            <a class="case-new-item" href="abogado_nuevo.php?accidente_id=<?= (int) $accidente_id ?>"><span class="case-new-icon" aria-hidden="true">⚖️</span><span>Abogado<small>Registrar defensa legal</small></span></a>
            <a class="case-new-item" href="policial_interviniente_nuevo.php?accidente_id=<?= (int) $accidente_id ?>"><span class="case-new-icon" aria-hidden="true">👮</span><span>Efectivo policial<small>Registrar interviniente</small></span></a>
          </div>
        </div>
        <a class="btn-shell btn-citacion case-command-button" href="citacion_nuevo.php?accidente_id=<?= (int) $accidente_id ?>&return_to=<?= urlencode('accidente_vista_tabs.php?accidente_id=' . (int) $accidente_id . '&tab=participantes') ?>"><span class="case-command-icon" aria-hidden="true">📅</span>CITACIONES</a>
        <div class="case-manifest-actions">
          <button class="btn-shell case-manifest-trigger case-command-button js-case-manifest-trigger" type="button" aria-expanded="false" aria-controls="case-manifest-menu"><span class="case-command-icon" aria-hidden="true">📝</span>MANIFESTACIÓN</button>
          <div class="case-new-menu case-manifest-menu" id="case-manifest-menu" hidden>
            <?php if ($manifestacionPersonaOptions === []): ?>
              <div class="case-manifest-empty">No hay personas vinculadas al accidente para crear una manifestación.</div>
            <?php else: ?>
              <form class="case-manifest-form js-case-manifest-form" action="documento_manifestacion_nuevo.php" method="get">
                <label for="case-manifest-persona">Persona</label>
                <select id="case-manifest-persona" name="persona_id" required>
                  <option value="">Seleccionar persona...</option>
                  <?php foreach ($manifestacionPersonaOptions as $manifestacionPersona): ?>
                    <option value="<?= (int) $manifestacionPersona['persona_id'] ?>" data-rol-id="<?= (int) $manifestacionPersona['rol_id'] ?>"><?= h(manifestacion_persona_icono($manifestacionPersona['condicion']) . ' ' . $manifestacionPersona['nombre'] . ($manifestacionPersona['condicion'] !== '' ? ' — ' . $manifestacionPersona['condicion'] : '')) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="rol_id" value="" class="js-case-manifest-role">
                <input type="hidden" name="accidente_id" value="<?= (int) $accidente_id ?>">
                <input type="hidden" name="return_to" value="<?= h('accidente_vista_tabs.php?accidente_id=' . (int) $accidente_id . '&tab=participantes') ?>">
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php foreach ($summaryUnits as $unit): ?>
        <article class="participant-overview-unit">
          <header><h3><?= h($unit['ut']) ?></h3>
          <?php foreach ($unit['vehiculos'] as $vehicle): ?>
            <?php
            $vehiclePersonTarget = '';
            $vehiclePanelTarget = '';
            foreach ($unit['personas'] as $candidate) {
                if (!is_conductor($candidate) || empty($candidate['veh_id'])) continue;
                $combined = es_participacion_combinada($candidate['veh_participacion'] ?? null) && count($unit['vehiculos']) > 1;
                if (!$combined && (int) $candidate['veh_id'] !== (int) ($vehicle['veh_id'] ?? 0)) continue;
                $vehiclePersonTarget = 'persona-' . (int) $candidate['involucrado_id'];
                $vehiclePanelTarget = '#person-pane-' . (int) $candidate['involucrado_id'] . '-vehiculo';
                if ($combined) $vehiclePanelTarget .= '-' . (string) ($vehicle['veh_numero'] ?? '');
                break;
            }
            ?>
            <div class="case-person-actions"><strong>Vehículo de placa <?= h($vehicle['veh_placa'] ?: 'Sin placa registrada') ?></strong>
            <?php if ($vehiclePersonTarget !== ''): ?>
              <button type="button" class="case-person-view js-sidebar-view-person" data-person-target="<?= h($vehiclePersonTarget) ?>" data-vehicle-only="1" data-vehicle-panel="<?= h($vehiclePanelTarget) ?>">Ver vehículo ↗</button>
            <?php else: ?>
              <a class="case-person-view" href="vehiculo_editar.php?id=<?= (int) ($vehicle['veh_id'] ?? 0) ?>&return_to=<?= urlencode('accidente_vista_tabs.php?accidente_id=' . $accidente_id . '&tab=participantes') ?>">Ver vehículo ↗</a>
            <?php endif; ?></div>
          <?php endforeach; ?></header>
          <?php
          $lastRoleGroup = '';
          $unitPeople = $unit['personas'];
          usort($unitPeople, static fn(array $a, array $b): int => (is_conductor($b) <=> is_conductor($a)) ?: strcmp((string) ($a['orden_persona'] ?? ''), (string) ($b['orden_persona'] ?? '')));
          foreach ($unitPeople as $person) {
              $seen[(int) $person['involucrado_id']] = true;
              $role = (string) ($person['rol_nombre'] ?? 'Participante');
              if (is_pasajero_ocupante($person) && !empty($person['orden_persona'])) $role .= ' "' . $person['orden_persona'] . '"';
              $renderPerson($person, $role, 'persona-' . (int) $person['involucrado_id']);
          }
          foreach ($propietarios as $owner) {
              if (trim((string) ($owner['orden_participacion'] ?? '')) !== trim((string) $unit['ut'])) continue;
              $ownersSeen[(int) $owner['id']] = true;
              $renderPerson(project_prefixed_record($owner, 'owner_'), 'Propietario del vehículo · ' . ($owner['placa'] ?? ''), 'propietario-registro-' . (int) $owner['id'], (string) ($owner['razon_social'] ?? ''));
          } ?>
        </article>
      <?php endforeach; ?>
      <?php foreach ($personas as $person): if (isset($seen[(int) $person['involucrado_id']])) continue; ?>
        <article class="participant-overview-unit"><header><h3><?= h(trim((string) ($person['orden_participacion'] ?? '') . ' · ' . (string) ($person['rol_nombre'] ?? 'Participante'), ' ·')) ?></h3></header>
        <?php $renderPerson($person, (string) ($person['rol_nombre'] ?? 'Participante'), 'persona-' . (int) $person['involucrado_id']); ?></article>
      <?php endforeach; ?>
      <?php foreach ($propietarios as $owner) { if (isset($ownersSeen[(int) $owner['id']])) continue;
          $renderPerson(project_prefixed_record($owner, 'owner_'), 'Propietario del vehículo · ' . ($owner['placa'] ?? ''), 'propietario-registro-' . (int) $owner['id'], (string) ($owner['razon_social'] ?? ''));
      }
      if ($policias) echo '<section class="overview-police-group">';
      foreach ($policias as $person) $renderPerson($person, 'Efectivo policial', 'policia-registro-' . (int) $person['id']);
      if ($policias) echo '</section>';
      foreach ($familiares as $person) if (!isset($relativesShown[(int) $person['id']])) $renderPerson(project_prefixed_record($person, 'fam_'), 'Familiar · ' . ($person['parentesco'] ?? ''), 'familiar-registro-' . (int) $person['id']);
      foreach ($abogados as $person) if (!isset($lawyersShown[(int) $person['id']])) $renderPerson($person, 'Abogado', 'sidebar-abogado-' . (int) $person['id']);
      if (!$personas && !$summaryUnits && !$propietarios && !$policias && !$familiares && !$abogados) echo '<p>No hay participantes registrados.</p>';
      ?>
    </section>
<?php })(); ?>
