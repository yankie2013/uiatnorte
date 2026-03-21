<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\CitacionRepository;
use App\Services\CitacionService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new CitacionService(new CitacionRepository($pdo));
$accidenteId = (int) ($_GET['accidente_id'] ?? 0);
if ($accidenteId <= 0) {
    http_response_code(400);
    exit('Falta accidente_id');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if ($id > 0) {
            $service->delete($id, $accidenteId);
        }
        header('Location: citacion_listar.php?accidente_id=' . $accidenteId);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'desde' => trim((string) ($_GET['desde'] ?? '')),
    'hasta' => trim((string) ($_GET['hasta'] ?? '')),
];
$rows = $service->listado($accidenteId, $filters);
$pdfDisponible = file_exists(__DIR__ . '/citacion_diligencia_pdf.php');
include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Listado de citaciones</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8;--danger:#b91c1c}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#3b82f6;--danger:#fecaca}}
body{background:var(--page);color:var(--text)}.wrap{max-width:1280px;margin:24px auto;padding:0 12px}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:10px}.c12{grid-column:span 12}.c3{grid-column:span 3}.c2{grid-column:span 2}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.btn.danger{color:var(--danger)}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(29,78,216,.12);color:var(--primary);border:1px solid rgba(29,78,216,.18);font-size:11px}.small{color:var(--muted);font-size:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin:10px 0}.actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin:16px 0}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:16px;background:var(--card)}table{width:100%;border-collapse:collapse;min-width:1160px}th,td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:top;text-align:left}th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;background:rgba(148,163,184,.08)}tbody tr:hover{background:rgba(59,130,246,.05)}.stack-actions{display:flex;gap:8px;flex-wrap:wrap}.pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid var(--border);font-size:11px}.muted{color:var(--muted)}input{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text);box-sizing:border-box}@media(max-width:900px){.c3,.c2{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0 0 6px">Citaciones <span class="badge">Listado</span></h1>
      <div class="small">Accidente ID: <?= (int) $accidenteId ?> · <?= count($rows) ?> registro(s)</div>
    </div>
    <div class="actions" style="margin:0;">
      <a class="btn" href="Dato_General_accidente.php?accidente_id=<?= (int) $accidenteId ?>">Volver al accidente</a>
      <a class="btn primary" href="citacion_nuevo.php?accidente_id=<?= (int) $accidenteId ?>">Nueva citacion</a>
    </div>
  </div>

  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:14px;">
    <form method="get" class="grid">
      <input type="hidden" name="accidente_id" value="<?= (int) $accidenteId ?>">
      <div class="c3">
        <label class="small">Buscar</label>
        <input name="q" value="<?= h($filters['q']) ?>" placeholder="Nombre, documento, lugar, motivo">
      </div>
      <div class="c2">
        <label class="small">Desde</label>
        <input type="date" name="desde" value="<?= h($filters['desde']) ?>">
      </div>
      <div class="c2">
        <label class="small">Hasta</label>
        <input type="date" name="hasta" value="<?= h($filters['hasta']) ?>">
      </div>
      <div class="c3" style="display:flex;align-items:end;gap:8px;">
        <button class="btn" type="submit">Filtrar</button>
        <a class="btn" href="citacion_listar.php?accidente_id=<?= (int) $accidenteId ?>">Limpiar</a>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Persona</th>
          <th>Documento</th>
          <th>Calidad</th>
          <th>Diligencia</th>
          <th>Fecha</th>
          <th>Hora</th>
          <th>Lugar</th>
          <th>Oficio</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($rows === []): ?>
        <tr><td colspan="10" class="muted" style="text-align:center;padding:24px;">No hay citaciones registradas con esos filtros.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $nombre = trim((string) (($row['persona_nombres'] ?? '') . ' ' . ($row['persona_apep'] ?? '') . ' ' . ($row['persona_apem'] ?? '')));
            $documento = trim((string) (($row['persona_doc_tipo'] ?? '') . ' ' . ($row['persona_doc_num'] ?? '')));
            $oficio = (!empty($row['oficio_num']) && !empty($row['oficio_anio']))
                ? ((string) $row['oficio_num'] . '/' . (string) $row['oficio_anio'])
                : 'Sin oficio';
          ?>
          <tr>
            <td>#<?= (int) $row['id'] ?></td>
            <td>
              <strong><?= h($nombre !== '' ? $nombre : 'Sin nombre') ?></strong><br>
              <span class="muted">Orden: <?= (int) ($row['orden_citacion'] ?? 0) ?></span>
            </td>
            <td><?= h($documento !== '' ? $documento : 'Sin documento') ?></td>
            <td><span class="pill"><?= h((string) ($row['en_calidad'] ?? '')) ?></span></td>
            <td><?= h((string) ($row['tipo_diligencia'] ?? '')) ?></td>
            <td><?= h((string) ($row['fecha'] ?? '')) ?></td>
            <td><?= h(substr((string) ($row['hora'] ?? ''), 0, 5)) ?></td>
            <td><?= h((string) ($row['lugar'] ?? '')) ?></td>
            <td><?= h($oficio) ?></td>
            <td>
              <div class="stack-actions">
                <a class="btn" href="citacion_leer.php?id=<?= (int) $row['id'] ?>&return=<?= urlencode('citacion_listar.php?accidente_id=' . $accidenteId) ?>">Ver</a>
                <a class="btn" href="citacion_editar.php?id=<?= (int) $row['id'] ?>&return=<?= urlencode('citacion_listar.php?accidente_id=' . $accidenteId) ?>">Editar</a>
                <a class="btn" href="citacion_diligencia.php?citacion_id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">DOCX</a>
                <?php if ($pdfDisponible): ?><a class="btn" href="citacion_diligencia_pdf.php?citacion_id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">PDF</a><?php endif; ?>
                <form method="post" onsubmit="return confirm('Eliminar esta citacion?');" style="display:inline;">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <button class="btn danger" type="submit">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
