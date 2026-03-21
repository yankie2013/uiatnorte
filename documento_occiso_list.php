<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Repositories\DocumentoOccisoRepository;
use App\Services\DocumentoOccisoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function q($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }

$service = new DocumentoOccisoService(new DocumentoOccisoRepository($pdo));
$persona_id = (int) q('persona_id', 0);
$accidente_id = (int) q('accidente_id', 0);
$embed = (int) q('embed', 0);
$return_to = q('return_to', '');
$msg = q('msg', '');
$rows = $service->listado($persona_id, $accidente_id);
$baseReturn = $return_to ?: ($_SERVER['REQUEST_URI'] ?? 'documento_occiso_list.php');

function persona_etq($r){
    $ns = trim(($r['nombres'] ?? '') . ' ' . ($r['apellido_paterno'] ?? '') . ' ' . ($r['apellido_materno'] ?? ''));
    return $ns !== '' ? $ns : ('ID ' . $r['persona_id']);
}
function acc_etq($r){
    $parts = [];
    if (!empty($r['registro_sidpol'])) $parts[] = $r['registro_sidpol'];
    $parts[] = !empty($r['fecha_accidente']) ? date('Y-m-d H:i', strtotime($r['fecha_accidente'])) : 's/f';
    if (!empty($r['lugar_accidente'])) $parts[] = $r['lugar_accidente'];
    return '#' . $r['accidente_id'] . ' - ' . implode(' - ', $parts);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Occisos - Listado</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
.wrap{max-width:1120px;margin:20px auto;padding:0 14px}
.card{padding:14px !important}
.table-wrap{overflow:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:10px 8px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left;vertical-align:top}
th{opacity:.8;font-size:12px}
.empty{padding:14px 8px;opacity:.75}
.actions{display:flex;gap:6px;flex-wrap:wrap}
.ok{margin-bottom:12px;padding:10px 12px;border:1px solid #167a59;background:#0c3f2d;color:#c6ffe3;border-radius:10px}
</style>
</head>
<body>
<div class="wrap">
  <div class="bar">
    <a class="btn small" href="<?= $embed ? h($baseReturn) : 'javascript:history.back()' ?>"><?= $embed ? 'Cerrar' : 'Volver' ?></a>
    <span></span>
    <a class="btn small" href="documento_occiso_nuevo.php?persona_id=<?= $persona_id ?>&accidente_id=<?= $accidente_id ?>&embed=<?= $embed ?>&return_to=<?= urlencode($baseReturn) ?>">Nuevo</a>
  </div>

  <h1>Documentos de Occiso</h1>
  <?php if($msg === 'eliminado'): ?><div class="ok">Documento eliminado correctamente.</div><?php endif; ?>
  <div class="card"><div class="table-wrap"><table>
    <thead><tr><th>ID</th><th>Persona</th><th>Accidente</th><th>Levantamiento</th><th>Protocolo</th><th>Lugar</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php if(!$rows): ?>
      <tr><td colspan="7" class="empty">No hay registros.</td></tr>
    <?php else: foreach($rows as $r):
      $lev = trim(($r['fecha_levantamiento'] ?: '') . (($r['hora_levantamiento'] ?? '') ? (' ' . $r['hora_levantamiento']) : ''));
      $prot = trim(($r['fecha_protocolo'] ?: '') . (($r['hora_protocolo'] ?? '') ? (' ' . $r['hora_protocolo']) : ''));
      $base = 'persona_id=' . (int)$r['persona_id'] . '&accidente_id=' . (int)$r['accidente_id'] . '&embed=' . $embed . '&return_to=' . urlencode($baseReturn);
    ?>
      <tr>
        <td>#<?= (int)$r['id'] ?></td>
        <td><?= h(persona_etq($r)) ?></td>
        <td><?= h(acc_etq($r)) ?></td>
        <td><?= h($lev ?: '-') ?></td>
        <td><?= h($prot ?: '-') ?></td>
        <td><?= h($r['lugar_levantamiento'] ?: '-') ?></td>
        <td class="actions">
          <a class="btn micro" href="documento_occiso_ver.php?id=<?= (int)$r['id'] ?>&<?= $base ?>">Ver</a>
          <a class="btn micro" href="documento_occiso_editar.php?id=<?= (int)$r['id'] ?>&<?= $base ?>">Editar</a>
          <a class="btn micro" href="documento_occiso_eliminar.php?id=<?= (int)$r['id'] ?>&<?= $base ?>">Eliminar</a>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table></div></div>
</div>
<?php if($embed): ?>
<script>
window.addEventListener('message', (ev) => {
  const t = (ev.data || {}).type || '';
  if (t === 'occiso.saved' || t === 'occiso.updated' || t === 'occiso.deleted') {
    location.reload();
  }
});
</script>
<?php endif; ?>
</body>
</html>
