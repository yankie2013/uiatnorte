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
$accidenteId = (int) ($_GET['accidente_id'] ?? $_POST['accidente_id'] ?? 0);
$error = '';
$context = null;

try {
    $context = $service->quickRegistrationContext($accidenteId);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$fecha = trim((string) ($_POST['fecha_emision'] ?? ($context['fecha_emision'] ?? '')));
$numero = trim((string) ($_POST['numero_oficio'] ?? ($context['numero_oficio'] ?? '')));
$motivo = trim((string) ($_POST['motivo'] ?? ''));
$entidadId = trim((string) ($_POST['entidad_id'] ?? ''));
$categoriaEntidad = trim((string) ($_POST['entidad_categoria'] ?? ''));
$categoriasEntidad = [];
if ($context !== null) {
    foreach ($context['entidad_categorias'] as $categoria) {
        $codigo = trim((string) ($categoria['codigo'] ?? ''));
        if ($codigo !== '') {
            $categoriasEntidad[$codigo] = trim((string) ($categoria['nombre'] ?? '')) ?: $codigo;
        }
    }
    foreach ($context['entidades'] as $entidad) {
        $codigo = trim((string) ($entidad['categoria'] ?? ''));
        if ($codigo !== '' && !isset($categoriasEntidad[$codigo])) {
            $categoriasEntidad[$codigo] = str_replace('_', ' ', $codigo);
        }
    }
    asort($categoriasEntidad, SORT_NATURAL | SORT_FLAG_CASE);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $context !== null) {
    try {
        $service->createQuick([
            'accidente_id' => $accidenteId,
            'fecha_emision' => $fecha,
            'numero_oficio' => $numero,
            'motivo' => $motivo,
            'entidad_id' => $entidadId,
            'oficial_ano_id' => $context['oficial_ano_id'],
        ]);
        echo '<!doctype html><meta charset="utf-8"><script>window.parent.postMessage({type:"oficio.saved"},"*");</script><body>Guardado...</body>';
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Registro rapido de oficio</title>
<link rel="stylesheet" href="style_mushu.css">
<style>
:root{color-scheme:light;--page:#f4f7fb;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8;--danger:#b91c1c}
html[data-theme-resolved="dark"]{color-scheme:dark;--page:#0b1220;--card:#101a2c;--text:#e5edf8;--muted:#9fb0c6;--border:#30415f;--primary:#60a5fa;--danger:#fecaca}
body{margin:0;background:var(--page);color:var(--text);font-family:Inter,system-ui,sans-serif}.wrap{max-width:1000px;margin:0 auto;padding:18px}.head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:14px}.head h1{margin:0;font-size:22px}.muted{color:var(--muted);font-size:.9rem}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:18px}.summary{margin-bottom:14px;padding:12px 14px;border:1px dashed var(--border);border-radius:12px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}.c4{grid-column:span 4}.c8{grid-column:span 8}.c12{grid-column:span 12}label{display:block;margin-bottom:6px;color:var(--muted);font-weight:800}input,select,textarea{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid var(--border);border-radius:10px;background:var(--card);color:var(--text);font:inherit;line-height:1.35}select{height:46px;min-height:46px;padding:9px 38px 9px 12px;appearance:auto;-webkit-appearance:menulist}select option{padding:8px 10px;background:var(--card);color:var(--text)}textarea{min-height:125px;resize:vertical}.actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}.btn{padding:10px 14px;border:1px solid var(--border);border-radius:10px;background:var(--card);color:var(--text);font-weight:800;cursor:pointer}.btn.primary{background:var(--primary);border-color:transparent;color:#fff}.alert{margin-bottom:12px;padding:11px 13px;border-radius:10px;background:rgba(220,38,38,.12);color:var(--danger)}@media(max-width:760px){.c4,.c8{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div><h1>Nuevo oficio - registro rapido</h1><div class="muted">Solo registra los datos esenciales del oficio.</div></div>
    <button class="btn" type="button" onclick="window.parent.postMessage({type:'oficio.close'},'*')">Cerrar</button>
  </div>
  <?php if ($error !== ''): ?><div class="alert"><?= h($error) ?></div><?php endif; ?>
  <?php if ($context !== null): ?>
    <form method="post" class="card">
      <input type="hidden" name="accidente_id" value="<?= h($accidenteId) ?>">
      <div class="summary"><strong>Accidente actual:</strong> <?= h($context['accidente_label']) ?><div class="muted">El oficio quedara vinculado automaticamente a este accidente.</div></div>
      <div class="grid">
        <div class="c4"><label>Fecha*</label><input type="date" name="fecha_emision" value="<?= h($fecha) ?>" required></div>
        <div class="c4"><label>Numero*</label><input type="number" name="numero_oficio" value="<?= h($numero) ?>" min="1" required></div>
        <div class="c4">
          <label>Categoria de entidad</label>
          <select name="entidad_categoria" id="entidad_categoria">
            <option value="">Todas las categorias</option>
            <?php foreach ($categoriasEntidad as $codigo => $nombre): ?>
              <option value="<?= h($codigo) ?>" <?= $categoriaEntidad === (string) $codigo ? 'selected' : '' ?>><?= h($nombre) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="c8">
          <label>Entidad de destino*</label>
          <select name="entidad_id" id="entidad_id" required>
            <option value="">Selecciona una entidad</option>
            <?php foreach ($context['entidades'] as $entidad): ?>
              <?php $entidadLabel = trim((string) ($entidad['nombre'] ?? '')) . (trim((string) ($entidad['siglas'] ?? '')) !== '' ? ' (' . trim((string) $entidad['siglas']) . ')' : ''); ?>
              <option value="<?= h($entidad['id']) ?>" data-categoria="<?= h($entidad['categoria'] ?? '') ?>" <?= $entidadId === (string) $entidad['id'] ? 'selected' : '' ?>><?= h($entidadLabel) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="muted">El filtro muestra solamente las entidades de la categoria elegida.</div>
        </div>
        <div class="c12"><label>Motivo / contexto*</label><textarea name="motivo" placeholder="Describe brevemente el motivo y contexto del oficio" required><?= h($motivo) ?></textarea></div>
      </div>
      <div class="actions"><button class="btn primary" type="submit">Guardar oficio rapido</button></div>
    </form>
  <?php endif; ?>
</div>
<script>
const categoriaSelect = document.getElementById('entidad_categoria');
const entidadSelect = document.getElementById('entidad_id');
const entidades = Array.from(entidadSelect.options).slice(1).map((option) => ({
  value: option.value,
  label: option.textContent,
  categoria: option.dataset.categoria || ''
}));

function filtrarEntidades() {
  const categoria = categoriaSelect.value;
  const seleccionActual = entidadSelect.value;
  entidadSelect.innerHTML = '<option value="">Selecciona una entidad</option>';
  entidades
    .filter((entidad) => categoria === '' || entidad.categoria === categoria)
    .forEach((entidad) => {
      const option = document.createElement('option');
      option.value = entidad.value;
      option.textContent = entidad.label;
      option.dataset.categoria = entidad.categoria;
      option.selected = entidad.value === seleccionActual;
      entidadSelect.appendChild(option);
    });
}

categoriaSelect.addEventListener('change', filtrarEntidades);
filtrarEntidades();
</script>
</body>
</html>
