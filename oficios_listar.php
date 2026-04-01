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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === 'estado') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $service->changeEstado((int) ($_POST['id'] ?? 0), (string) ($_POST['estado'] ?? ''));
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$anio = trim((string) ($_GET['anio'] ?? ''));
$entidadId = trim((string) ($_GET['entidad_id'] ?? ''));
$sidpol = trim((string) ($_GET['sidpol'] ?? ''));
$accidenteId = (int) ($_GET['accidente_id'] ?? 0);
$estado = trim((string) ($_GET['estado'] ?? ''));
$msg = trim((string) ($_GET['msg'] ?? ''));

$filters = [
    'q' => $q,
    'anio' => $anio,
    'entidad_id' => $entidadId,
    'sidpol' => $sidpol,
    'accidente_id' => $accidenteId,
    'estado' => $estado,
];
$ctx = $service->listado($filters);
$rows = $ctx['rows'];
$returnTo = $_SERVER['REQUEST_URI'] ?? build_url([]);

function build_url(array $overrides): string
{
    $query = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') unset($query[$key]);
        else $query[$key] = $value;
    }
    $qs = http_build_query($query);
    return basename(__FILE__) . ($qs !== '' ? ('?' . $qs) : '');
}

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Oficios | Listado</title>
<link rel="stylesheet" href="style_mushu.css">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#3b82f6}}
body{background:var(--page);color:var(--text)}.wrap{max-width:1320px;margin:24px auto;padding:16px}.toolbar{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:700;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:18px}.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}.filters input,.filters select{padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text)}table{width:100%;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid var(--border);vertical-align:top}th{text-align:left;color:var(--muted);font-size:.9rem}.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(29,78,216,.12);color:var(--primary);font-weight:700}.actions{display:flex;gap:8px;flex-wrap:wrap}.state{padding:8px 10px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text)}.small{font-size:.9rem;color:var(--muted)}.tools{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px}.tool{display:inline-block;padding:6px 9px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);text-decoration:none;font-size:.85rem}.ok{margin-bottom:12px;padding:10px 12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:10px}@media(max-width:780px){table,thead,tbody,tr,td,th{display:block}thead{display:none}td{padding:10px 0}td::before{content:attr(data-label);display:block;color:var(--muted);font-size:.84rem;margin-bottom:4px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0;">Oficios</h1>
      <div class="small">Listado operativo del módulo.</div>
    </div>
    <div class="actions">
      <?php if ($accidenteId > 0): ?><a class="btn" href="Dato_General_accidente.php?accidente_id=<?= urlencode((string) $accidenteId) ?>">Datos generales SIDPOL</a><?php endif; ?>
      <?php if ($accidenteId > 0): ?><a class="btn" href="oficio_peritaje_express.php?accidente_id=<?= urlencode((string) $accidenteId) ?>&return_to=<?= urlencode($returnTo) ?>">Peritaje rápido</a><?php endif; ?>
      <a class="btn primary" href="oficios_nuevo.php<?= $accidenteId > 0 ? ('?accidente_id=' . urlencode((string) $accidenteId)) : ($sidpol !== '' ? ('?sidpol=' . urlencode($sidpol)) : '') ?>">+ Nuevo oficio</a>
    </div>
  </div>

  <div class="card">
    <?php if ($msg === 'eliminado'): ?><div class="ok">Oficio eliminado correctamente.</div><?php endif; ?>
    <form method="get" class="filters">
      <?php if ($accidenteId > 0): ?><input type="hidden" name="accidente_id" value="<?= h($accidenteId) ?>"><?php endif; ?>
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar número, SIDPOL, asunto, referencia, placa..." style="min-width:260px;">
      <input type="number" name="anio" value="<?= h($anio) ?>" placeholder="Año">
      <select name="entidad_id">
        <option value="">Entidad</option>
        <?php foreach ($ctx['entidades'] as $entidad): ?>
          <option value="<?= h($entidad['id']) ?>" <?= $entidadId !== '' && (string) $entidad['id'] === $entidadId ? 'selected' : '' ?>><?= h($entidad['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="sidpol" value="<?= h($sidpol) ?>" placeholder="SIDPOL">
      <select name="estado">
        <option value="">Estado</option>
        <?php foreach ($ctx['estados'] as $item): ?>
          <option value="<?= h($item) ?>" <?= $estado === $item ? 'selected' : '' ?>><?= h($item) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Filtrar</button>
      <a class="btn" href="<?= h(build_url(['q' => null, 'anio' => null, 'entidad_id' => null, 'sidpol' => null, 'estado' => null])) ?>">Limpiar</a>
    </form>

    <div class="small" style="margin-bottom:10px;">Mostrando <?= count($rows) ?> registro(s).</div>

    <table>
      <thead>
        <tr>
          <th>SIDPOL</th>
          <th>Número</th>
          <th>Entidad / Asunto</th>
          <th>Referencia</th>
          <th>Fecha</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="small">Sin resultados.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $txt = strtolower((string) (($row['asunto_nombre'] ?? '') . ' ' . ($row['detalle'] ?? '')));
            $isRemitir = strtoupper(trim((string) ($row['asunto_tipo'] ?? ''))) === 'REMITIR' || str_contains($txt, 'remitir diligencias') || str_contains($txt, 'remitir diligencia');
            $isDosaje = str_contains($txt, 'dosaje et') || str_contains($txt, 'resultado dosaje') || preg_match('/resultado.*dosaje/i', $txt);
            $isPeritaje = str_contains($txt, 'peritaje de constat');
          ?>
          <tr>
            <td data-label="SIDPOL"><strong><?= h($row['registro_sidpol'] ?: '-') ?></strong><div class="small">Acc. <?= h($row['accid'] ?: '-') ?></div></td>
            <td data-label="Número"><div><?= h($row['numero']) ?>/<?= h($row['anio']) ?></div><div class="small">ID <?= h($row['id']) ?></div></td>
            <td data-label="Entidad / Asunto"><div><?= h($row['entidad'] ?: '-') ?></div><div class="small"><?= h($row['asunto_nombre'] ?: '-') ?></div><?php if (!empty($row['veh_placa'])): ?><div class="small">Vehículo: <?= h(trim(($row['veh_ut'] ?: '') . ' ' . $row['veh_placa'])) ?></div><?php endif; ?></td>
            <td data-label="Referencia"><div class="small"><?= h(mb_strimwidth((string) ($row['detalle'] ?: ''), 0, 110, '...')) ?></div></td>
            <td data-label="Fecha"><?= h($row['fecha_emision']) ?></td>
            <td data-label="Estado"><select class="state js-state" data-id="<?= h($row['id']) ?>"><?php foreach ($ctx['estados'] as $item): ?><option value="<?= h($item) ?>" <?= (string) ($row['estado'] ?? '') === $item ? 'selected' : '' ?>><?= h($item) ?></option><?php endforeach; ?></select></td>
            <td data-label="Acciones">
              <div class="tools">
                <?php if ($isRemitir): ?><a class="tool" target="_blank" rel="noopener" href="oficio_remitir_diligencia.php?oficio_id=<?= h($row['id']) ?><?= !empty($row['accid']) ? '&accidente_id=' . h($row['accid']) : '' ?>">Remitir</a><?php endif; ?>
                <?php if ($isDosaje): ?><a class="tool" target="_blank" rel="noopener" href="oficio_resultado_dosaje.php?oficio_id=<?= h($row['id']) ?>">Dosaje</a><?php endif; ?>
                <?php if ($isPeritaje): ?><a class="tool" target="_blank" rel="noopener" href="oficio_peritaje.php?oficio_id=<?= h($row['id']) ?>">Peritaje</a><?php endif; ?>
              </div>
              <div class="actions">
                <a class="btn" href="oficios_leer.php?id=<?= h($row['id']) ?>">Ver</a>
                <a class="btn" href="oficios_editar.php?id=<?= h($row['id']) ?>">Editar</a>
                <form action="oficios_eliminar.php" method="post" style="display:inline" onsubmit="return confirm('¿Eliminar el oficio?');">
                  <input type="hidden" name="id" value="<?= h($row['id']) ?>">
                  <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                  <button class="btn" type="submit">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
document.querySelectorAll('.js-state').forEach(function(select){
  select.addEventListener('change', async function(){
    const previous = this.dataset.prev || this.value;
    this.disabled = true;
    try {
      const response = await fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},
        body: new URLSearchParams({ajax:'estado', id:this.dataset.id, estado:this.value})
      });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.msg || 'No se pudo actualizar el estado.');
      this.dataset.prev = this.value;
    } catch (error) {
      alert(error.message || 'No se pudo actualizar el estado.');
      this.value = previous;
    } finally {
      this.disabled = false;
    }
  });
  select.dataset.prev = select.value;
});
</script>
</body>
</html>
