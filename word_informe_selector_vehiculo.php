<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('SET NAMES utf8mb4');

function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function g(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

function placa_visible(string $placa): string
{
    return str_starts_with($placa, 'SPLACA') ? 'SIN PLACA' : $placa;
}

function fetch_all_selector(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function condicion_sql(string $condicion, string $prefix = 'ip', string $rolPrefix = 'pr'): string
{
    if ($condicion === 'fallecido') {
        return "LOWER(COALESCE({$prefix}.lesion, '')) LIKE '%falle%'";
    }

    return "(LOWER(COALESCE({$prefix}.lesion, '')) LIKE '%ileso%'
             OR LOWER(COALESCE({$prefix}.lesion, '')) LIKE '%herid%')
            AND LOWER(COALESCE({$rolPrefix}.Nombre, '')) LIKE '%conduc%'";
}

function target_report(string $tipoVehiculo, string $condicion): string
{
    if ($tipoVehiculo === 'combinado') {
        return 'word_informe_combinado_vehiculo.php';
    }

    return $condicion === 'fallecido'
        ? 'word_informe_un_vehiculo_fallecido.php'
        : 'word_informe_un_vehiculo_ileso.php';
}

function load_accidentes(PDO $pdo): array
{
    return $pdo->query("
        SELECT id,
               CONCAT('#', id, ' - ', DATE_FORMAT(fecha_accidente, '%Y-%m-%d %H:%i'), ' - ', COALESCE(lugar, '')) AS label
          FROM accidentes
         ORDER BY id DESC
         LIMIT 300
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function load_unidades(PDO $pdo, int $accidenteId, string $condicion): array
{
    $cond = condicion_sql($condicion);
    $sql = "
        SELECT iv.id AS vehiculo_inv_id,
               iv.orden_participacion,
               iv.tipo AS involucrado_tipo,
               v.id AS vehiculo_id,
               v.placa,
               v.color,
               v.anio,
               GROUP_CONCAT(
                   DISTINCT TRIM(CONCAT(
                       COALESCE(p.apellido_paterno, ''), ' ',
                       COALESCE(p.apellido_materno, ''), ' ',
                       COALESCE(p.nombres, ''),
                       ' - ', COALESCE(pr.Nombre, ''),
                       ' / ', COALESCE(ip.lesion, '')
                   ))
                   ORDER BY ip.id
                   SEPARATOR '; '
               ) AS personas
          FROM involucrados_vehiculos iv
          JOIN vehiculos v ON v.id = iv.vehiculo_id
          JOIN involucrados_personas ip ON ip.accidente_id = iv.accidente_id AND ip.vehiculo_id = iv.vehiculo_id
     LEFT JOIN participacion_persona pr ON pr.Id = ip.rol_id
          JOIN personas p ON p.id = ip.persona_id
         WHERE iv.accidente_id = :a
           AND iv.tipo = 'Unidad'
           AND {$cond}
      GROUP BY iv.id, iv.orden_participacion, iv.tipo, v.id, v.placa, v.color, v.anio
      ORDER BY FIELD(iv.orden_participacion, 'UT-1','UT-2','UT-3','UT-4','UT-5','UT-6','UT-7'), iv.id ASC
    ";

    $rows = fetch_all_selector($pdo, $sql, [':a' => $accidenteId]);
    foreach ($rows as &$row) {
        $placa = placa_visible((string) ($row['placa'] ?? ''));
        $row['placa_label'] = trim((string) ($row['orden_participacion'] ?? '') . ' - ' . $placa);
        if (!empty($row['color'])) {
            $row['placa_label'] .= ' - ' . $row['color'];
        }
        if (!empty($row['anio'])) {
            $row['placa_label'] .= ' (' . $row['anio'] . ')';
        }
    }

    return $rows;
}

function load_combinados(PDO $pdo, int $accidenteId, string $condicion): array
{
    $cond = condicion_sql($condicion, 'ip2', 'pr2');
    $sql = "
        SELECT combo.vehiculo_inv_id,
               combo.orden_participacion,
               combo.placa_label,
               combo.detalle,
               (
                   SELECT GROUP_CONCAT(
                              DISTINCT TRIM(CONCAT(
                                  COALESCE(p2.apellido_paterno, ''), ' ',
                                  COALESCE(p2.apellido_materno, ''), ' ',
                                  COALESCE(p2.nombres, ''),
                                  ' - ', COALESCE(pr2.Nombre, ''),
                                  ' / ', COALESCE(ip2.lesion, '')
                              ))
                              ORDER BY ip2.id
                              SEPARATOR '; '
                          )
                     FROM involucrados_personas ip2
                     JOIN personas p2 ON p2.id = ip2.persona_id
                LEFT JOIN participacion_persona pr2 ON pr2.Id = ip2.rol_id
                    WHERE ip2.accidente_id = :a_personas
                      AND ip2.vehiculo_id IN (
                          SELECT ivx.vehiculo_id
                            FROM involucrados_vehiculos ivx
                           WHERE ivx.accidente_id = :a_combo
                             AND ivx.orden_participacion = combo.orden_participacion
                             AND ivx.tipo IN ('Combinado vehicular 1', 'Combinado vehicular 2')
                      )
                      AND {$cond}
               ) AS personas
          FROM (
              SELECT MIN(CASE WHEN iv.tipo = 'Combinado vehicular 1' THEN iv.id END) AS vehiculo_inv_id,
                     iv.orden_participacion,
                     GROUP_CONCAT(
                         CASE WHEN v.placa LIKE 'SPLACA%' THEN 'SIN PLACA' ELSE v.placa END
                         ORDER BY FIELD(iv.tipo, 'Combinado vehicular 1', 'Combinado vehicular 2'), iv.id
                         SEPARATOR ' + '
                     ) AS placa_label,
                     GROUP_CONCAT(
                         CONCAT(iv.tipo, ': ', CASE WHEN v.placa LIKE 'SPLACA%' THEN 'SIN PLACA' ELSE v.placa END)
                         ORDER BY FIELD(iv.tipo, 'Combinado vehicular 1', 'Combinado vehicular 2'), iv.id
                         SEPARATOR ' | '
                     ) AS detalle,
                     COUNT(*) AS componentes
                FROM involucrados_vehiculos iv
                JOIN vehiculos v ON v.id = iv.vehiculo_id
               WHERE iv.accidente_id = :a
                 AND iv.tipo IN ('Combinado vehicular 1', 'Combinado vehicular 2')
            GROUP BY iv.accidente_id, iv.orden_participacion
              HAVING componentes >= 2 AND vehiculo_inv_id IS NOT NULL
          ) combo
         WHERE combo.vehiculo_inv_id IS NOT NULL
      ORDER BY FIELD(combo.orden_participacion, 'UT-1','UT-2','UT-3','UT-4','UT-5','UT-6','UT-7'), combo.vehiculo_inv_id ASC
    ";

    $rows = fetch_all_selector($pdo, $sql, [
        ':a' => $accidenteId,
        ':a_personas' => $accidenteId,
        ':a_combo' => $accidenteId,
    ]);

    return array_values(array_filter($rows, static fn($row) => trim((string) ($row['personas'] ?? '')) !== ''));
}

function load_peatones_fallecidos(PDO $pdo, int $accidenteId): array
{
    $sql = "
        SELECT ip.id AS persona_inv_id,
               ip.orden_persona,
               ip.lesion,
               pr.Nombre AS rol_nombre,
               p.id AS persona_id,
               p.tipo_doc,
               p.num_doc,
               p.apellido_paterno,
               p.apellido_materno,
               p.nombres
          FROM involucrados_personas ip
          JOIN personas p ON p.id = ip.persona_id
     LEFT JOIN participacion_persona pr ON pr.Id = ip.rol_id
         WHERE ip.accidente_id = :a
           AND LOWER(COALESCE(ip.lesion, '')) LIKE '%falle%'
           AND (
                LOWER(COALESCE(pr.Nombre, '')) LIKE '%peat%'
                OR ip.rol_id = 2
                OR ip.vehiculo_id IS NULL
                OR ip.vehiculo_id = 0
           )
      ORDER BY ip.id ASC
    ";

    $rows = fetch_all_selector($pdo, $sql, [':a' => $accidenteId]);
    foreach ($rows as &$row) {
        $nombre = trim(
            (string) ($row['apellido_paterno'] ?? '') . ' ' .
            (string) ($row['apellido_materno'] ?? '') . ' ' .
            (string) ($row['nombres'] ?? '')
        );
        $doc = trim((string) ($row['tipo_doc'] ?? '') . ' ' . (string) ($row['num_doc'] ?? ''));
        $row['placa_label'] = trim('Peaton - ' . ($nombre !== '' ? $nombre : 'SIN NOMBRE'));
        $row['personas'] = trim(($nombre !== '' ? $nombre : 'SIN NOMBRE') . ($doc !== '' ? ' - ' . $doc : '') . ' / ' . ($row['lesion'] ?? 'Fallecido'));
        $row['detalle'] = $row['rol_nombre'] ?? 'Peaton';
    }

    return $rows;
}

function count_vehicle_groups(PDO $pdo, int $accidenteId): int
{
    $sql = "
        SELECT SUM(total) FROM (
            SELECT COUNT(*) AS total
              FROM involucrados_vehiculos
             WHERE accidente_id = :a_unidad
               AND tipo NOT IN ('Combinado vehicular 1', 'Combinado vehicular 2')
            UNION ALL
            SELECT COUNT(*) AS total
              FROM (
                  SELECT orden_participacion
                    FROM involucrados_vehiculos
                   WHERE accidente_id = :a_combo
                     AND tipo IN ('Combinado vehicular 1', 'Combinado vehicular 2')
                GROUP BY orden_participacion
              ) combos
        ) conteo
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':a_unidad' => $accidenteId, ':a_combo' => $accidenteId]);
    return (int) $st->fetchColumn();
}

function load_auto_options(PDO $pdo, int $accidenteId): array
{
    $items = [];
    foreach (['unidad', 'combinado'] as $tipoVehiculo) {
        foreach (['ileso', 'fallecido'] as $condicion) {
            $rows = $tipoVehiculo === 'combinado'
                ? load_combinados($pdo, $accidenteId, $condicion)
                : load_unidades($pdo, $accidenteId, $condicion);

            foreach ($rows as $row) {
                $row['tipo_vehiculo'] = $tipoVehiculo;
                $row['condicion'] = $condicion;
                $row['tipo_label'] = $tipoVehiculo === 'combinado' ? 'Combinado vehicular' : 'Unidad';
                $row['condicion_label'] = $condicion === 'fallecido' ? 'Fallecido' : 'Ileso / Herido';
                $row['reporte'] = target_report($tipoVehiculo, $condicion);
                $row['download_url'] = $row['reporte'] . '?accidente_id=' . $accidenteId . '&vehiculo_inv_id=' . (int) $row['vehiculo_inv_id'];
                $items[] = $row;
            }
        }
    }

    foreach (load_peatones_fallecidos($pdo, $accidenteId) as $row) {
        $row['tipo_vehiculo'] = 'peaton';
        $row['condicion'] = 'fallecido';
        $row['tipo_label'] = 'Peaton';
        $row['condicion_label'] = 'Fallecido';
        $row['reporte'] = 'word_informe_peaton_fallecido.php';
        $row['download_url'] = $row['reporte'] . '?accidente_id=' . $accidenteId . '&persona_inv_id=' . (int) $row['persona_inv_id'];
        $items[] = $row;
    }

    usort($items, static function (array $a, array $b): int {
        $ordenA = (string) ($a['orden_participacion'] ?? '');
        $ordenB = (string) ($b['orden_participacion'] ?? '');
        $rank = ['UT-1' => 1, 'UT-2' => 2, 'UT-3' => 3, 'UT-4' => 4, 'UT-5' => 5, 'UT-6' => 6, 'UT-7' => 7];
        $cmp = ($rank[$ordenA] ?? 99) <=> ($rank[$ordenB] ?? 99);
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = strcmp((string) ($a['tipo_vehiculo'] ?? ''), (string) ($b['tipo_vehiculo'] ?? ''));
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string) ($a['condicion'] ?? ''), (string) ($b['condicion'] ?? ''));
    });

    return $items;
}

$accidenteId = (int) g('accidente_id', '0');
$tipoVehiculo = g('tipo_vehiculo', 'unidad');
$condicion = g('condicion', 'ileso');
$vehiculoInvId = (int) g('vehiculo_inv_id', '0');
$action = g('action');
$isEmbed = g('embed', '0') === '1';

$tiposValidos = ['unidad', 'combinado'];
$condicionesValidas = ['ileso', 'fallecido'];
if (!in_array($tipoVehiculo, $tiposValidos, true)) {
    $tipoVehiculo = 'unidad';
}
if (!in_array($condicion, $condicionesValidas, true)) {
    $condicion = 'ileso';
}

$opciones = [];
$autoOptions = [];
$vehicleGroupCount = 0;
if ($accidenteId > 0) {
    $opciones = $tipoVehiculo === 'combinado'
        ? load_combinados($pdo, $accidenteId, $condicion)
        : load_unidades($pdo, $accidenteId, $condicion);
    $autoOptions = load_auto_options($pdo, $accidenteId);
    $vehicleGroupCount = count_vehicle_groups($pdo, $accidenteId);
}

if ($action === 'generar' && $accidenteId > 0 && $vehiculoInvId > 0) {
    $idsValidos = array_map(static fn($row) => (int) ($row['vehiculo_inv_id'] ?? 0), $opciones);
    if (in_array($vehiculoInvId, $idsValidos, true)) {
        $target = target_report($tipoVehiculo, $condicion);
        header('Location: ' . $target . '?accidente_id=' . $accidenteId . '&vehiculo_inv_id=' . $vehiculoInvId);
        exit;
    }
}

$accidentes = load_accidentes($pdo);

if (!$isEmbed) {
    include __DIR__ . '/sidebar.php';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Descargo vehicular</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
  body{ margin:0; }
  body.selector-embed{ background:transparent; }
  body.selector-embed .wrap{ max-width:none; margin:0 auto; padding:0; }
  .wrap{ max-width:980px; margin:22px auto; padding:0 14px; }
  .topbar{ display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:14px; }
  .title{ font-size:26px; font-weight:900; margin:0; }
  .muted{ color:var(--fg-2,#64748b); font-size:13px; }
  .card{ border:1px solid var(--line,#e5e7eb); border-radius:12px; background:var(--bg-1,#fff); padding:14px; margin-bottom:12px; }
  .grid{ display:grid; grid-template-columns:1.4fr 1fr 1fr; gap:12px; align-items:end; }
  .grid-select{ display:grid; grid-template-columns:1fr auto; gap:12px; align-items:end; }
  label{ display:block; font-size:12px; font-weight:800; color:var(--fg-2,#64748b); margin:0 0 6px; }
  select{ width:100%; padding:10px 12px; border:1px solid var(--line,#d1d5db); border-radius:8px; background:var(--bg-2,#fff); color:var(--fg,#111827); }
  .btn{ display:inline-flex; justify-content:center; align-items:center; gap:6px; min-height:40px; padding:10px 14px; border-radius:8px; border:1px solid var(--line,#d1d5db); background:var(--bg-2,#f8fafc); color:inherit; text-decoration:none; font-weight:800; cursor:pointer; white-space:nowrap; }
  .btn.primary{ background:#2563eb; border-color:#1d4ed8; color:#fff; }
  .option-meta{ margin-top:8px; font-size:12px; color:var(--fg-2,#64748b); }
  .pill{ display:inline-flex; align-items:center; border:1px solid var(--line,#d1d5db); border-radius:999px; padding:4px 8px; font-size:12px; font-weight:800; margin:4px 6px 0 0; }
  .empty{ border:1px dashed var(--line,#d1d5db); border-radius:12px; padding:14px; color:var(--fg-2,#64748b); }
  .auto-head{ display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
  .auto-title{ margin:0; font-size:18px; font-weight:900; }
  .auto-list{ display:grid; gap:10px; }
  .auto-row{ display:grid; grid-template-columns:1fr auto; gap:12px; align-items:center; border:1px solid var(--line,#e5e7eb); border-radius:10px; padding:12px; background:var(--bg-2,#f8fafc); }
  .auto-main{ font-weight:900; margin-bottom:6px; }
  .auto-meta{ color:var(--fg-2,#64748b); font-size:12px; line-height:1.45; }
  @media (max-width:760px){ .grid,.grid-select{ grid-template-columns:1fr; } .topbar{ flex-direction:column; } }
  @media (max-width:760px){ .auto-row{ grid-template-columns:1fr; } }
</style>
</head>
<body class="<?= $isEmbed ? 'selector-embed' : '' ?>">
<div class="wrap">
  <div class="topbar">
    <div>
      <h1 class="title">Descargo vehicular</h1>
      <div class="muted">Selecciona accidente, tipo, condicion y placa para generar el Word con la unidad correcta.</div>
    </div>
    <?php if (!$isEmbed): ?>
      <a class="btn" href="javascript:history.back()">Volver</a>
    <?php endif; ?>
  </div>

  <form class="card" method="get">
    <?php if ($isEmbed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
    <div class="grid">
      <div>
        <label for="accidente_id">Accidente</label>
        <select id="accidente_id" name="accidente_id" required>
          <option value="">Selecciona un accidente</option>
          <?php foreach ($accidentes as $acc): ?>
            <option value="<?= (int) $acc['id'] ?>" <?= $accidenteId === (int) $acc['id'] ? 'selected' : '' ?>>
              <?= h($acc['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="tipo_vehiculo">Tipo de vehiculo</label>
        <select id="tipo_vehiculo" name="tipo_vehiculo">
          <option value="unidad" <?= $tipoVehiculo === 'unidad' ? 'selected' : '' ?>>Unidad</option>
          <option value="combinado" <?= $tipoVehiculo === 'combinado' ? 'selected' : '' ?>>Combinado vehicular</option>
        </select>
      </div>
      <div>
        <label for="condicion">Condicion</label>
        <select id="condicion" name="condicion">
          <option value="ileso" <?= $condicion === 'ileso' ? 'selected' : '' ?>>Ileso / Herido</option>
          <option value="fallecido" <?= $condicion === 'fallecido' ? 'selected' : '' ?>>Fallecido</option>
        </select>
      </div>
    </div>
    <div style="margin-top:12px; display:flex; justify-content:flex-end;">
      <button class="btn primary" type="submit">Buscar placas</button>
    </div>
  </form>

  <?php if ($accidenteId > 0): ?>
    <div class="card">
      <div class="auto-head">
        <div>
          <h2 class="auto-title">Informes detectados automaticamente</h2>
          <div class="muted">
            Vehiculos/unidades registradas: <b><?= (int) $vehicleGroupCount ?></b>.
            Descargas disponibles segun lesion y tipo: <b><?= count($autoOptions) ?></b>.
          </div>
        </div>
      </div>

      <?php if ($autoOptions): ?>
        <div class="auto-list">
          <?php foreach ($autoOptions as $row): ?>
            <div class="auto-row">
              <div>
                <?php
                  $displayLabel = trim((string) ($row['placa_label'] ?? ''));
                  if (!empty($row['orden_participacion'])) {
                      $displayLabel = trim((string) $row['orden_participacion'] . ' - ' . $displayLabel);
                  }
                ?>
                <div class="auto-main"><?= h($displayLabel) ?></div>
                <div class="auto-meta">
                  <span class="pill"><?= h($row['tipo_label']) ?></span>
                  <span class="pill"><?= h($row['condicion_label']) ?></span>
                  <span class="pill"><?= h($row['reporte']) ?></span>
                  <?php if (!empty($row['detalle'])): ?><span class="pill"><?= h($row['detalle']) ?></span><?php endif; ?>
                </div>
                <?php if (!empty($row['personas'])): ?>
                  <div class="auto-meta" style="margin-top:6px"><?= h($row['personas']) ?></div>
                <?php endif; ?>
              </div>
              <a class="btn primary" href="<?= h($row['download_url']) ?>">Descargar Word</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty">No se detecto automaticamente un informe descargable para este accidente. Revisa que las personas tengan lesion registrada como ileso, herido o fallecido; para ileso/herido debe figurar rol de conductor y para peaton debe figurar rol peaton o estar sin vehiculo.</div>
      <?php endif; ?>
    </div>

    <?php if ($opciones): ?>
      <form class="card" method="get">
        <input type="hidden" name="action" value="generar">
        <?php if ($isEmbed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
        <input type="hidden" name="accidente_id" value="<?= (int) $accidenteId ?>">
        <input type="hidden" name="tipo_vehiculo" value="<?= h($tipoVehiculo) ?>">
        <input type="hidden" name="condicion" value="<?= h($condicion) ?>">

        <div class="grid-select">
          <div>
            <label for="vehiculo_inv_id">Placa o combinado disponible</label>
            <select id="vehiculo_inv_id" name="vehiculo_inv_id" required>
              <?php foreach ($opciones as $row): ?>
                <option value="<?= (int) $row['vehiculo_inv_id'] ?>">
                  <?= h(($row['orden_participacion'] ?? '') . ' - ' . ($row['placa_label'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php foreach ($opciones as $row): ?>
              <div class="option-meta" data-option-meta="<?= (int) $row['vehiculo_inv_id'] ?>">
                <span class="pill"><?= h($row['detalle'] ?? ($row['involucrado_tipo'] ?? 'Unidad')) ?></span>
                <?php if (!empty($row['personas'])): ?><span class="pill"><?= h($row['personas']) ?></span><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <button class="btn primary" type="submit">Generar descargo</button>
        </div>
      </form>
    <?php else: ?>
      <div class="empty">
        No hay placas que cumplan con el tipo <b><?= h($tipoVehiculo === 'combinado' ? 'Combinado vehicular' : 'Unidad') ?></b>
        y condicion <b><?= h($condicion) ?></b> para este accidente.
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<script>
(function(){
  const select = document.getElementById('vehiculo_inv_id');
  if (!select) return;
  const metas = Array.from(document.querySelectorAll('[data-option-meta]'));
  function syncMeta(){
    metas.forEach(function(meta){
      meta.style.display = meta.getAttribute('data-option-meta') === select.value ? 'block' : 'none';
    });
  }
  select.addEventListener('change', syncMeta);
  syncMeta();
})();
</script>
</body>
</html>
