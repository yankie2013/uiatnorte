<?php
require __DIR__.'/auth.php';
require __DIR__.'/db.php';

use App\Support\Auth;

if (session_status()===PHP_SESSION_NONE) session_start();

header('Content-Type: text/html; charset=utf-8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

/* Si ya está logueado, envía al panel */
if (!empty($_SESSION['user'])) {
  header('Location: ' . Auth::postLoginDestination()); exit;
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$err=''; $email='';
$flash = trim((string) ($_SESSION['flash'] ?? ''));
unset($_SESSION['flash']);

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
      header('Location: ' . Auth::postLoginDestination()); exit;
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Acceso · Investigación de Accidentes de Tránsito Lima Norte</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#142f2a">
<link rel="icon" href="favicon.ico">
<link rel="stylesheet" href="assets/css/login.css?v=2">
</head>
<body>
<svg class="icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6z"/><path d="m8 12 3 3 5-6"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/></symbol>
  <symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></symbol>
  <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></symbol>
  <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M4 12h16m-6-6 6 6-6 6"/></symbol>
  <symbol id="i-file" viewBox="0 0 24 24"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 13h8M8 17h5"/></symbol>
</svg>
<main class="login-shell">
  <section class="welcome" aria-labelledby="welcome-title">
    <div class="institution"><span class="institution-line"></span> POLICÍA NACIONAL DEL PERÚ</div>
    <div class="division-brand">
      <div class="emblem"><img src="assets/img/divpiat-logo.jpg" width="244" height="206" alt="Emblema de la DIVPIAT, Policía Nacional del Perú"></div>
      <div class="division-name"><strong>DIVPIAT</strong><span>División de Prevención e Investigación<br>de Accidentes de Tránsito</span></div>
    </div>
    <div class="welcome-copy">
      <span class="eyebrow">SISTEMA DE GESTIÓN DE LA INFORMACIÓN</span>
      <h1 id="welcome-title">Departamento de<br>Investigación de<br>Accidentes de Tránsito</h1>
      <div class="location"><span></span> Lima Norte</div>
      <p>Información organizada para fortalecer<br class="desktop-break"> la investigación y el trabajo policial.</p>
    </div>
    <div class="welcome-footer"><svg class="icon"><use href="#i-file"/></svg><span>Gestión documental <b>·</b> Seguimiento de investigaciones</span></div>
  </section>
  <section class="login-panel" aria-labelledby="login-title">
    <div class="panel-top"><svg class="icon"><use href="#i-lock"/></svg> ACCESO INSTITUCIONAL</div>
    <div class="form-container">
      <span class="form-icon"><svg class="icon"><use href="#i-lock"/></svg></span>
      <div class="form-heading"><span class="form-eyebrow">TU ESPACIO DE TRABAJO</span><h2 id="login-title">Bienvenido al sistema</h2><p>Ingresa tus credenciales para continuar<br>con la gestión de la información.</p></div>
      <?php if ($err): ?><div class="message error" role="alert"><?= h($err) ?></div><?php endif; ?>
      <?php if ($flash): ?><div class="message info" role="status"><?= h($flash) ?></div><?php endif; ?>
      <form method="post" id="loginForm">
        <div class="field">
          <label for="email">Correo institucional</label>
          <div class="input-wrap"><svg class="icon input-icon"><use href="#i-mail"/></svg><input type="text" name="email" id="email" value="<?= h($email) ?>" placeholder="Ingresa tu correo" required pattern="[^@\s]+@[^@\s]+" title="Formato: usuario@dominio" inputmode="email" autocomplete="username" autocapitalize="none" spellcheck="false"></div>
        </div>
        <div class="field">
          <label for="password">Contraseña</label>
          <div class="input-wrap"><svg class="icon input-icon"><use href="#i-lock"/></svg><input type="password" name="password" id="password" placeholder="Ingresa tu contraseña" required autocomplete="current-password" aria-describedby="capsWarning"><button type="button" class="toggle" id="togglePwd" aria-label="Mostrar contraseña" aria-pressed="false"><svg class="icon"><use href="#i-eye"/></svg></button></div>
        </div>
        <p class="caps" id="capsWarning" role="status" hidden>Bloq Mayús está activado.</p>
        <div class="form-options"><label class="remember"><input type="checkbox" id="remember"> Recordar correo</label><button type="button" class="help-link" id="helpBtn" aria-expanded="false" aria-controls="recoveryHelp">¿Olvidaste tu contraseña?</button></div>
        <p class="recovery-help" id="recoveryHelp" hidden>Contacta al administrador del sistema para restablecer tu contraseña.</p>
        <button class="submit" type="submit" id="submitBtn"><span class="txt">Iniciar sesión</span><svg class="icon arrow"><use href="#i-arrow"/></svg><span class="spinner" aria-hidden="true"></span></button>
      </form>
      <div class="access-note"><svg class="icon"><use href="#i-shield"/></svg><span>Acceso exclusivo para personal autorizado.</span></div>
    </div>
    <footer class="panel-footer"><span>DIVPIAT <b>/</b> Lima Norte</span><span>Gestión de la información</span></footer>
  </section>
</main>
<script>
  const pwd = document.getElementById('password');
  const toggle = document.getElementById('togglePwd');
  toggle.addEventListener('click', () => {
    const visible = pwd.type === 'password';
    pwd.type = visible ? 'text' : 'password';
    toggle.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
    toggle.setAttribute('aria-pressed', String(visible));
  });
  const caps = document.getElementById('capsWarning');
  ['keydown', 'keyup'].forEach(event => pwd.addEventListener(event, e => {
    caps.hidden = !(e.getModifierState && e.getModifierState('CapsLock'));
  }));
  pwd.addEventListener('blur', () => { caps.hidden = true; });
  const help = document.getElementById('helpBtn');
  help.addEventListener('click', () => {
    const panel = document.getElementById('recoveryHelp');
    panel.hidden = !panel.hidden;
    help.setAttribute('aria-expanded', String(!panel.hidden));
  });
  const remember = document.getElementById('remember');
  const email = document.getElementById('email');
  try {
    const saved = localStorage.getItem('uiat_email');
    if (saved) { if (!email.value) email.value = saved; remember.checked = true; }
  } catch (_) {}
  const form = document.getElementById('loginForm');
  const submit = document.getElementById('submitBtn');
  form.addEventListener('submit', () => {
    submit.classList.add('loading'); submit.disabled = true;
    submit.querySelector('.txt').textContent = 'Ingresando…';
    form.setAttribute('aria-busy', 'true');
    try {
      if (remember.checked) localStorage.setItem('uiat_email', email.value.trim());
      else localStorage.removeItem('uiat_email');
    } catch (_) {}
  });
  window.addEventListener('pageshow', () => {
    submit.classList.remove('loading'); submit.disabled = false;
    submit.querySelector('.txt').textContent = 'Iniciar sesión';
    form.removeAttribute('aria-busy');
  });
</script>
</body>
</html>
