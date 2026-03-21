<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\ComisariaRepository;
use App\Services\ComisariaService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new ComisariaService(new ComisariaRepository($pdo));
$error = '';
$data = $service->defaultData();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nombre' => $_POST['nombre'] ?? '',
        'tipo' => $_POST['tipo'] ?? '',
        'direccion' => $_POST['direccion'] ?? '',
        'telefono' => $_POST['telefono'] ?? '',
        'correo' => $_POST['correo'] ?? '',
        'lat' => $_POST['lat'] ?? '',
        'lon' => $_POST['lon'] ?? '',
        'notas' => $_POST['notas'] ?? '',
        'activo' => isset($_POST['activo']) ? 1 : 0,
    ];

    try {
        $service->create($data);
        header('Location: comisarias_listar.php?ok=created');
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
<title>Nueva comisaria</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--danger:#b91c1c}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--danger:#fecaca}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1120px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(37,99,235,.12);color:var(--primary);border:1px solid rgba(37,99,235,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#eff6ff;border-color:transparent}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.c4{grid-column:span 4}.field{display:flex;flex-direction:column;gap:6px}.label{font-size:12px;color:var(--muted);font-weight:700}.small{color:var(--muted);font-size:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}input,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}textarea{min-height:110px;resize:vertical}.check{display:flex;align-items:center;gap:10px;padding-top:22px}.check input{width:auto}.actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:16px}@media(max-width:860px){.c6,.c4{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div><h1 style="margin:0 0 6px">Comisarias <span class="badge">Nueva</span></h1><div class="small">Registro base para notificaciones, referencias y ubicacion</div></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn" href="comisarias_listar.php">Volver</a><button class="btn primary" type="submit" form="frmComisaria">Guardar comisaria</button></div>
  </div>

  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <form method="post" class="card" id="frmComisaria" autocomplete="off">
    <div class="grid">
      <div class="c6 field"><label class="label">Nombre*</label><input type="text" name="nombre" value="<?= h((string) $data['nombre']) ?>" required></div>
      <div class="c6 field"><label class="label">Tipo</label><input type="text" name="tipo" value="<?= h((string) $data['tipo']) ?>" placeholder="Comisaria, seccional, unidad especializada"></div>
      <div class="c12 field"><label class="label">Direccion</label><input type="text" name="direccion" value="<?= h((string) $data['direccion']) ?>"></div>
      <div class="c4 field"><label class="label">Telefono</label><input type="text" name="telefono" value="<?= h((string) $data['telefono']) ?>"></div>
      <div class="c4 field"><label class="label">Correo</label><input type="email" name="correo" value="<?= h((string) $data['correo']) ?>"></div>
      <div class="c4 check"><input type="checkbox" id="activo" name="activo" value="1" <?= !empty($data['activo']) ? 'checked' : '' ?>><label for="activo">Registro activo</label></div>
      <div class="c6 field"><label class="label">Latitud</label><input type="text" name="lat" value="<?= h((string) $data['lat']) ?>" placeholder="-12.0464"></div>
      <div class="c6 field"><label class="label">Longitud</label><input type="text" name="lon" value="<?= h((string) $data['lon']) ?>" placeholder="-77.0428"></div>
      <div class="c12 field"><label class="label">Notas</label><textarea name="notas"><?= h((string) $data['notas']) ?></textarea></div>
    </div>
    <div class="actions"><a class="btn" href="comisarias_listar.php">Cancelar</a><button class="btn primary" type="submit">Guardar</button></div>
  </form>
</div>
</body>
</html>
