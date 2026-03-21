<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function g($k, $d = null)
{
    return isset($_GET[$k]) ? trim((string) $_GET[$k]) : $d;
}

function edad_ref($fn, $ref)
{
    if (!$fn) {
        return '';
    }
    $b = new DateTime($fn);
    $a = $ref ? new DateTime($ref) : new DateTime();
    return $a->diff($b)->y;
}

function dash($value): string
{
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? $value : '-';
}

function fmt_dt($value): string
{
    if (!$value) {
        return 'Sin fecha';
    }
    return date('Y-m-d H:i', strtotime((string) $value));
}

function make_summary(array $inv): string
{
    $pname = trim(($inv['nombres'] ?? '') . ' ' . ($inv['apellido_paterno'] ?? '') . ' ' . ($inv['apellido_materno'] ?? ''));
    $rows = [];
    $rows[] = 'Persona: ' . ($pname !== '' ? $pname : ('ID ' . (int) ($inv['persona_id'] ?? 0))) . ' (DNI: ' . dash($inv['num_doc'] ?? '') . ')';
    $rows[] = 'Sexo: ' . dash($inv['sexo'] ?? '') . ' - Edad ref.: ' . ($inv['fecha_nacimiento'] ? edad_ref($inv['fecha_nacimiento'], $inv['fecha_accidente'] ?? '') : '-');
    $rows[] = 'Rol: ' . dash($inv['rol_nombre'] ?? '');
    if (!empty($inv['veh_placa'])) {
        $rows[] = 'Vehiculo: ' . trim((string) ($inv['veh_placa'] . ' ' . ($inv['veh_marca'] ?? '') . ' ' . ($inv['veh_modelo'] ?? '')));
    }
    $rows[] = 'Accidente: #' . ((int) ($inv['accidente_id'] ?? 0)) . ' - ' . ($inv['fecha_accidente'] ? fmt_dt($inv['fecha_accidente']) : 'Sin fecha') . ' - ' . dash($inv['lugar_accidente'] ?? '');
    $rows[] = 'Lesion: ' . dash($inv['lesion'] ?? '');
    $obs = trim((string) ($inv['observaciones'] ?? ''));
    if ($obs !== '') {
        $rows[] = 'Observaciones: ' . preg_replace('/\s+/', ' ', $obs);
    }
    return implode("\n", $rows);
}

$id = (int) g('id', 0);
if ($id <= 0) {
    die('ID de involucrado invalido');
}

$sql = "
  SELECT ip.*,
         p.num_doc, p.nombres, p.apellido_paterno, p.apellido_materno,
         p.sexo, p.fecha_nacimiento,
         a.fecha_accidente, a.lugar AS lugar_accidente,
         v.id AS veh_id, v.placa AS veh_placa, v.color AS veh_color, v.anio AS veh_anio, v.marca AS veh_marca, v.modelo AS veh_modelo,
         pp.Nombre AS rol_nombre, COALESCE(pp.RequiereVehiculo,0) AS rol_reqveh
  FROM involucrados_personas ip
  LEFT JOIN personas p ON p.id = ip.persona_id
  LEFT JOIN accidentes a ON a.id = ip.accidente_id
  LEFT JOIN vehiculos v ON v.id = ip.vehiculo_id
  LEFT JOIN participacion_persona pp ON pp.Id = ip.rol_id
  WHERE ip.id = ?
  LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([$id]);
$inv = $st->fetch(PDO::FETCH_ASSOC);
if (!$inv) {
    die('No se encontro el registro');
}

$pid = (int) $inv['persona_id'];
$aid = (int) $inv['accidente_id'];
$vehiculoId = (int) ($inv['veh_id'] ?? 0);
$returnTo = trim((string) ($_GET['return_to'] ?? ''));
if ($returnTo === '') {
    $returnTo = 'involucrados_personas_listar.php?accidente_id=' . $aid;
}
$selfUrl = $_SERVER['REQUEST_URI'] ?? ('involucrados_personas_ver.php?id=' . $id . '&return_to=' . urlencode($returnTo));

$isConductor = ((int) $inv['rol_reqveh'] === 1);
$isPeatonPasajero = !$isConductor;
$show = [
    'lc' => $isConductor,
    'rml' => $isConductor,
    'dos' => $isConductor,
    'man' => $isPeatonPasajero,
    'occ' => $isPeatonPasajero,
];

$lcList = [];
try {
    $st = $pdo->prepare("
      SELECT id, clase, categoria, numero, expedido_por, vigente_desde, vigente_hasta, restricciones
      FROM documento_lc
      WHERE persona_id = ?
      ORDER BY COALESCE(vigente_hasta, '9999-12-31') DESC, id DESC
    ");
    $st->execute([$pid]);
    $lcList = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lcList = [];
}

$rmlList = [];
try {
    $st = $pdo->prepare("SELECT id, numero, fecha, incapacidad_medico, atencion_facultativo, observaciones FROM documento_rml WHERE persona_id = ? ORDER BY COALESCE(fecha, '9999-12-31') DESC, id DESC");
    $st->execute([$pid]);
    $rmlList = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rmlList = [];
}

$dosList = [];
try {
    $st = $pdo->prepare("SELECT id, numero, numero_registro, fecha_extraccion, resultado_cualitativo, resultado_cuantitativo, observaciones FROM documento_dosaje WHERE persona_id = ? ORDER BY COALESCE(fecha_extraccion, '9999-12-31 23:59:59') DESC, id DESC");
    $st->execute([$pid]);
    $dosList = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dosList = [];
}

$manList = [];
try {
    $st = $pdo->prepare("SELECT id, fecha, horario_inicio, hora_termino, modalidad, observaciones FROM Manifestacion WHERE persona_id = ? AND accidente_id = ? ORDER BY COALESCE(fecha, '9999-12-31') DESC, id DESC");
    $st->execute([$pid, $aid]);
    $manList = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $manList = [];
}

$occList = [];
try {
    $st = $pdo->prepare("SELECT id, fecha_levantamiento, hora_levantamiento, lugar_levantamiento, numero_protocolo, observaciones_levantamiento FROM documento_occiso WHERE persona_id = ? AND accidente_id = ? ORDER BY COALESCE(fecha_levantamiento, '9999-12-31') DESC, id DESC");
    $st->execute([$pid, $aid]);
    $occList = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $occList = [];
}

$veh = null;
$propietario = null;
if ($vehiculoId > 0) {
    try {
        $st = $pdo->prepare("SELECT * FROM vehiculos WHERE id = ? LIMIT 1");
        $st->execute([$vehiculoId]);
        $veh = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($veh) {
            $ownerId = 0;
            foreach (['propietario_id', 'persona_id', 'duenio_id', 'propietario_persona_id', 'owner_id'] as $col) {
                if (isset($veh[$col]) && (int) $veh[$col] > 0) {
                    $ownerId = (int) $veh[$col];
                    break;
                }
            }
            if ($ownerId > 0) {
                $st2 = $pdo->prepare("SELECT id, num_doc, nombres, apellido_paterno, apellido_materno, sexo, fecha_nacimiento FROM personas WHERE id = ? LIMIT 1");
                $st2->execute([$ownerId]);
                $propietario = $st2->fetch(PDO::FETCH_ASSOC) ?: null;
            } else {
                try {
                    $st3 = $pdo->query("SELECT 1 FROM propietarios LIMIT 1");
                    if ($st3) {
                        $q = $pdo->prepare("SELECT p.id, p.num_doc, p.nombres, p.apellido_paterno, p.apellido_materno FROM propietarios pr JOIN personas p ON p.id = pr.persona_id WHERE pr.vehiculo_id = ? LIMIT 1");
                        $q->execute([$vehiculoId]);
                        $propietario = $q->fetch(PDO::FETCH_ASSOC) ?: null;
                    }
                } catch (PDOException $e) {
                }
            }
        }
    } catch (PDOException $e) {
        $veh = null;
        $propietario = null;
    }
}

$totalCounts = [
    'lc' => count($lcList),
    'rml' => count($rmlList),
    'dos' => count($dosList),
    'man' => count($manList),
    'occ' => count($occList),
];

$hiddenSections = [];
if (!$show['lc'] && count($lcList)) {
    $hiddenSections['lc'] = $lcList;
}
if (!$show['rml'] && count($rmlList)) {
    $hiddenSections['rml'] = $rmlList;
}
if (!$show['dos'] && count($dosList)) {
    $hiddenSections['dos'] = $dosList;
}
if (!$show['man'] && count($manList)) {
    $hiddenSections['man'] = $manList;
}
if (!$show['occ'] && count($occList)) {
    $hiddenSections['occ'] = $occList;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Involucrado - Persona #<?= (int) $id ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
  :root{ --card-bg: rgba(255,255,255,0.03); --card-border: rgba(255,255,255,0.05); --mut:#c7c7c7; --text:#eaeff6; }
  body{background:#0b1220;color:var(--text);font-family:Inter,system-ui,Arial,Helvetica,sans-serif}
  .wrap{max-width:1150px;margin:18px auto;padding:12px;}
  .bar{display:flex;gap:8px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
  .card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:12px;padding:18px;margin-bottom:12px;}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .rowv{display:grid;grid-template-columns:180px 1fr;gap:10px;margin:8px 0;align-items:start}
  .mut{color:var(--mut);font-weight:700}
  .summary-pre{white-space:pre-wrap;background:rgba(255,255,255,0.02);padding:12px;border-radius:10px;border:1px solid rgba(255,255,255,0.04);line-height:1.45}
  .counts{display:flex;gap:8px;margin-top:6px;flex-wrap:wrap;}
  .count{padding:6px 10px;background:rgba(255,255,255,0.02);border-radius:8px;border:1px solid rgba(255,255,255,0.03);font-size:13px}
  .btn{padding:7px 12px;border-radius:8px;background:#1f2937;color:#fff;text-decoration:none;cursor:pointer;border:0}
  .btn.ghost{background:transparent;border:1px solid rgba(255,255,255,0.06)}
  .btn.danger{background:#7f1d1d}
  .doc-card{background:rgba(255,255,255,0.01);border:1px solid rgba(255,255,255,0.03);padding:10px;border-radius:8px;margin-bottom:8px}
  .doc-row{display:flex;gap:8px;align-items:center;justify-content:space-between}
  .chip{display:inline-block;padding:6px 8px;border-radius:8px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.03);font-size:13px}
  .other-section{display:none;background:rgba(255,255,255,0.01);padding:10px;border-radius:8px;margin-top:8px;border:1px dashed rgba(255,255,255,0.03)}
  .veh-box{display:grid;grid-template-columns:120px 1fr;gap:10px;align-items:start;padding:10px;background:rgba(255,255,255,0.015);border-radius:8px;border:1px solid rgba(255,255,255,0.02)}
  @media(max-width:920px){ .grid{grid-template-columns:1fr} .rowv{grid-template-columns:140px 1fr} .veh-box{grid-template-columns:1fr} }
</style>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <a class="btn ghost" href="<?= h($returnTo) ?>">Volver</a>
    <a class="btn ghost" href="involucrados_personas_editar.php?id=<?= (int) $id ?>&return_to=<?= urlencode($selfUrl) ?>">Editar</a>
    <a class="btn danger" href="involucrados_personas_eliminar.php?id=<?= (int) $id ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
    <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn" href="persona_leer.php?id=<?= (int) $inv['persona_id'] ?>">Ver persona</a>
      <?php if ($vehiculoId): ?>
        <a class="btn" href="vehiculo_leer.php?id=<?= (int) $vehiculoId ?>">Ver vehiculo</a>
      <?php endif; ?>
      <button class="btn" onclick="copyResumen()">Copiar resumen</button>
    </div>
  </div>

  <div class="card">
    <h2 style="margin-top:0">Involucrado - Persona #<?= (int) $id ?></h2>
    <div class="grid">
      <div>
        <div class="rowv"><div class="mut">Persona</div><div><?= h(trim(($inv['nombres'] ?? '') . ' ' . ($inv['apellido_paterno'] ?? '') . ' ' . ($inv['apellido_materno'] ?? ''))) ?: ('ID ' . (int) $inv['persona_id']) ?></div></div>
        <div class="rowv"><div class="mut">DNI</div><div><?= h(dash($inv['num_doc'] ?? '')) ?></div></div>
        <div class="rowv"><div class="mut">Sexo</div><div><?= h(dash($inv['sexo'] ?? '')) ?></div></div>
        <div class="rowv"><div class="mut">Fecha nac.</div><div><?= h(dash($inv['fecha_nacimiento'] ?? '')) ?><?= !empty($inv['fecha_nacimiento']) ? (' - ' . edad_ref($inv['fecha_nacimiento'], $inv['fecha_accidente'] ?? '') . ' anios') : '' ?></div></div>
        <div class="rowv"><div class="mut">Rol</div><div><?= h(dash($inv['rol_nombre'] ?? '')) ?><?= $inv['rol_reqveh'] ? '<span class="chip">requiere vehiculo</span>' : '' ?></div></div>
        <div class="rowv"><div class="mut">Vehiculo</div><div><?= !empty($inv['veh_placa']) ? h(trim(($inv['veh_placa'] ?? '') . ' ' . ($inv['veh_marca'] ?? '') . ' ' . ($inv['veh_modelo'] ?? '') . ' ' . ($inv['veh_color'] ?? '') . ' ' . ($inv['veh_anio'] ?? ''))) : '-' ?></div></div>
        <div class="rowv"><div class="mut">Lesion</div><div><?= h(dash($inv['lesion'] ?? '')) ?></div></div>
      </div>

      <div>
        <div class="rowv"><div class="mut">Accidente</div><div>#<?= (int) $inv['accidente_id'] ?> - <?= h(fmt_dt($inv['fecha_accidente'] ?? '')) ?></div></div>
        <div class="rowv"><div class="mut">Lugar</div><div><?= h(dash($inv['lugar_accidente'] ?? '')) ?></div></div>
        <div style="margin-top:12px">
          <div class="mut">Documentos</div>
          <div class="counts">
            <div class="count">LC: <?= $totalCounts['lc'] ?></div>
            <div class="count">RML: <?= $totalCounts['rml'] ?></div>
            <div class="count">Dosaje: <?= $totalCounts['dos'] ?></div>
            <div class="count">Manifestaciones: <?= $totalCounts['man'] ?></div>
            <div class="count">Occiso: <?= $totalCounts['occ'] ?></div>
          </div>
        </div>
        <div style="margin-top:12px">
          <div class="mut">Observaciones</div>
          <div class="summary-pre"><?= h(dash($inv['observaciones'] ?? '')) ?></div>
        </div>
      </div>
    </div>

    <div style="margin-top:12px">
      <div class="mut">Resumen</div>
      <div id="resumenBox" class="summary-pre"><?= h(make_summary($inv)) ?></div>
    </div>
  </div>

  <?php if ($veh): ?>
    <div class="card">
      <h3 style="margin-top:0">Vehiculo asociado</h3>
      <div class="veh-box">
        <div style="font-weight:800"><?= h(dash($veh['placa'] ?? '')) ?></div>
        <div>
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div><strong>Marca:</strong> <?= h(dash($veh['marca'] ?? $inv['veh_marca'] ?? '')) ?></div>
            <div><strong>Modelo:</strong> <?= h(dash($veh['modelo'] ?? $inv['veh_modelo'] ?? '')) ?></div>
            <div><strong>Anio:</strong> <?= h(dash($veh['anio'] ?? $inv['veh_anio'] ?? '')) ?></div>
            <div><strong>Color:</strong> <?= h(dash($veh['color'] ?? $inv['veh_color'] ?? '')) ?></div>
          </div>

          <?php
          $extra = [];
          foreach (['tipo', 'motor', 'chasis', 'cilindrada', 'placa_anterior', 'observaciones'] as $ef) {
              if (isset($veh[$ef]) && trim((string) $veh[$ef]) !== '') {
                  $extra[$ef] = $veh[$ef];
              }
          }
          ?>
          <?php if (!empty($extra)): ?>
            <div style="margin-top:8px">
              <?php foreach ($extra as $k => $v): ?>
                <div><strong><?= h(ucfirst(str_replace('_', ' ', $k))) ?>:</strong> <?= h($v) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div style="margin-top:12px">
            <strong>Propietario:</strong>
            <?php if ($propietario): ?>
              <div style="margin-top:6px" class="summary-pre">
                <?= h(trim(($propietario['nombres'] ?? '') . ' ' . ($propietario['apellido_paterno'] ?? '') . ' ' . ($propietario['apellido_materno'] ?? ''))) ?> (DNI: <?= h(dash($propietario['num_doc'] ?? '')) ?>)
                <?php if (!empty($propietario['fecha_nacimiento'])): ?> - <?= edad_ref($propietario['fecha_nacimiento'], $inv['fecha_accidente'] ?? '') ?> anios<?php endif; ?>
              </div>
              <div style="margin-top:8px">
                <a class="btn ghost" href="persona_leer.php?id=<?= (int) $propietario['id'] ?>">Ver propietario</a>
              </div>
            <?php else: ?>
              <div style="margin-top:6px" class="summary-pre">No se encontro un propietario asociado en la base de datos.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($show['lc']): ?>
    <div class="card">
      <h3 style="margin-top:0">Licencias de conducir</h3>
      <?php if (empty($lcList)): ?>
        <div class="doc-card">No hay licencias registradas para esta persona.</div>
      <?php else: ?>
        <?php foreach ($lcList as $lc): ?>
          <div class="doc-card" id="lc-<?= (int) $lc['id'] ?>">
            <div class="doc-row">
              <div>
                <strong>Clase:</strong> <?= h(dash($lc['clase'] ?? '')) ?> <?= $lc['categoria'] ? ('<span class="chip">Cat ' . h($lc['categoria']) . '</span>') : '' ?><br>
                <small>Nro: <?= h(dash($lc['numero'] ?? '')) ?> - Expedido por: <?= h(dash($lc['expedido_por'] ?? '')) ?> - Vigente: <?= h(dash($lc['vigente_desde'] ?? '')) ?> a <?= h(dash($lc['vigente_hasta'] ?? '')) ?></small>
              </div>
              <div style="display:flex;gap:8px">
                <button class="btn" onclick="copyTextFromId('lc-<?= (int) $lc['id'] ?>')">Copiar</button>
                <a class="btn ghost" href="doc_lc_editar.php?id=<?= (int) $lc['id'] ?>">Editar</a>
              </div>
            </div>
            <?php if (trim((string) ($lc['restricciones'] ?? '')) !== ''): ?>
              <div style="margin-top:8px" class="summary-pre"><?= h($lc['restricciones']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($show['rml']): ?>
    <div class="card">
      <h3 style="margin-top:0">RML</h3>
      <?php if (empty($rmlList)): ?>
        <div class="doc-card">No hay RML registrados para esta persona.</div>
      <?php else: ?>
        <?php foreach ($rmlList as $r): ?>
          <div class="doc-card" id="rml-<?= (int) $r['id'] ?>">
            <div class="doc-row">
              <div>
                <strong>Nro:</strong> <?= h(dash($r['numero'] ?? '')) ?> - <strong>Fecha:</strong> <?= h(dash($r['fecha'] ?? '')) ?><br>
                <small>Incapacidad: <?= h(dash($r['incapacidad_medico'] ?? '')) ?> - Atencion: <?= h(dash($r['atencion_facultativo'] ?? '')) ?></small>
              </div>
              <div style="display:flex;gap:8px">
                <button class="btn" onclick="copyTextFromId('rml-<?= (int) $r['id'] ?>')">Copiar</button>
                <a class="btn ghost" href="documento_rml_editar.php?id=<?= (int) $r['id'] ?>">Editar</a>
              </div>
            </div>
            <?php if (trim((string) ($r['observaciones'] ?? '')) !== ''): ?>
              <div style="margin-top:8px" class="summary-pre"><?= h($r['observaciones']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($show['dos']): ?>
    <div class="card">
      <h3 style="margin-top:0">Dosaje etilico</h3>
      <?php if (empty($dosList)): ?>
        <div class="doc-card">No hay dosajes registrados para esta persona.</div>
      <?php else: ?>
        <?php foreach ($dosList as $d): ?>
          <div class="doc-card" id="dos-<?= (int) $d['id'] ?>">
            <div class="doc-row">
              <div>
                <strong>Nro:</strong> <?= h(dash($d['numero'] ?? '')) ?> - <strong>Registro:</strong> <?= h(dash($d['numero_registro'] ?? '')) ?><br>
                <small>Extraccion: <?= h(dash($d['fecha_extraccion'] ?? '')) ?> - Resultado: <?= h(dash($d['resultado_cualitativo'] ?? '')) ?><?= !empty($d['resultado_cuantitativo']) ? (' - ' . h($d['resultado_cuantitativo']) . ' g/L') : '' ?></small>
              </div>
              <div style="display:flex;gap:8px">
                <button class="btn" onclick="copyTextFromId('dos-<?= (int) $d['id'] ?>')">Copiar</button>
                <a class="btn ghost" href="documento_dosaje_editar.php?id=<?= (int) $d['id'] ?>">Editar</a>
              </div>
            </div>
            <?php if (trim((string) ($d['observaciones'] ?? '')) !== ''): ?>
              <div style="margin-top:8px" class="summary-pre"><?= h($d['observaciones']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($show['man']): ?>
    <div class="card">
      <h3 style="margin-top:0">Manifestaciones</h3>
      <?php if (empty($manList)): ?>
        <div class="doc-card">No hay manifestaciones registradas para esta persona en este accidente.</div>
      <?php else: ?>
        <?php foreach ($manList as $m): ?>
          <div class="doc-card" id="man-<?= (int) $m['id'] ?>">
            <div class="doc-row">
              <div>
                <strong>Modalidad:</strong> <?= h(dash($m['modalidad'] ?? '')) ?> - <strong>Fecha:</strong> <?= h(dash($m['fecha'] ?? '')) ?><br>
                <small>Horario: <?= h(dash($m['horario_inicio'] ?? '--:--')) ?> a <?= h(dash($m['hora_termino'] ?? '--:--')) ?></small>
              </div>
              <div style="display:flex;gap:8px">
                <button class="btn" onclick="copyTextFromId('man-<?= (int) $m['id'] ?>')">Copiar</button>
                <a class="btn ghost" href="documento_manifestacion_editar.php?id=<?= (int) $m['id'] ?>">Editar</a>
              </div>
            </div>
            <?php if (trim((string) ($m['observaciones'] ?? '')) !== ''): ?>
              <div style="margin-top:8px" class="summary-pre"><?= h($m['observaciones']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($show['occ']): ?>
    <div class="card">
      <h3 style="margin-top:0">Occiso</h3>
      <?php if (empty($occList)): ?>
        <div class="doc-card">No hay documentos de occiso para esta persona en este accidente.</div>
      <?php else: ?>
        <?php foreach ($occList as $o): ?>
          <div class="doc-card" id="occ-<?= (int) $o['id'] ?>">
            <div class="doc-row">
              <div>
                <strong>Lugar:</strong> <?= h(dash($o['lugar_levantamiento'] ?? '')) ?> - <strong>Fecha/Hora:</strong> <?= h(dash($o['fecha_levantamiento'] ?? '')) ?> <?= h((string) ($o['hora_levantamiento'] ?? '')) ?><br>
                <small>Protocolo: <?= h(dash($o['numero_protocolo'] ?? '')) ?></small>
              </div>
              <div style="display:flex;gap:8px">
                <button class="btn" onclick="copyTextFromId('occ-<?= (int) $o['id'] ?>')">Copiar</button>
                <a class="btn ghost" href="documento_occiso_editar.php?id=<?= (int) $o['id'] ?>">Editar</a>
              </div>
            </div>
            <?php if (trim((string) ($o['observaciones_levantamiento'] ?? '')) !== ''): ?>
              <div style="margin-top:8px" class="summary-pre"><?= h($o['observaciones_levantamiento']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($hiddenSections)): ?>
    <div class="card">
      <h3 style="margin-top:0">Otros documentos</h3>
      <p style="margin:6px 0;color:var(--mut)">Estos documentos existen para la persona, pero no son prioritarios segun el rol detectado: <?= h(dash($inv['rol_nombre'] ?? '')) ?></p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="btn ghost" onclick="document.getElementById('otherBox').style.display = (document.getElementById('otherBox').style.display==='none' ? 'block' : 'none')">Mostrar / Ocultar</button>
      </div>

      <div id="otherBox" class="other-section" style="display:none;margin-top:10px">
        <?php if (isset($hiddenSections['lc'])): ?>
          <h4>Licencias</h4>
          <?php foreach ($hiddenSections['lc'] as $lc): ?>
            <div class="doc-card" id="hlc-<?= (int) $lc['id'] ?>">
              <div class="doc-row">
                <div><strong>Clase:</strong> <?= h(dash($lc['clase'] ?? '')) ?> - Nro <?= h(dash($lc['numero'] ?? '')) ?></div>
                <div><button class="btn" onclick="copyTextFromId('hlc-<?= (int) $lc['id'] ?>')">Copiar</button></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (isset($hiddenSections['rml'])): ?>
          <h4>RML</h4>
          <?php foreach ($hiddenSections['rml'] as $r): ?>
            <div class="doc-card" id="hrml-<?= (int) $r['id'] ?>">
              <div class="doc-row">
                <div><strong>Nro:</strong> <?= h(dash($r['numero'] ?? '')) ?> - <?= h(dash($r['fecha'] ?? '')) ?></div>
                <div><button class="btn" onclick="copyTextFromId('hrml-<?= (int) $r['id'] ?>')">Copiar</button></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (isset($hiddenSections['dos'])): ?>
          <h4>Dosajes</h4>
          <?php foreach ($hiddenSections['dos'] as $d): ?>
            <div class="doc-card" id="hdos-<?= (int) $d['id'] ?>">
              <div class="doc-row">
                <div><strong>Nro:</strong> <?= h(dash($d['numero'] ?? '')) ?> - <?= h(dash($d['fecha_extraccion'] ?? '')) ?></div>
                <div><button class="btn" onclick="copyTextFromId('hdos-<?= (int) $d['id'] ?>')">Copiar</button></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (isset($hiddenSections['man'])): ?>
          <h4>Manifestaciones</h4>
          <?php foreach ($hiddenSections['man'] as $m): ?>
            <div class="doc-card" id="hman-<?= (int) $m['id'] ?>">
              <div class="doc-row">
                <div><?= h(dash($m['modalidad'] ?? '')) ?> - <?= h(dash($m['fecha'] ?? '')) ?></div>
                <div><button class="btn" onclick="copyTextFromId('hman-<?= (int) $m['id'] ?>')">Copiar</button></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (isset($hiddenSections['occ'])): ?>
          <h4>Occiso</h4>
          <?php foreach ($hiddenSections['occ'] as $o): ?>
            <div class="doc-card" id="hocc-<?= (int) $o['id'] ?>">
              <div class="doc-row">
                <div><?= h(dash($o['lugar_levantamiento'] ?? '')) ?> - <?= h(dash($o['fecha_levantamiento'] ?? '')) ?></div>
                <div><button class="btn" onclick="copyTextFromId('hocc-<?= (int) $o['id'] ?>')">Copiar</button></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
function copyResumen(){
  const el = document.getElementById('resumenBox');
  const text = el ? el.innerText.trim() : '';
  if(!text){ alert('No hay resumen para copiar'); return; }
  navigator.clipboard.writeText(text).then(()=>alert('Resumen copiado')).catch(()=>fallbackCopy(text));
}
function copyTextFromId(id){
  const el = document.getElementById(id);
  if(!el){ alert('No hay contenido para copiar'); return; }
  const text = el.innerText.trim();
  navigator.clipboard.writeText(text).then(()=>alert('Texto copiado')).catch(()=>fallbackCopy(text));
}
function fallbackCopy(t){
  const ta = document.createElement('textarea');
  ta.value = t;
  document.body.appendChild(ta);
  ta.select();
  try{ document.execCommand('copy'); alert('Copiado'); }catch(e){ alert('No se pudo copiar'); }
  document.body.removeChild(ta);
}
</script>
</body>
</html>
