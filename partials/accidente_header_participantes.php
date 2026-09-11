<?php
// Resumen de presentación: utiliza únicamente los registros ya cargados por la vista.
(static function (array $personas, array $policias, array $abogados): void {
    $groups = array_fill_keys(['Conductores', 'Ocupantes', 'Pasajeros', 'Peatones', 'Efectivos policiales', 'Abogados', 'Otros'], []);
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
            'name' => person_label($person),
            'meta' => implode(' · ', $vehicle),
            'fatal' => needs_occ($person),
        ];
    }
    foreach ($policias as $officer) {
        $groups['Efectivos policiales'][] = ['icon' => '👮', 'vehicle_icon' => '', 'name' => person_label($officer), 'meta' => (string) ($officer['grado_policial'] ?? ''), 'fatal' => false];
    }
    foreach ($abogados as $lawyer) {
        $groups['Abogados'][] = ['icon' => '⚖️', 'vehicle_icon' => '', 'name' => person_label($lawyer), 'meta' => '', 'fatal' => false];
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
            <section class="case-header-people-group">
              <h3><?= h($role) ?> <span><?= count($members) ?></span></h3>
              <ul>
                <?php foreach ($members as $member): ?>
                  <li class="case-header-person<?= $member['fatal'] ? ' is-fatal' : '' ?>">
                    <span class="case-header-person-icon" aria-hidden="true"><?= h($member['icon']) ?></span>
                    <div><strong><?= h(trim($member['name']) !== '' ? $member['name'] : 'Sin identificar') ?></strong><?php if ($member['fatal']): ?><span class="case-header-person-fatal">Fallecido</span><?php endif; ?><?php if ($member['meta'] !== ''): ?><small><span aria-hidden="true"><?= h($member['vehicle_icon']) ?></span> <?= h($member['meta']) ?></small><?php endif; ?></div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php
})($personas, $policias, $abogados);
