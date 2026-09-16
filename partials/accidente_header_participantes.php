<?php
// Resumen de presentación: utiliza únicamente los registros ya cargados por la vista.
(static function (array $personas, array $policias, array $abogados, array $propietarios, array $familiares, array $messages): void {
    $groups = array_fill_keys(['Conductores', 'Ocupantes', 'Pasajeros', 'Peatones', 'Efectivos policiales', 'Propietarios de vehículo', 'Familiares de fallecidos', 'Abogados', 'Otros'], []);
    $vehicleIcon = static function (string $type): string {
        $type = str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], mb_strtolower($type, 'UTF-8'));
        return match (true) {
            str_contains($type, 'trimoto'), str_contains($type, 'mototaxi') => '🛺',
            str_contains($type, 'moto') => '🏍️',
            str_contains($type, 'bicicleta') => '🚲',
            str_contains($type, 'camioneta') => '🛻',
            str_contains($type, 'camion'), str_contains($type, 'remolc') => '🚚',
            str_contains($type, 'bus'), str_contains($type, 'omnibus') => '🚌',
            default => '🚘',
        };
    };
    foreach ($personas as $person) {
        $role = compact_text((string) ($person['rol_nombre'] ?? ''));
        $key = mb_strtolower($role, 'UTF-8');
        if (str_contains($key, 'propietario') || str_contains($key, 'familiar')) continue;
        $group = match (true) {
            str_contains($key, 'conductor') => 'Conductores',
            str_contains($key, 'ocupante') => 'Ocupantes',
            str_contains($key, 'pasajero') => 'Pasajeros',
            str_contains($key, 'peat') => 'Peatones',
            default => $role !== '' ? $role : 'Otros',
        };
        $vehicle = array_filter([
            compact_text((string) ($person['veh_tipo'] ?? '')),
            compact_text(trim((string) ($person['veh_marca'] ?? '') . ' ' . (string) ($person['veh_modelo'] ?? ''))),
            compact_text((string) ($person['veh_chip_text'] ?? ($person['veh_placa'] ?? ''))),
        ], static fn(string $value): bool => $value !== '');
        $groups[$group][] = [
            'icon' => $group === 'Conductores' ? $vehicleIcon((string) ($person['veh_tipo'] ?? '')) : ($group === 'Peatones' ? '🚶' : '👤'),
            'vehicle_icon' => $vehicle !== [] ? $vehicleIcon((string) ($person['veh_tipo'] ?? '')) : '',
            'vehicle_only' => is_conductor($person) && !empty($person['veh_id']),
            'target' => 'persona-' . (int) $person['involucrado_id'],
            'phone' => (string) ($person['celular'] ?? ''), 'name' => person_label($person),
            'meta' => implode(' · ', $vehicle),
            'fatal' => needs_occ($person),
        ];
    }
    foreach ($policias as $officer) {
        $groups['Efectivos policiales'][] = ['target' => 'policia-registro-' . (int) $officer['id'], 'icon' => '👮', 'vehicle_icon' => '', 'phone' => (string) ($officer['celular'] ?? ''), 'name' => person_label($officer), 'meta' => (string) ($officer['grado_policial'] ?? ''), 'fatal' => false];
    }
    foreach ($abogados as $lawyer) {
        $groups['Abogados'][] = ['target' => 'sidebar-abogado-' . (int) $lawyer['id'], 'icon' => '⚖️', 'vehicle_icon' => '', 'phone' => (string) ($lawyer['celular'] ?? ''), 'name' => person_label($lawyer), 'meta' => '', 'fatal' => false];
    }
    foreach ($propietarios as $owner) {
        $isCompany = mb_strtoupper(trim((string) ($owner['tipo_propietario'] ?? '')), 'UTF-8') === 'JURIDICA';
        $name = $isCompany ? (string) ($owner['razon_social'] ?? '') : person_label(project_prefixed_record($owner, 'owner_'));
        $plate = vehiculo_placa_visible((string) ($owner['placa'] ?? ''));
        $groups['Propietarios de vehículo'][] = [
            'target' => 'propietario-registro-' . (int) $owner['id'],
            'icon' => $isCompany ? '🏢' : '🔑', 'vehicle_icon' => '🚘',
            'phone' => (string) ($owner[$isCompany ? 'rep_celular' : 'owner_celular'] ?? ''), 'name' => $name, 'meta' => $plate !== '' ? 'Placa ' . $plate : 'Sin placa', 'fatal' => false,
        ];
    }
    foreach ($familiares as $relative) {
        $deceased = person_label(project_prefixed_record($relative, 'fall_'));
        $relationship = compact_text((string) ($relative['parentesco'] ?? ''));
        $groups['Familiares de fallecidos'][] = [
            'target' => 'familiar-registro-' . (int) $relative['id'],
            'icon' => '👥', 'vehicle_icon' => '',
            'phone' => (string) ($relative['fam_celular'] ?? ''), 'name' => person_label(project_prefixed_record($relative, 'fam_')),
            'meta' => implode(' · ', array_filter([$relationship, trim($deceased) !== '' ? 'Familiar de ' . $deceased : ''])),
            'fatal' => false,
        ];
    }
    $total = array_sum(array_map('count', $groups));
    ?>
    <section class="case-header-people" aria-label="Participantes agrupados por rol">
      <div class="case-header-people-heading"><h2>Participantes <span><?= $total ?></span></h2><p>Agrupados por rol</p></div>
      <?php if ($total === 0): ?>
        <p class="case-header-people-empty">No hay participantes registrados.</p>
      <?php else: ?>
        <div class="case-header-people-groups" role="region" aria-label="Listado completo de participantes por rol">
          <?php foreach ($groups as $role => $members): ?>
            <?php if ($members === []) continue; ?>
            <?php
            $tone = match ($role) {
                'Conductores' => 'blue',
                'Ocupantes', 'Pasajeros' => 'cyan',
                'Peatones' => 'amber',
                'Efectivos policiales' => 'green',
                'Propietarios de vehículo' => 'violet',
                'Familiares de fallecidos' => 'rose',
                'Abogados' => 'gold',
                default => 'slate',
            };
            ?>
            <section class="case-header-people-group" data-role-tone="<?= h($tone) ?>">
              <h3><?= h($role) ?> <span><?= count($members) ?></span></h3>
              <ul>
                <?php foreach ($members as $member): ?>
                  <li class="case-header-person<?= $member['fatal'] ? ' is-fatal' : '' ?>">
                    <span class="case-header-person-icon" aria-hidden="true"><?= h($member['icon']) ?></span>
                    <div><strong><?= h(trim($member['name']) !== '' ? $member['name'] : 'Sin identificar') ?></strong><?php if ($member['fatal']): ?><span class="case-header-person-fatal">Fallecido</span><?php endif; ?><?php if ($member['meta'] !== ''): ?><small><span aria-hidden="true"><?= h($member['vehicle_icon']) ?></span> <?= h($member['meta']) ?></small><?php endif; ?><div class="case-person-actions"><button type="button" class="case-person-view js-sidebar-view-person" data-person-target="<?= h($member['target']) ?>" data-vehicle-only="0" aria-label="Ver persona: <?= h($member['name']) ?>">Ver persona <span aria-hidden="true">↗</span></button><?php if (!empty($member['vehicle_only'])): ?><button type="button" class="case-person-view js-sidebar-view-person" data-person-target="<?= h($member['target']) ?>" data-vehicle-only="1" aria-label="Ver vehículo: <?= h($member['name']) ?>">Ver vehículo <span aria-hidden="true">↗</span></button><?php endif; ?><?php $phone = preg_replace('/\D+/', '', $member['phone'] ?? ''); ?><?php if ($phone !== ''): ?><div class="case-person-whatsapp"><button type="button" class="case-person-view case-person-wa-toggle" aria-expanded="false" aria-controls="wa-<?= h($member['target']) ?>" aria-label="Opciones de WhatsApp: <?= h($member['name']) ?>" title="Opciones de WhatsApp"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M20.52 3.48A11.91 11.91 0 0 0 12.04 0C5.46 0 .1 5.35.1 11.93c0 2.1.55 4.15 1.6 5.96L0 24l6.26-1.64a11.94 11.94 0 0 0 5.77 1.47h.01c6.58 0 11.94-5.35 11.95-11.93a11.87 11.87 0 0 0-3.47-8.42zM12.04 21.82a9.9 9.9 0 0 1-5.05-1.38l-.36-.21-3.72.98.99-3.63-.24-.37a9.89 9.89 0 0 1-1.52-5.28c0-5.47 4.45-9.92 9.93-9.92a9.85 9.85 0 0 1 7.02 2.91 9.85 9.85 0 0 1 2.9 7.02c0 5.47-4.45 9.92-9.95 9.92zm5.45-7.43c-.3-.15-1.76-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.18.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.18-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.49s1.07 2.89 1.22 3.09c.15.2 2.1 3.2 5.09 4.48.71.31 1.26.49 1.69.62.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2.01-1.42.25-.7.25-1.3.17-1.42-.07-.13-.27-.2-.57-.35z"/></svg></button><div id="wa-<?= h($member['target']) ?>" class="case-person-wa-options" hidden><?= render_whatsapp_message_actions($phone, $messages) ?></div></div><?php endif; ?></div></div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php
})($personas, $policias, $abogados, $propietarios, $familiares, whatsapp_message_pack($modalidades, $A['fecha_accidente'] ?? null, $A['lugar'] ?? null, $A['registro_sidpol'] ?? null));
