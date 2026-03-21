<?php
require __DIR__.'/auth.php';
require __DIR__.'/db.php';

if (session_status()===PHP_SESSION_NONE) session_start();

header('Content-Type: text/html; charset=utf-8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

/* Si ya está logueado, envía al panel */
if (!empty($_SESSION['user'])) {
  header('Location: index.php'); exit;
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$err=''; $email='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  if ($email==='')            { $err = 'Ingresa tu correo.'; }
  elseif (!preg_match('/^[^@\s]+@[^@\s]+$/', $email)) { $err = 'Correo inválido.'; } // permite admin@uiat
  elseif ($pass==='')         { $err = 'Ingresa tu contraseña.'; }
  else {
    $st = $pdo->prepare("SELECT id, email, nombre, rol, pass_hash, activo
                         FROM usuarios
                         WHERE email = :e
                         LIMIT 1");
    $st->execute([':e'=>$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
      $err = 'Credenciales inválidas.';
    } elseif ((int)$u['activo'] !== 1) {
      $err = 'Usuario inactivo. Contacte al administrador.';
    } elseif (!password_verify($pass, $u['pass_hash'])) {
      $err = 'Credenciales inválidas.';
    } else {
      session_regenerate_id(true);
      $_SESSION['id']   = (int)$u['id'];
      $_SESSION['rol']  = $u['rol'];
      $_SESSION['user'] = [
        'id'     => (int)$u['id'],
        'email'  => $u['email'],
        'nombre' => $u['nombre'],
        'rol'    => $u['rol'],
      ];
      header('Location: index.php'); exit;
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Iniciar sesión · UIAT Norte</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="style_gian.css">
<style>
  :root{
    color-scheme: light dark;
    --bg1: rgba(99,102,241,.18);
    --bg2: rgba(16,185,129,.18);
    --card: rgba(255,255,255,.80);
    --line: rgba(0,0,0,.12);
    --fg: #0f172a;
    --muted: rgba(15,23,42,.65);
    --ring: rgba(99,102,241,.40);
    --accent: #4f46e5;
    --accent-weak: rgba(79,70,229,.12);
  }
  @media (prefers-color-scheme: dark){
    :root{
      --card: rgba(26,32,44,.78);
      --line: rgba(255,255,255,.16);
      --fg: #e5e7eb;
      --muted: rgba(229,231,235,.72);
      --ring: rgba(99,102,241,.35);
      --accent: #6366f1;
      --accent-weak: rgba(99,102,241,.12);
    }
  }

  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0; display:grid; place-items:center; padding:24px; color:var(--fg);
    font-family: system-ui, -apple-system, Segoe UI, Roboto;
    background:
      radial-gradient(1200px 600px at 12% 10%, var(--bg1), transparent 60%),
      radial-gradient(1200px 600px at 88% 90%, var(--bg2), transparent 60%);
  }

  .shell{
    width:min(1080px, 96vw);
    display:grid; grid-template-columns: 1.25fr 1fr; gap:22px;
  }
  @media (max-width: 980px){ .shell{ grid-template-columns: 1fr; } }

  .panel{
    background:var(--card); border:1px solid var(--line); border-radius:20px;
    backdrop-filter: blur(14px); overflow:hidden;
    box-shadow: 0 18px 40px rgba(0,0,0,.14);
  }

  .left{
    padding:28px 28px 0 28px; min-height:340px;
    display:flex; flex-direction:column; justify-content:center; gap:18px;
  }
  .brand{ display:flex; align-items:center; gap:12px; }
  .logo{
    width:46px; height:46px; border-radius:12px; border:1px solid var(--line);
    display:grid; place-items:center; font-weight:900; background:rgba(255,255,255,.6);
  }
  .badge{
    display:inline-flex; align-items:center; gap:8px; padding:8px 12px;
    border-radius:999px; border:1px solid var(--line); background:var(--accent-weak); font-weight:700;
  }
  .title{ font-size:1.9rem; line-height:1.2; font-weight:850; margin:4px 0 0 }
  .sub{ color:var(--muted); margin:0 }

  .right{ padding:0; border-left:1px solid var(--line); }
  @media (max-width:980px){ .right{ border-left:none; border-top:1px solid var(--line); } }

  .card{
    padding:26px; max-width:520px; margin:0 auto;
    display:flex; flex-direction:column; gap:14px;
  }
  .hdr{ display:flex; flex-direction:column; gap:6px; }
  .hdr h1{ margin:0; font-size:1.22rem; }
  .hint{ color:var(--muted); font-size:.95rem; }

  /* Form */
  form{ display:grid; gap:16px; }
  .field{ position:relative; }
  .control{
    width:100%; background:transparent; color:var(--fg);
    border:1px solid var(--line); border-radius:12px;
    padding:16px 44px 10px 14px; font-size:1rem; line-height:1.2;
    outline:none; transition: box-shadow .15s ease, border-color .15s ease, transform .05s ease;
  }
  .control:focus{ box-shadow:0 0 0 5px var(--ring); border-color:transparent; transform: translateY(-1px); }
  .label{
    position:absolute; left:14px; top:12px; pointer-events:none;
    font-size:.96rem; color:var(--muted); transition: all .15s ease;
    background:transparent;
  }
  .control:not(:placeholder-shown) + .label,
  .control:focus + .label{
    top:-9px; left:10px; padding:0 6px; font-size:.78rem;
    background:var(--card);
    border-radius:6px; border:1px solid var(--line);
  }

  .toggle{
    position:absolute; right:8px; top:50%; transform:translateY(-50%);
    border:1px solid var(--line); background:transparent; color:inherit;
    border-radius:10px; padding:6px 10px; cursor:pointer; font-size:.9rem;
  }

  .caps{ display:none; color:#ea580c; font-size:.92rem; }

  .msg-err{
    display:flex; align-items:center; gap:8px;
    border:1px solid #ef4444aa; color:#ef4444; padding:10px 12px; border-radius:12px;
    background: transparent;
  }

  .actions{ display:grid; gap:10px; }
  .btn{
    display:inline-grid; place-items:center;
    background:var(--accent); color:#fff; font-weight:800; letter-spacing:.2px;
    border:none; border-radius:12px; padding:14px 14px; cursor:pointer;
    transition: transform .06s ease, filter .15s ease;
  }
  .btn:hover{ filter:brightness(1.05); }
  .btn:active{ transform: translateY(1px); }
  .btn[disabled]{ opacity:.65; cursor:not-allowed; }

  .btn .spinner{
    width:18px; height:18px; border-radius:50%; border:3px solid rgba(255,255,255,.45);
    border-top-color:#fff; animation: spin .8s linear infinite; display:none;
  }
  .btn.loading .txt{ display:none; }
  .btn.loading .spinner{ display:inline-block; }

  .footrow{
    display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;
  }
  .check{ display:flex; align-items:center; gap:8px; color:var(--muted); }
  .link{ color:inherit; text-underline-offset:3px; }

  @keyframes spin{ to{ transform:rotate(360deg); } }
</style>
</head>
<body>

<div class="shell panel">
  <!-- IZQUIERDA: branding y mensaje -->
  <section class="left">
    <div class="brand">
      <div class="logo">U</div>
      <div>
        <span class="badge">UIAT Norte</span>
      </div>
    </div>
    <h2 class="title">Bienvenido al sistema<br>de Gestión UIAT Norte</h2>
    <p class="sub">Accede con tus credenciales institucionales para continuar.</p>
  </section>

  <!-- DERECHA: formulario -->
  <section class="right">
    <div class="card">
      <div class="hdr">
        <h1>Iniciar sesión</h1>
        <div class="hint">Ingresa tu correo y contraseña para entrar al panel.</div>
      </div>

      <?php if ($err): ?>
        <div class="msg-err">⚠️ <?= h($err) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off" id="loginForm">
        <!-- EMAIL -->
        <div class="field">
          <input class="control" type="text" name="email" id="email"
                 value="<?= h($email) ?>" placeholder=" " required
                 pattern="[^@\s]+@[^@\s]+" title="Formato: usuario@dominio">
          <label class="label" for="email">Correo</label>
        </div>

        <!-- PASSWORD -->
        <div class="field">
          <input class="control" type="password" name="password" id="password" placeholder=" " required>
          <label class="label" for="password">Contraseña</label>
          <button type="button" class="toggle" id="togglePwd" aria-label="Mostrar/ocultar contraseña">👁️</button>
        </div>

        <div class="caps" id="capsWarning">Bloq Mayús activado</div>

        <!-- BOTÓN -->
        <div class="actions">
          <button class="btn" type="submit" id="submitBtn">
            <span class="txt">Ingresar</span>
            <span class="spinner" aria-hidden="true"></span>
          </button>
        </div>

        <div class="footrow">
          <label class="check">
            <input type="checkbox" id="remember" style="accent-color:var(--accent)">
            Recordar correo
          </label>
          <a class="link hint" href="#" onclick="alert('Pide al administrador restablecer tu clave.');return false;">¿Olvidaste tu contraseña?</a>
        </div>
      </form>
    </div>
  </section>
</div>

<script>
  // Mostrar/ocultar contraseña
  const pwd = document.getElementById('password');
  const btn = document.getElementById('togglePwd');
  btn.addEventListener('click', () => {
    const is = pwd.type === 'password';
    pwd.type = is ? 'text' : 'password';
    btn.textContent = is ? '🙈' : '👁️';
  });

  // Bloq Mayús
  const caps = document.getElementById('capsWarning');
  pwd.addEventListener('keyup', e => {
    const on = e.getModifierState && e.getModifierState('CapsLock');
    caps.style.display = on ? 'block' : 'none';
  });

  // Recordar correo en localStorage
  const remember = document.getElementById('remember');
  const emailInput = document.getElementById('email');
  (function initRemember(){
    try{
      const saved = localStorage.getItem('uiat_email');
      if (saved) { emailInput.value = saved; remember.checked = true; emailInput.dispatchEvent(new Event('input')); }
    }catch(_){}
  })();

  document.getElementById('loginForm').addEventListener('submit', () => {
    const sb = document.getElementById('submitBtn');
    sb.classList.add('loading'); sb.setAttribute('disabled','disabled');
    try{
      if (remember.checked) localStorage.setItem('uiat_email', emailInput.value.trim());
      else localStorage.removeItem('uiat_email');
    }catch(_){}
  });

  // Dispara label flotante al cargar si hay valor
  document.querySelectorAll('.control').forEach(el=>{
    if(el.value) el.dispatchEvent(new Event('input'));
    el.addEventListener('input', ()=>{ /* placeholder flotante ya controlado por CSS */ });
  });
</script>

</body>
</html>