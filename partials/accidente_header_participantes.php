<?php
// Resumen de presentación: utiliza únicamente los registros ya cargados por la vista.
(static function (array $personas, array $policias, array $abogados, array $propietarios, array $familiares): void {
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
            'name' => person_label($person),
            'meta' => implode(' · ', $vehicle),
            'fatal' => needs_occ($person),
        ];
    }
    foreach ($policias as $officer) {
        $groups['Efectivos policiales'][] = ['target' => 'policia-registro-' . (int) $officer['id'], 'icon' => '👮', 'vehicle_icon' => '', 'name' => person_label($officer), 'meta' => (string) ($officer['grado_policial'] ?? ''), 'fatal' => false];
    }
    foreach ($abogados as $lawyer) {
        $groups['Abogados'][] = ['target' => 'sidebar-abogado-' . (int) $lawyer['id'], 'icon' => '⚖️', 'vehicle_icon' => '', 'name' => person_label($lawyer), 'meta' => '', 'fatal' => false];
    }
    foreach ($propietarios as $owner) {
        $isCompany = mb_strtoupper(trim((string) ($owner['tipo_propietario'] ?? '')), 'UTF-8') === 'JURIDICA';
        $name = $isCompany ? (string) ($owner['razon_social'] ?? '') : person_label(project_prefixed_record($owner, 'owner_'));
        $plate = vehiculo_placa_visible((string) ($owner['placa'] ?? ''));
        $groups['Propietarios de vehículo'][] = [
            'target' => 'propietario-registro-' . (int) $owner['id'],
            'icon' => $isCompany ? '🏢' : '🔑', 'vehicle_icon' => '🚘',
            'name' => $name, 'meta' => $plate !== '' ? 'Placa ' . $plate : 'Sin placa', 'fatal' => false,
        ];
    }
    foreach ($familiares as $relative) {
        $deceased = person_label(project_prefixed_record($relative, 'fall_'));
        $relationship = compact_text((string) ($relative['parentesco'] ?? ''));
        $groups['Familiares de fallecidos'][] = [
            'target' => 'familiar-registro-' . (int) $relative['id'],
            'icon' => '👥', 'vehicle_icon' => '',
            'name' => person_label(project_prefixed_record($relative, 'fam_')),
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
                    <div><strong><?= h(trim($member['name']) !== '' ? $member['name'] : 'Sin identificar') ?></strong><?php if ($member['fatal']): ?><span class="case-header-person-fatal">Fallecido</span><?php endif; ?><?php if ($member['meta'] !== ''): ?><small><span aria-hidden="true"><?= h($member['vehicle_icon']) ?></span> <?= h($member['meta']) ?></small><?php endif; ?><div class="case-person-actions"><button type="button" class="case-person-view js-sidebar-view-person" data-person-target="<?= h($member['target']) ?>" data-vehicle-only="0" aria-label="Ver persona: <?= h($member['name']) ?>">Ver persona <span aria-hidden="true">↗</span></button><?php if (!empty($member['vehicle_only'])): ?><button type="button" class="case-person-view js-sidebar-view-person" data-person-target="<?= h($member['target']) ?>" data-vehicle-only="1" aria-label="Ver vehículo: <?= h($member['name']) ?>">Ver vehículo <span aria-hidden="true">↗</span></button><?php endif; ?></div></div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php
})($personas, $policias, $abogados, $propietarios, $familiares);
