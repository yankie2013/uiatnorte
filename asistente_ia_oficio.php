<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$accidenteId = isset($_GET['accidente_id']) ? (int) $_GET['accidente_id'] : 0;
$numeroOficio = trim((string) ($_GET['numero_oficio'] ?? ''));
$returnTo = trim((string) ($_GET['return_to'] ?? ''));
$defaultPrompt = 'Genera un oficio para solicitar camaras a la Municipalidad de Los Olivos, en el rango de 14:00 a 15:00 horas aproximadamente.';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Generador de Oficios con IA</title>
  <style>
    :root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8;--danger:#b91c1c}
    @media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#3b82f6;--danger:#fecaca}}
    body{margin:0;background:var(--page);color:var(--text);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    .wrap{max-width:960px;margin:24px auto;padding:16px}
    .toolbar{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px}
    .toolbar h1{margin:0;font-size:26px}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:700;cursor:pointer}
    .btn.primary{background:var(--primary);color:#fff;border-color:transparent}
    .card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px}
    .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}
    .c4{grid-column:span 4}.c8{grid-column:span 8}.c12{grid-column:span 12}
    label{display:block;font-weight:700;color:var(--muted);margin-bottom:6px}
    input,textarea{width:100%;box-sizing:border-box;padding:12px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);line-height:1.35}
    input{min-height:46px} textarea{min-height:170px;resize:vertical}
    .muted{color:var(--muted);font-size:.92rem}
    .actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:4px}
    @media (max-width:900px){.c4,.c8{grid-column:span 12}.toolbar h1{font-size:22px}}
  </style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1>Generador de Oficios con IA</h1>
      <div class="muted">Solicitud de camaras de videovigilancia en formato Word</div>
    </div>
    <div>
      <?php if ($returnTo !== ''): ?><a class="btn" href="<?= h($returnTo) ?>">Volver</a><?php endif; ?>
      <?php if ($accidenteId > 0): ?><a class="btn" href="oficios_listar.php?accidente_id=<?= urlencode((string) $accidenteId) ?>">Ver oficios</a><?php endif; ?>
    </div>
  </div>

  <form method="post" action="ia_generar_oficio.php" class="card">
    <div class="grid">
      <div class="c4">
        <label for="accidente_id">accidente_id</label>
        <input id="accidente_id" name="accidente_id" type="number" min="1" required value="<?= h($accidenteId ?: '') ?>">
      </div>
      <div class="c4">
        <label for="numero_oficio">numero_oficio</label>
        <input id="numero_oficio" name="numero_oficio" type="text" maxlength="40" required value="<?= h($numeroOficio) ?>" placeholder="Ej. 123-2026">
      </div>
      <div class="c12">
        <label for="prompt_usuario">prompt_usuario</label>
        <textarea id="prompt_usuario" name="prompt_usuario" required><?= h($defaultPrompt) ?></textarea>
      </div>
      <div class="c12 actions">
        <?php if ($returnTo !== ''): ?><input type="hidden" name="return_to" value="<?= h($returnTo) ?>"><?php endif; ?>
        <button class="btn primary" type="submit">Generar oficio Word</button>
      </div>
    </div>
  </form>
</div>
</body>
</html>
