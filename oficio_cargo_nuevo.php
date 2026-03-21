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
$error = '';
$success = '';
$data = $service->gradoCargoDefault(['tipo' => $_GET['tipo'] ?? 'CARGO']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'tipo' => $_POST['tipo'] ?? 'CARGO',
        'nombre' => $_POST['nombre'] ?? '',
        'abreviatura' => $_POST['abreviatura'] ?? '',
        'orden' => $_POST['orden'] ?? 0,
        'activo' => isset($_POST['activo']) ? 1 : 0,
    ];

    try {
        $service->createGradoCargo($data);
        $success = 'Registro agregado correctamente.';
        $data = $service->gradoCargoDefault(['tipo' => $data['tipo']]);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Nuevo grado/cargo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:760px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:700;cursor:pointer}.btn.primary{background:var(--primary);color:#eff6ff;border-color:transparent}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.field{display:flex;flex-direction:column;gap:6px}.label{font-size:12px;color:var(--muted);font-weight:700}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin-bottom:12px}input,select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}.check{display:flex;align-items:center;gap:10px;padding-top:24px}.check input{width:auto}.actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:16px}@media(max-width:860px){.c6{grid-column:span 12}}
</style>
<script>
function closeModalMaybe(){ if(window.parent && typeof window.parent.closeModal==='function'){ window.parent.closeModal(); } else { history.back(); } }
</script>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <h1 style="margin:0;">Nuevo grado/cargo</h1>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><button class="btn" type="button" onclick="closeModalMaybe()">Cerrar</button><button class="btn primary" type="submit" form="frmCargo">Guardar</button></div>
  </div>
  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success !== ''): ?><div class="ok"><?= h($success) ?></div><?php endif; ?>
  <form method="post" class="card" id="frmCargo">
    <div class="grid">
      <div class="c6 field"><label class="label">Tipo*</label><select name="tipo"><?php foreach (['GRADO','CARGO'] as $item): ?><option value="<?= $item ?>" <?= (string) $data['tipo'] === $item ? 'selected' : '' ?>><?= $item ?></option><?php endforeach; ?></select></div>
      <div class="c6 field"><label class="label">Orden</label><input type="number" name="orden" value="<?= h((string) $data['orden']) ?>"></div>
      <div class="c12 field"><label class="label">Nombre*</label><input type="text" name="nombre" value="<?= h((string) $data['nombre']) ?>" required></div>
      <div class="c12 field"><label class="label">Abreviatura</label><input type="text" name="abreviatura" value="<?= h((string) $data['abreviatura']) ?>"></div>
      <div class="c12 check"><input type="checkbox" id="activo" name="activo" value="1" <?= !empty($data['activo']) ? 'checked' : '' ?>><label for="activo">Activo</label></div>
    </div>
    <div class="actions"><button class="btn" type="button" onclick="closeModalMaybe()">Cancelar</button><button class="btn primary" type="submit">Guardar</button></div>
  </form>
</div>
</body>
</html>
