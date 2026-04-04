<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\DocumentoRecibidoRepository;
use App\Services\DocumentoRecibidoService;

header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

$service = new DocumentoRecibidoService(new DocumentoRecibidoRepository($pdo));
$msg = trim((string)($_GET['msg'] ?? ''));
$filters = [
    'accidente_id' => $_GET['accidente_id'] ?? '',
    'tipo_documento' => trim((string) ($_GET['tipo_documento'] ?? '')),
    'estado' => $_GET['estado'] ?? '',
    'q' => trim((string) ($_GET['q'] ?? '')),
];
$ctx = $service->listado($filters);
$rows = $ctx['rows'];
$returnTo = $_SERVER['REQUEST_URI'] ?? 'documento_recibido_listar.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Listado Documentos Recibidos</title>
<style>
:root{--bg:#fafafa;--panel:#ffffff;--text:#111827;--muted:#6b7280;--border:#e5e7eb;--primary:#0b84ff;--radius:10px;--maxw:1100px}
@media (prefers-color-scheme: dark){:root{--bg:#071025;--panel:#071827;--text:#e6eef8;--muted:#9aa7bf;--border:#233046;--primary:#4ea8ff}}
body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;background:var(--bg);color:var(--text);padding:18px}
.wrap{max-width:var(--maxw);margin:0 auto;background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:16px}
.head,.filters,.actions-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.head{justify-content:space-between;margin-bottom:12px}
.btn{padding:8px 12px;border-radius:8px;text-decoration:none;display:inline-flex;gap:8px;align-items:center;font-weight:600;border:1px solid var(--border);background:transparent;color:var(--text)}
.btn.primary{background:var(--primary);color:#fff;border-color:transparent}
.filters{margin-bottom:12px}.filters select,.filters input[type=text]{padding:8px 10px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text)}
table{width:100%;border-collapse:collapse;margin-top:8px;font-size:.95rem}thead th{text-align:left;font-size:.85rem;color:var(--muted);padding:10px;border-bottom:1px solid var(--border)}tbody td{padding:10px;border-bottom:1px dashed var(--border);vertical-align:top}.small{font-size:.85rem;color:var(--muted)}
.tag{padding:4px 8px;border-radius:999px;font-weight:700;font-size:.82rem;display:inline-block}.estado-pendiente{background:#fff4e5;color:#b45309}.estado-revisado{background:#ecfdf5;color:#065f46}.estado-archivado{background:#eef2ff;color:#3730a3}
.ok{margin:0 0 12px;padding:10px 12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:10px}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div><div style="font-weight:700;font-size:1.05rem;">Documentos recibidos</div><div class="small">Listado general del modulo.</div></div>
    <div class="head">
      <a class="btn" href="index.php">Panel</a>
      <a class="btn primary" href="documento_recibido_nuevo.php">Nuevo documento</a>
    </div>
  </div>

  <form method="GET" class="filters">
    <select name="accidente_id">
      <option value="">Filtrar por accidente</option>
      <?php foreach($ctx['accidentes'] as $a): ?>
        <option value="<?= h($a['id']) ?>" <?= ((string)$filters['accidente_id'] === (string)$a['id']) ? 'selected' : '' ?>><?= h($a['id']) ?> - <?= h($a['sidpol'] ?? '') ?><?= !empty($a['lugar']) ? (' - ' . h($a['lugar'])) : '' ?></option>
      <?php endforeach; ?>
    </select>
    <select name="tipo_documento">
      <option value="">Tipo documento</option>
      <?php foreach($ctx['tipos'] as $t): ?><option value="<?= h($t) ?>" <?= ($filters['tipo_documento'] === $t) ? 'selected' : '' ?>><?= h($t) ?></option><?php endforeach; ?>
    </select>
    <select name="estado">
      <option value="">Estado</option>
      <?php foreach($ctx['estados'] as $e): ?><option value="<?= h($e) ?>" <?= ($filters['estado'] === $e) ? 'selected' : '' ?>><?= h($e) ?></option><?php endforeach; ?>
    </select>
    <input type="text" name="q" placeholder="Buscar asunto, entidad, numero, contenido..." value="<?= h($filters['q']) ?>">
    <button type="submit" class="btn">Buscar</button>
    <a href="documento_recibido_listar.php" class="btn">Limpiar</a>
  </form>

  <div class="small">Mostrando <strong><?= count($rows) ?></strong> registro(s).</div>
  <?php if($msg === 'eliminado'): ?><div class="ok">Documento eliminado correctamente.</div><?php endif; ?>
  <table>
    <thead><tr><th>Recepcion / Documento</th><th>Asunto / Entidad</th><th>Tipo / Numero</th><th>Accidente</th><th>Oficio</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="small">No se encontraron documentos.</td></tr><?php endif; ?>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><div><strong>Recepcion:</strong> <?= h($r['fecha_recepcion_resuelta'] ?? $r['fecha_recepcion'] ?? $r['fecha'] ?? '') ?></div><div class="small">Documento: <?= h($r['fecha_documento_resuelta'] ?? $r['fecha_documento'] ?? $r['fecha'] ?? '-') ?></div><div class="small">ID <?= h($r['id']) ?></div></td>
          <td><div><?= h($r['asunto']) ?></div><div class="small"><?= h($r['entidad_persona']) ?></div><?php if(!empty($r['contenido'])): ?><div class="small" style="margin-top:6px;"><?= h(mb_strimwidth((string)$r['contenido'],0,160,'...')) ?></div><?php endif; ?></td>
          <td><div><?= h($r['tipo_documento']) ?></div><div class="small">Nro. <?= h($r['numero_documento']) ?></div></td>
          <td><?php if($r['accidente_id']): ?><div class="small">#<?= h($r['accidente_id']) ?> - <?= h($r['accidente_sidpol'] ?: '-') ?></div><div class="small"><?= h((string)($r['accidente_lugar'] ?? '')) ?></div><?php else: ?><div class="small">Sin accidente</div><?php endif; ?></td>
          <td><?php if($r['referencia_oficio_id']): ?><div class="small">Oficio <?= h($r['oficio_numero']) ?>/<?= h($r['oficio_anio']) ?></div><?php else: ?><div class="small">Sin referencia</div><?php endif; ?></td>
          <td><?php $st = (string)($r['estado'] ?? 'Pendiente'); $cls = strtolower($st)==='revisado'?'estado-revisado':(strtolower($st)==='archivado'?'estado-archivado':'estado-pendiente'); ?><span class="tag <?= $cls ?>"><?= h($st) ?></span></td>
          <td><div class="actions-row"><a class="btn" href="documento_recibido_ver.php?id=<?= h($r['id']) ?>&return_to=<?= urlencode($returnTo) ?>">Ver</a><a class="btn" href="documento_recibido_editar.php?id=<?= h($r['id']) ?>">Editar</a><a class="btn" href="documento_recibido_eliminar.php?id=<?= h($r['id']) ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a></div></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</body>
</html>
