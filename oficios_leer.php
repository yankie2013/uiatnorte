<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\OficioRepository;
use App\Services\OficioService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new OficioService(new OficioRepository($pdo));
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$embed = (int) ($_GET['embed'] ?? 0) === 1;
$returnTo = trim((string) ($_GET['return_to'] ?? ''));
$detail = $id > 0 ? $service->detalle($id) : null;
if ($id <= 0 || $detail === null) {
    header('Location: oficios_listar.php');
    exit;
}

$persona = trim((string) (($detail['per_nombres'] ?? '') . ' ' . ($detail['per_ap'] ?? '') . ' ' . ($detail['per_am'] ?? '')));
if ($persona === '') {
    $persona = trim((string) ($detail['persona_destino_manual'] ?? ''));
}
$personaFallecida = trim((string) (($detail['fall_nombres'] ?? '') . ' ' . ($detail['fall_ap'] ?? '') . ' ' . ($detail['fall_am'] ?? '')));
$vehiculoVinculado = trim((string) (($detail['veh_ut'] ?? '') !== '' ? ($detail['veh_ut'] . ' - ') : '') . ($detail['veh_placa'] ?? ''));
$listarHref = 'oficios_listar.php' . (!empty($detail['accidente_id']) ? ('?accidente_id=' . urlencode((string) $detail['accidente_id'])) : '');
if ($returnTo === '') {
    $returnTo = $listarHref;
}

if (!$embed) {
    include __DIR__ . '/sidebar.php';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Oficio N° <?= h($detail['numero']) ?>/<?= h($detail['anio']) ?></title>
<link rel="stylesheet" href="style_mushu.css">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#3b82f6}}
body{background:var(--page);color:var(--text)}body.is-embed{margin:0}.wrap{max-width:980px;margin:24px auto;padding:16px}body.is-embed .wrap{margin:0 auto;padding:14px}.toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:700;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.btn.danger{color:#b91c1c}.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(29,78,216,.12);color:var(--primary);font-weight:700}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:18px}.row{display:grid;grid-template-columns:190px 1fr;gap:12px;padding:8px 0;border-bottom:1px solid var(--border)}.row:last-child{border-bottom:0}.label{color:var(--muted);font-weight:700}.text-block{white-space:pre-wrap;line-height:1.45;padding:12px;border:1px solid var(--border);border-radius:12px;margin-top:8px;background:rgba(148,163,184,.04)}@media(max-width:760px){.row{grid-template-columns:1fr}}
</style>
</head>
<body class="<?= $embed ? 'is-embed' : '' ?>">
<div class="wrap">
<div class="toolbar">
  <?php if ($embed): ?>
    <button class="btn" type="button" onclick="try{window.parent&&window.parent.postMessage({type:'oficio.close'},'*');}catch(e){}">Cerrar</button>
  <?php else: ?>
    <a class="btn" href="<?= h($listarHref) ?>">← Volver</a>
  <?php endif; ?>
  <?php if (!$embed && !empty($detail['accidente_id'])): ?>
    <a class="btn" href="Dato_General_accidente.php?accidente_id=<?= urlencode((string) $detail['accidente_id']) ?>">Datos generales SIDPOL</a>
  <?php endif; ?>
  <a class="btn primary" href="oficios_editar.php?id=<?= h($id) ?>&embed=<?= $embed ? 1 : 0 ?>&return_to=<?= urlencode($returnTo) ?>">Editar</a>
  <a class="btn danger" href="oficios_eliminar.php?id=<?= h($id) ?>&embed=<?= $embed ? 1 : 0 ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
</div>

  <h1>Oficio N° <?= h($detail['numero']) ?>/<?= h($detail['anio']) ?> <span class="badge"><?= h($detail['estado']) ?></span></h1>

  <div class="card">
    <div class="row"><div class="label">Fecha de emisión</div><div><?= h($detail['fecha_emision']) ?></div></div>
    <div class="row"><div class="label">Entidad destino</div><div><?= h($detail['entidad'] ?? '-') ?></div></div>
    <div class="row"><div class="label">Subentidad</div><div><?= h($detail['subentidad'] ?? '-') ?></div></div>
    <div class="row"><div class="label">Persona destino</div><div><?= $persona !== '' ? h($persona) : '-' ?></div></div>
    <div class="row"><div class="label">Grado y cargo</div><div><?= h($detail['grado_cargo_nombre'] ?? '-') ?></div></div>
    <div class="row"><div class="label">Accidente</div><div><?= !empty($detail['registro_sidpol']) ? h('SIDPOL ' . $detail['registro_sidpol']) : h('Accidente ' . ($detail['accidente_id'] ?? '-')) ?><?php if (!empty($detail['lugar'])): ?> - <?= h($detail['lugar']) ?><?php endif; ?><?php if (!empty($detail['fecha_accidente'])): ?> (<?= h($detail['fecha_accidente']) ?>)<?php endif; ?></div></div>
    <div class="row"><div class="label">Tipo de asunto</div><div><?= h($detail['asunto_tipo'] ?? '-') ?></div></div>
    <div class="row"><div class="label">Categoría</div><div><?= h($detail['categoria'] ?? '') ?: '-' ?></div></div>
    <div class="row"><div class="label">Asunto</div><div><?= h($detail['asunto_nombre'] ?? '-') ?></div></div>
    <div class="row"><div class="label">Nombre oficial del año</div><div><?= h(trim((string) (($detail['anio_nom'] ?? '') . ' - ' . ($detail['nombre_anio'] ?? '')))) ?></div></div>
    <div class="row"><div class="label">Referencia</div><div><?= h($detail['referencia_texto'] ?? '') ?: '-' ?></div></div>
    <div class="row"><div class="label">Vehículo vinculado</div><div><?= $vehiculoVinculado !== '' ? h($vehiculoVinculado) : (!empty($detail['involucrado_vehiculo_id']) ? ('ID ' . h($detail['involucrado_vehiculo_id'])) : '-') ?></div></div>
    <div class="row"><div class="label">Persona fallecida vinculada</div><div><?= $personaFallecida !== '' ? h($personaFallecida) : (!empty($detail['involucrado_persona_id']) ? ('ID ' . h($detail['involucrado_persona_id'])) : '-') ?></div></div>
    <div style="margin-top:14px;">
      <div class="label">Motivo / contexto</div>
      <div class="text-block"><?= h($detail['motivo'] ?? '') ?></div>
    </div>
    <?php if (trim((string) ($detail['diligencias_solicitadas'] ?? '')) !== ''): ?>
    <div style="margin-top:14px;">
      <div class="label">Diligencias cuya informacion se solicita</div>
      <div class="text-block"><?= nl2br(h($detail['diligencias_solicitadas'])) ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
