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

$ctx = $service->formContext($accidenteId);
$data = $service->defaultData($row);
$error = '';
$success = (int) ($_GET['ok'] ?? 0) === 1;
$pdfDisponible = file_exists(__DIR__ . '/citacion_diligencia_pdf.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'persona_nombres' => $_POST['persona_nombres'] ?? '',
        'persona_apep' => $_POST['persona_apep'] ?? '',
        'persona_apem' => $_POST['persona_apem'] ?? '',
        'persona_doc_tipo' => $_POST['persona_doc_tipo'] ?? 'DNI',
        'persona_doc_num' => $_POST['persona_doc_num'] ?? '',
        'persona_domicilio' => $_POST['persona_domicilio'] ?? '',
        'persona_edad' => $_POST['persona_edad'] ?? '',
        'en_calidad' => $_POST['en_calidad'] ?? '',
        'tipo_diligencia' => $_POST['tipo_diligencia'] ?? '',
        'fecha' => $_POST['fecha'] ?? '',
        'hora' => $_POST['hora'] ?? '',
        'lugar' => $_POST['lugar'] ?? '',
        'motivo' => $_POST['motivo'] ?? '',
        'orden_citacion' => $_POST['orden_citacion'] ?? 1,
        'oficio_id' => $_POST['oficio_id'] ?? '',
    ];

    try {
        $service->update($id, $data);
        header('Location: citacion_editar.php?id=' . $id . '&ok=1&return=' . urlencode($return));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Editar citacion</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8;--ok:#166534;--danger:#b91c1c}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#3b82f6;--ok:#bbf7d0;--danger:#fecaca}}
body{background:var(--page);color:var(--text)}.wrap{max-width:1020px;margin:24px auto;padding:0 12px}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:10px}.c12{grid-column:span 12}.c6{grid-column:span 6}.c4{grid-column:span 4}.c3{grid-column:span 3}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(29,78,216,.12);color:var(--primary);border:1px solid rgba(29,78,216,.18);font-size:11px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin:10px 0}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin:10px 0}.small{color:var(--muted);font-size:12px}.label{font-weight:700;color:var(--muted);font-size:13px}.value-box{padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:rgba(148,163,184,.08)}input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text);box-sizing:border-box}textarea{min-height:110px;resize:vertical}.actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin:16px 0}.panel{padding:12px;border:1px dashed var(--border);border-radius:14px}.meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:12px}@media(max-width:820px){.c6,.c4,.c3{grid-column:span 12}.meta{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <h1 style="margin:0 0 10px">Citacion <span class="badge">Editar</span></h1>
  <div class="small">Registro #<?= (int) $id ?><?php if ($accidenteId > 0): ?> · Accidente ID: <?= (int) $accidenteId ?><?php endif; ?></div>

  <div class="actions">
    <a class="btn" href="<?= h($return) ?>">Volver</a>
    <a class="btn" href="citacion_leer.php?id=<?= (int) $id ?>&return=<?= urlencode($return) ?>">Ver detalle</a>
    <a class="btn" href="citacion_diligencia.php?citacion_id=<?= (int) $id ?>" target="_blank" rel="noopener">DOCX</a>
    <?php if ($pdfDisponible): ?><a class="btn" href="citacion_diligencia_pdf.php?citacion_id=<?= (int) $id ?>" target="_blank" rel="noopener">PDF</a><?php endif; ?>
    <button class="btn primary" type="submit" form="frmCitacion">Guardar cambios</button>
  </div>

  <?php if ($success): ?><div class="ok">Cambios guardados correctamente.</div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <form method="post" class="card" id="frmCitacion">
    <div class="meta">
      <div>
        <div class="label">Origen</div>
        <div class="value-box"><?= h(trim((string) (($row['fuente'] ?? '') . ' #' . ($row['fuente_id'] ?? '')))) ?></div>
      </div>
      <div>
        <div class="label">Oficio vinculado</div>
        <div class="value-box"><?php if (!empty($row['oficio_num']) && !empty($row['oficio_anio'])): ?><?= h($row['oficio_num'] . '/' . $row['oficio_anio']) ?><?php else: ?>Sin oficio<?php endif; ?></div>
      </div>
      <div>
        <div class="label">Retorno</div>
        <div class="value-box">Listado de citaciones</div>
      </div>
    </div>

    <div class="panel" style="margin-bottom:14px;">
      <strong>Persona citada</strong>
      <div class="small" style="margin-top:4px;">Puedes ajustar el snapshot almacenado en la citacion sin tocar la ficha original de la persona.</div>
    </div>

    <div class="grid">
      <div class="c4">
        <label class="label">Nombres</label>
        <input name="persona_nombres" value="<?= h($data['persona_nombres']) ?>">
      </div>
      <div class="c4">
        <label class="label">Apellido paterno</label>
        <input name="persona_apep" value="<?= h($data['persona_apep']) ?>">
      </div>
      <div class="c4">
        <label class="label">Apellido materno</label>
        <input name="persona_apem" value="<?= h($data['persona_apem']) ?>">
      </div>

      <div class="c3">
        <label class="label">Tipo de documento</label>
        <input name="persona_doc_tipo" value="<?= h($data['persona_doc_tipo']) ?>">
      </div>
      <div class="c3">
        <label class="label">Numero de documento</label>
        <input name="persona_doc_num" value="<?= h($data['persona_doc_num']) ?>">
      </div>
      <div class="c3">
        <label class="label">Edad</label>
        <input name="persona_edad" value="<?= h((string) $data['persona_edad']) ?>">
      </div>
      <div class="c3">
        <label class="label">Oficio que ordena</label>
        <select name="oficio_id">
          <option value="">Sin oficio</option>
          <?php foreach ($ctx['oficios'] as $oficio): ?>
            <?php $val = (string) ($oficio['id'] ?? ''); ?>
            <option value="<?= h($val) ?>" <?= (string) $data['oficio_id'] === $val ? 'selected' : '' ?>><?= h(($oficio['numero'] ?? '') . '/' . ($oficio['anio'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="c12">
        <label class="label">Domicilio</label>
        <input name="persona_domicilio" value="<?= h($data['persona_domicilio']) ?>">
      </div>

      <div class="c6">
        <label class="label">En calidad de</label>
        <select name="en_calidad" required>
          <option value="">Selecciona</option>
          <?php foreach ($ctx['calidades'] as $calidad): ?>
            <option value="<?= h($calidad) ?>" <?= $data['en_calidad'] === $calidad ? 'selected' : '' ?>><?= h($calidad) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="c6">
        <label class="label">Tipo de diligencia</label>
        <select name="tipo_diligencia" required>
          <option value="">Selecciona</option>
          <?php foreach ($ctx['tipos'] as $tipo): ?>
            <option value="<?= h($tipo) ?>" <?= $data['tipo_diligencia'] === $tipo ? 'selected' : '' ?>><?= h($tipo) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="c3"><label class="label">Fecha</label><input type="date" name="fecha" value="<?= h($data['fecha']) ?>" required></div>
      <div class="c3"><label class="label">Hora</label><input type="time" name="hora" value="<?= h($data['hora']) ?>" required></div>
      <div class="c3"><label class="label">Orden de citacion</label><input type="number" name="orden_citacion" min="1" value="<?= h((string) $data['orden_citacion']) ?>"></div>
      <div class="c3"><label class="label">Accidente</label><input value="<?= (int) $accidenteId ?>" readonly></div>

      <div class="c12">
        <label class="label">Lugar</label>
        <input name="lugar" value="<?= h($data['lugar']) ?>" required>
      </div>

      <div class="c12">
        <label class="label">Motivo / observaciones</label>
        <textarea name="motivo" required><?= h($data['motivo']) ?></textarea>
      </div>
    </div>
  </form>
</div>
</body>
</html>
