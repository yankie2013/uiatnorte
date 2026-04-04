<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\CatalogoOficioRepository;
use App\Services\CatalogoOficioService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new CatalogoOficioService(new CatalogoOficioRepository($pdo));
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Falta id de entidad.');
}
$row = $service->entidadDetalle($id);
if ($row === null) {
    http_response_code(404);
    exit('Entidad no encontrada.');
}
$error = '';
$success = '';
$data = $service->entidadDefault($row);
$tiposEntidad = $service->tiposEntidad();
$categoriasEntidad = $service->categoriasEntidad();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'tipo' => $_POST['tipo'] ?? 'PUBLICA',
        'categoria' => $_POST['categoria'] ?? '',
        'nombre' => $_POST['nombre'] ?? '',
        'siglas' => $_POST['siglas'] ?? '',
        'direccion' => $_POST['direccion'] ?? '',
        'telefono' => $_POST['telefono'] ?? '',
        'telefono_fijo' => $_POST['telefono_fijo'] ?? '',
        'telefono_movil' => $_POST['telefono_movil'] ?? '',
        'correo' => $_POST['correo'] ?? '',
        'pagina_web' => $_POST['pagina_web'] ?? '',
    ];

    try {
        $service->saveEntidad($data, $id);
        $success = 'Entidad actualizada correctamente.';
        $row = $service->entidadDetalle($id);
        $data = $service->entidadDefault($row);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Editar entidad</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:900px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:700;cursor:pointer}.btn.primary{background:var(--primary);color:#eff6ff;border-color:transparent}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.field{display:flex;flex-direction:column;gap:6px}.label{font-size:12px;color:var(--muted);font-weight:700}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin-bottom:12px}input,select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}.actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:16px}@media(max-width:860px){.c6{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div><h1 style="margin:0;">Editar entidad</h1><div style="color:var(--muted);">Registro #<?= (int) $id ?></div></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn" href="oficio_entidades_listar.php">Prontuario</a><button class="btn" type="button" onclick="history.back()">Volver</button><button class="btn primary" type="submit" form="frmEntidad">Guardar cambios</button></div>
  </div>
  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success !== ''): ?><div class="ok"><?= h($success) ?></div><?php endif; ?>
  <form method="post" class="card" id="frmEntidad">
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <div class="grid">
      <div class="c6 field"><label class="label">Naturaleza</label><select name="tipo"><?php foreach ($tiposEntidad as $item): ?><option value="<?= h($item) ?>" <?= (string) $data['tipo'] === (string) $item ? 'selected' : '' ?>><?= h($item) ?></option><?php endforeach; ?></select></div>
      <div class="c6 field"><label class="label">Categoria</label><select name="categoria"><option value="">Selecciona una categoria</option><?php foreach ($categoriasEntidad as $item): ?><option value="<?= h($item) ?>" <?= (string) ($data['categoria'] ?? '') === (string) $item ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $item)) ?></option><?php endforeach; ?></select></div>
      <div class="c6 field"><label class="label">Nombre*</label><input type="text" name="nombre" value="<?= h((string) $data['nombre']) ?>" required></div>
      <div class="c6 field"><label class="label">Siglas</label><input type="text" name="siglas" value="<?= h((string) $data['siglas']) ?>"></div>
      <div class="c6 field"><label class="label">Telefono fijo</label><input type="text" name="telefono_fijo" value="<?= h((string) ($data['telefono_fijo'] ?? '')) ?>"></div>
      <div class="c6 field"><label class="label">Telefono movil</label><input type="text" name="telefono_movil" value="<?= h((string) ($data['telefono_movil'] ?? '')) ?>"></div>
      <div class="c6 field"><label class="label">Telefono principal (compatibilidad)</label><input type="text" name="telefono" value="<?= h((string) $data['telefono']) ?>"></div>
      <div class="c12 field"><label class="label">Direccion</label><input type="text" name="direccion" value="<?= h((string) $data['direccion']) ?>"></div>
      <div class="c6 field"><label class="label">Correo</label><input type="email" name="correo" value="<?= h((string) $data['correo']) ?>"></div>
      <div class="c6 field"><label class="label">Pagina web</label><input type="text" name="pagina_web" value="<?= h((string) $data['pagina_web']) ?>"></div>
    </div>
    <div class="actions"><button class="btn" type="button" onclick="history.back()">Cancelar</button><button class="btn primary" type="submit">Guardar</button></div>
  </form>
</div>
</body>
</html>
