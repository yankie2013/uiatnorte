<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\UserRepository;
use App\Services\UserService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$actorRole = (string) ($_SESSION['rol'] ?? ($_SESSION['user']['rol'] ?? ''));
$service = new UserService(new UserRepository($pdo));
$allowedRoles = $service->allowedRolesFor($actorRole);

if ($allowedRoles === []) {
    http_response_code(403);
    exit('Acceso denegado. Solo el administrador puede registrar usuarios.');
}

$error = '';
$success = '';
$data = $service->defaultData();
$data['rol'] = $allowedRoles[0] ?? 'viewer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'cip' => $_POST['cip'] ?? '', 'grado' => $_POST['grado'] ?? '',
        'cargo' => $_POST['cargo'] ?? '', 'unidad' => $_POST['unidad'] ?? 'DEPIAT',
        'nombre' => $_POST['nombre'] ?? '',
        'email' => $_POST['email'] ?? '',
        'rol' => $_POST['rol'] ?? ($allowedRoles[0] ?? 'viewer'),
    ];

    try {
        \App\Support\Access::checkCsrf();
        $service->create($_POST, $actorRole);
        $success = 'Usuario creado. Ingresará con su CIP como usuario y contraseña inicial; deberá crear una nueva contraseña antes de usar el sistema.';
        $data = $service->defaultData();
        $data['rol'] = $allowedRoles[0] ?? 'viewer';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Registrar usuario</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#4f46e5;--accent:#059669;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#6366f1;--accent:#10b981;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(1000px at 10% 20%, rgba(99,102,241,.15), transparent 60%),radial-gradient(1000px at 90% 80%, rgba(16,185,129,.15), transparent 60%);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.card{width:min(560px,94vw);background:var(--card);border:1px solid var(--border);border-radius:18px;padding:24px;box-shadow:0 18px 40px rgba(0,0,0,.12)}.topbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.btn.back{background:var(--accent);color:#fff;border-color:transparent}.sub{color:var(--muted);margin:0 0 14px}.chip{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--border);font-size:12px;color:var(--muted)}.msg-ok{background:rgba(22,163,74,.12);border:1px solid rgba(22,163,74,.24);padding:10px;border-radius:10px;color:var(--ok);margin-bottom:12px}.msg-err{background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.24);padding:10px;border-radius:10px;color:var(--danger);margin-bottom:12px}.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field{display:flex;flex-direction:column;gap:6px}label{font-size:12px;color:var(--muted);font-weight:700}input,select{width:100%;padding:12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}form{display:grid;gap:14px}@media (max-width:640px){.row{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="card">
  <div class="topbar">
    <h1 style="margin:0;">Registrar usuario</h1>
    <a href="index.php" class="btn back">Regresar</a>
  </div>

  <p class="sub">Quien registra ahora: <span class="chip"><?= h($actorRole) ?></span></p>
  <p class="sub" style="margin-top:-6px;">Roles permitidos: <?= h(implode(', ', $allowedRoles)) ?></p>

  <?php if ($success !== ''): ?><div class="msg-ok"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="msg-err"><?= h($error) ?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= h(\App\Support\Access::csrf()) ?>">
    <div class="row"><label>Grado<input name="grado" maxlength="40" value="<?= h($data['grado']) ?>"></label><label>CIP*<input name="cip" maxlength="30" inputmode="numeric" pattern="[0-9]+" value="<?= h($data['cip']) ?>" required></label></div>
    <div class="row"><label>Cargo<input name="cargo" maxlength="80" value="<?= h($data['cargo']) ?>"></label><label>Unidad<input name="unidad" value="<?= h($data['unidad']) ?>" maxlength="160"></label></div>
    <div class="row">
      <div class="field">
        <label>Nombre completo*</label>
        <input type="text" name="nombre" value="<?= h((string) $data['nombre']) ?>" required>
      </div>
      <div class="field">
        <label>Correo*</label>
        <input type="email" name="email" value="<?= h((string) $data['email']) ?>" required>
      </div>
    </div>

    <div class="row">
      <div class="field">
        <label>Rol*</label>
        <select name="rol" required>
          <?php foreach ($allowedRoles as $role): ?>
            <option value="<?= h($role) ?>" <?= (string) $data['rol'] === $role ? 'selected' : '' ?>><?= h(\App\Support\Access::ROLES[$role] ?? $role) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div></div>
    </div>

    <p class="sub">La contraseña inicial será el CIP del usuario. En su primer acceso deberá crear una contraseña de al menos 10 caracteres con mayúscula, minúscula, número y carácter especial.</p>

    <div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;">
      <a class="btn" href="index.php">Cancelar</a>
      <button class="btn primary" type="submit">Guardar usuario</button>
    </div>
  </form>
</div>
</body>
</html>
