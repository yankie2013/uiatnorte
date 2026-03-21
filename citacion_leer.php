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
$id = (int) ($_GET['id'] ?? ($_GET['citacion_id'] ?? 0));
if ($id <= 0) {
    http_response_code(400);
    exit('Falta id');
}

$row = $service->detail($id);
if ($row === null) {
    http_response_code(404);
    exit('La citacion no existe.');
}

$accidenteId = (int) ($row['accidente_id'] ?? 0);
$return = trim((string) ($_GET['return'] ?? ''));
if ($return === '') {
    $return = $accidenteId > 0
        ? 'citacion_listar.php?accidente_id=' . $accidenteId
        : 'citacion_listar.php';
}

$nombre = trim((string) (($row['persona_nombres'] ?? '') . ' ' . ($row['persona_apep'] ?? '') . ' ' . ($row['persona_apem'] ?? '')));
$documento = trim((string) (($row['persona_doc_tipo'] ?? '') . ' ' . ($row['persona_doc_num'] ?? '')));
$oficio = (!empty($row['oficio_num']) && !empty($row['oficio_anio']))
    ? ((string) $row['oficio_num'] . '/' . (string) $row['oficio_anio'])
    : 'Sin oficio';
$pdfDisponible = file_exists(__DIR__ . '/citacion_diligencia_pdf.php');

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Detalle de citacion</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#3b82f6}}
body{background:var(--page);color:var(--text)}.wrap{max-width:960px;margin:24px auto;padding:0 12px}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(29,78,216,.12);color:var(--primary);border:1px solid rgba(29,78,216,.18);font-size:11px}.field{padding:12px;border:1px solid var(--border);border-radius:14px;background:rgba(148,163,184,.08)}.label{font-size:12px;color:var(--muted);font-weight:700;margin-bottom:4px}.value{font-weight:700;word-break:break-word}.actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin:16px 0}.small{color:var(--muted);font-size:12px}@media(max-width:820px){.c6{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <h1 style="margin:0 0 10px">Citacion <span class="badge">Detalle</span></h1>
  <div class="small">Registro #<?= (int) $id ?><?php if ($accidenteId > 0): ?> · Accidente ID: <?= (int) $accidenteId ?><?php endif; ?></div>

  <div class="actions">
    <a class="btn" href="<?= h($return) ?>">Volver</a>
    <a class="btn" href="citacion_editar.php?id=<?= (int) $id ?>&return=<?= urlencode($return) ?>">Editar</a>
    <a class="btn primary" href="citacion_diligencia.php?citacion_id=<?= (int) $id ?>" target="_blank" rel="noopener">DOCX</a>
    <?php if ($pdfDisponible): ?><a class="btn" href="citacion_diligencia_pdf.php?citacion_id=<?= (int) $id ?>" target="_blank" rel="noopener">PDF</a><?php endif; ?>
  </div>

  <div class="card">
    <div class="grid">
      <div class="c12 field">
        <div class="label">Persona citada</div>
        <div class="value"><?= h($nombre !== '' ? $nombre : 'Sin nombre registrado') ?></div>
      </div>

      <div class="c6 field">
        <div class="label">Documento</div>
        <div class="value"><?= h($documento !== '' ? $documento : 'Sin documento') ?></div>
      </div>

      <div class="c6 field">
        <div class="label">Edad</div>
        <div class="value"><?= h((string) (($row['persona_edad'] ?? '') !== '' ? $row['persona_edad'] : 'No registrada')) ?></div>
      </div>

      <div class="c12 field">
        <div class="label">Domicilio</div>
        <div class="value"><?= h((string) (($row['persona_domicilio'] ?? '') !== '' ? $row['persona_domicilio'] : 'No registrado')) ?></div>
      </div>

      <div class="c6 field">
        <div class="label">En calidad de</div>
        <div class="value"><?= h((string) ($row['en_calidad'] ?? '')) ?></div>
      </div>

      <div class="c6 field">
        <div class="label">Tipo de diligencia</div>
        <div class="value"><?= h((string) ($row['tipo_diligencia'] ?? '')) ?></div>
      </div>

      <div class="c6 field">
        <div class="label">Fecha</div>
        <div class="value"><?= h((string) ($row['fecha'] ?? '')) ?></div>
      </div>

      <div class="c6 field">
        <div class="label">Hora</div>
        <div class="value"><?= h(substr((string) ($row['hora'] ?? ''), 0, 5)) ?></div>
      </div>

      <div class="c6 field">
        <div class="label">Lugar</div>
        <div class="value"><?= h((string) ($row['lugar'] ?? '')) ?></div>
      </div>

      <div class="c6 field">
        <div class="label">Oficio que ordena</div>
        <div class="value"><?= h($oficio) ?></div>
      </div>

      <div class="c6 field">
        <div class="label">Orden de citacion</div>
        <div class="value"><?= (int) ($row['orden_citacion'] ?? 0) ?></div>
      </div>

      <div class="c6 field">
        <div class="label">Origen vinculado</div>
        <div class="value"><?= h(trim((string) (($row['fuente'] ?? '') . ' #' . ($row['fuente_id'] ?? '')))) ?></div>
      </div>

      <div class="c12 field">
        <div class="label">Motivo / observaciones</div>
        <div class="value"><?= nl2br(h((string) ($row['motivo'] ?? ''))) ?></div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
