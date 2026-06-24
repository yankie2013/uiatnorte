<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

use App\Support\Auth;

require_login();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function resume_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function resume_hidden_fields(array $values, string $prefix = ''): string
{
    $html = '';
    foreach ($values as $key => $value) {
        $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
        if (is_array($value)) {
            $html .= resume_hidden_fields($value, $name);
            continue;
        }
        $html .= '<input type="hidden" name="' . resume_h($name) . '" value="' . resume_h($value) . '">' . "\n";
    }
    return $html;
}

$pending = Auth::pendingRequest();
if ($pending === null) {
    header('Location: index.php');
    exit;
}

$token = (string) $pending['token'];
$target = (string) $pending['uri'];
$mode = trim((string) ($_POST['resume_action'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mode === 'discard') {
    Auth::discardPendingRequest((string) ($_POST['token'] ?? ''));
    header('Location: ' . $target);
    exit;
}

$capturedAt = !empty($pending['captured_at']) ? date('d/m/Y H:i', (int) $pending['captured_at']) : '';
$files = array_filter((array) ($pending['files'] ?? []));
$fields = resume_hidden_fields((array) $pending['post']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Recuperar actividad</title>
  <style>
    :root{color-scheme:light dark;--bg:#eef3f9;--card:#fff;--text:#0f172a;--muted:#64748b;--line:#d7deea;--primary:#2563eb;--danger:#b91c1c}
    @media(prefers-color-scheme:dark){:root{--bg:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--line:#23314d;--primary:#60a5fa;--danger:#fecaca}}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--text);font:15px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif;padding:24px}
    .card{width:min(640px,95vw);background:var(--card);border:1px solid var(--line);border-radius:20px;padding:30px;box-shadow:0 18px 45px rgba(15,23,42,.16)}
    h1{margin:0 0 10px;font-size:1.5rem}.lead{margin:0 0 22px;color:var(--muted)}.detail{padding:14px;border:1px solid var(--line);border-radius:14px;background:rgba(37,99,235,.06);overflow-wrap:anywhere}
    .warning{margin-top:14px;padding:12px;border:1px solid rgba(245,158,11,.4);border-radius:12px;background:rgba(245,158,11,.1)}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.actions form{margin:0}.btn{border:0;border-radius:12px;padding:12px 17px;font-weight:800;cursor:pointer}.primary{background:var(--primary);color:#fff}.secondary{background:transparent;color:var(--danger);border:1px solid var(--line)}
  </style>
</head>
<body>
  <main class="card">
    <h1>Recuperamos tu ultima actividad</h1>
    <p class="lead">La sesion vencio justo cuando intentaste guardar. Tus datos no se descartaron y ahora puedes confirmar el envio.</p>
    <div class="detail">
      <strong>Formulario:</strong> <?= resume_h($target) ?><br>
      <?php if ($capturedAt !== ''): ?><strong>Recuperado:</strong> <?= resume_h($capturedAt) ?><?php endif; ?>
    </div>
    <?php if ($files !== []): ?>
      <div class="warning"><strong>Adjuntos:</strong> por seguridad el navegador no puede conservar archivos. Seleccionalos nuevamente antes de continuar.</div>
    <?php endif; ?>
    <div class="actions">
      <form method="post" action="<?= resume_h($target) ?>" enctype="multipart/form-data">
        <?= $fields ?>
        <input type="hidden" name="_uiat_recovery_token" value="<?= resume_h($token) ?>">
        <?php foreach ($files as $field => $names): ?>
          <?php $names = array_values(array_filter((array) $names)); ?>
          <label style="display:block;margin:12px 0;text-align:left">
            <strong><?= resume_h(implode(', ', $names)) ?></strong>
            <input type="file" name="<?= resume_h((string) $field . (count($names) > 1 ? '[]' : '')) ?>" <?= count($names) > 1 ? 'multiple' : '' ?> required style="display:block;margin-top:6px;max-width:100%">
          </label>
        <?php endforeach; ?>
        <button class="btn primary" type="submit">Continuar y guardar</button>
      </form>
      <form method="post">
        <input type="hidden" name="token" value="<?= resume_h($token) ?>">
        <input type="hidden" name="resume_action" value="discard">
        <button class="btn secondary" type="submit">Descartar y volver</button>
      </form>
    </div>
  </main>
</body>
</html>
