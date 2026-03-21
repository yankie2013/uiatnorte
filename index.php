<?php
// index.php — Panel estilo "Dashboard Pro" completo listo para pegar
// Producción: no mostrar errores en pantalla, solo registrar en logs
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
@ini_set('display_errors', '0');

require_once __DIR__ . '/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$yo = $_SESSION['user'];

// -------------------------
// Inclusión segura de posibles archivos de conexión
// -------------------------
$pdo = null;
$try_files = [
    __DIR__ . '/pdo.php',
    __DIR__ . '/db.php',
    __DIR__ . '/conexion.php',
    __DIR__ . '/conexion_db.php',
    __DIR__ . '/config.php',
];

foreach ($try_files as $f) {
    if (file_exists($f)) {
        @include_once $f;
    }
}

// Normalizar nombres de conexión
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (isset($db) && $db instanceof PDO) $pdo = $db;
    elseif (isset($conexion) && $conexion instanceof PDO) $pdo = $conexion;
    elseif (isset($conn) && $conn instanceof PDO) $pdo = $conn;
    else {
        // Intento con constantes DB_* (si existen)
        if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $pdo = new PDO($dsn, DB_USER, defined('DB_PASS') ? DB_PASS : '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (Throwable $e) {
                error_log("Index KPI DB connect error: " . $e->getMessage());
                $pdo = null;
            }
        }
    }
}

// -------------------------
// KPIs (si hay conexión)
$tot = $res = $pen = $dil = 0;
$kpi_error = null;
if ($pdo instanceof PDO) {
    try {
        $tot = (int) $pdo->query("SELECT COUNT(*) FROM accidentes")->fetchColumn();
        $res = (int) $pdo->query("SELECT COUNT(*) FROM accidentes WHERE estado = 'Resuelto'")->fetchColumn();
        $pen = (int) $pdo->query("SELECT COUNT(*) FROM accidentes WHERE estado = 'Pendiente'")->fetchColumn();
        $dil = (int) $pdo->query("SELECT COUNT(*) FROM accidentes WHERE estado = 'Con diligencias'")->fetchColumn();
    } catch (Throwable $e) {
        error_log("Index KPI query error: " . $e->getMessage());
        $kpi_error = $e->getMessage();
    }
}

$pct_res = $tot > 0 ? round(($res / $tot) * 100, 1) : 0;
$pct_pen = $tot > 0 ? round(($pen / $tot) * 100, 1) : 0;
$pct_dil = $tot > 0 ? round(($dil / $tot) * 100, 1) : 0;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>UIAT Norte — Panel</title>
<link rel="stylesheet" href="style_gian.css">
<style>
/* ---------- Dashboard Pro styles (modern + animated) ---------- */
:root{
  --bg: #050814;
  --panel: rgba(255,255,255,0.03);
  --muted: rgba(255,255,255,0.66);
  --glass: rgba(255,255,255,0.02);
  --gold: #d4af37;
  --green: #00ff9c;
  --garnet: #a12424;
  --card-radius: 12px;
  --transition-fast: 180ms;
  --transition-smooth: 420ms cubic-bezier(.2,.9,.3,1);
  font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
}
html,body{height:100%;margin:0;background:
  radial-gradient(900px 300px at 8% 8%, rgba(90,110,140,0.03), transparent),
  linear-gradient(180deg,var(--bg),#04111a); color:#eaf4ff; -webkit-font-smoothing:antialiased;}
.wrap{display:flex;justify-content:center;padding:48px 18px;box-sizing:border-box}
.container{width:100%;max-width:1200px}
.card{background: linear-gradient(180deg, rgba(10,14,20,0.78), rgba(10,14,20,0.58)); border-radius:16px; overflow:hidden; border:1px solid rgba(255,255,255,0.03); box-shadow: 0 30px 80px rgba(2,6,12,0.6);}

/* header */
.header{display:flex;justify-content:space-between;align-items:center;padding:18px 22px;border-bottom:1px solid rgba(255,255,255,0.02)}
.brand{display:flex;align-items:center;gap:12px;font-weight:800}
.logo{width:26px;height:26px;border-radius:6px;background:linear-gradient(45deg,#4fd1c5,#2b6cb0);display:inline-block}
.user-info{font-size:13px;color:var(--muted);text-align:right}
.logout-btn{padding:8px 12px;border-radius:10px;background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));border:1px solid rgba(255,255,255,0.04);color:var(--muted);text-decoration:none;font-weight:700;display:inline-flex;align-items:center;gap:8px;}

/* KPIs top section */
.kpis-area{padding:20px 22px 10px 22px}
.kpis-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.kpi-card{
  background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
  border-radius:var(--card-radius); padding:18px; min-height:100px; position:relative;
  border:1px solid rgba(255,255,255,0.035);
  transition: transform var(--transition-smooth), box-shadow var(--transition-smooth);
  transform: translateZ(0);
  overflow: hidden;
}
.kpi-card:hover{
  transform: translateY(-8px) scale(1.01);
  box-shadow: 0 30px 60px rgba(2,6,12,0.6), 0 6px 20px rgba(0,0,0,0.4);
}
.kpi-label{font-weight:800;color:var(--muted);font-size:13px}
.kpi-value{font-size:34px;font-weight:900;margin-top:8px}
.kpi-sub{font-size:13px;color:rgba(255,255,255,0.72);margin-top:6px}
.kpi-pill{position:absolute;right:14px;top:14px;background:linear-gradient(180deg,rgba(255,255,255,0.03),rgba(255,255,255,0.02));padding:6px 10px;border-radius:999px;font-weight:800;font-size:12px;backdrop-filter: blur(6px);border:1px solid rgba(255,255,255,0.03);}
.progress-wrap{height:8px;background:rgba(255,255,255,0.03);border-radius:999px;margin-top:10px;overflow:hidden}
.progress-bar{height:100%;border-radius:999px;transition:width 900ms cubic-bezier(.2,.9,.3,1);box-shadow:0 6px 18px rgba(0,0,0,0.45) inset}

/* subtle animated gradient overlay for pro feel */
.kpi-card::after{
  content:""; position:absolute; inset:0; pointer-events:none;
  background: linear-gradient(120deg, rgba(255,255,255,0.00) 0%, rgba(255,255,255,0.01) 30%, rgba(255,255,255,0.00) 100%);
  mix-blend-mode: overlay; opacity:0.6;
  transform: translateX(-40%); transition: transform 1200ms ease;
}
.kpi-card:hover::after{ transform: translateX(10%); }

/* special color accents (borders + subtle glows) */
.kpi-resuelto { border-left: 4px solid rgba(0,200,120,0.18); }
.kpi-pendiente { border-left: 4px solid rgba(180,50,50,0.12); }
.kpi-dil { border-left: 4px solid rgba(120,80,180,0.12); }

/* separation */
.kpi-actions-gap{height:26px}

/* Actions area (tiles) */
.actions-area{padding:22px 22px 28px 22px; border-top:1px solid rgba(255,255,255,0.02)}
.actions-title{font-weight:900;color:#dfe;margin-bottom:14px;font-size:15px}
.tiles-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.tile{
  display:flex;align-items:center;gap:14px;padding:14px;border-radius:12px;background:rgba(255,255,255,0.015);
  color:var(--muted);text-decoration:none;border:1px solid rgba(255,255,255,0.03);
  transition: transform 420ms cubic-bezier(.2,.9,.3,1), box-shadow 420ms cubic-bezier(.2,.9,.3,1), border-color var(--transition-fast);
  transform: translateZ(0);
}
.tile:hover{
  transform: translateY(-10px);
  box-shadow: 0 30px 60px rgba(2,6,12,0.6), 0 10px 30px rgba(0,0,0,0.55);
}
.tile .icon{width:52px;height:52;border-radius:10px;background:rgba(255,255,255,0.02);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;transition: transform var(--transition-fast)}
.tile:hover .icon{ transform: scale(1.06); }

/* colorized border classes with glow */
.tile-gold { border: 1px solid rgba(212,175,55,0.95); box-shadow: 0 8px 30px rgba(212,175,55,0.08); }
.tile-gold .icon{ background: linear-gradient(90deg, rgba(212,175,55,0.08), rgba(212,175,55,0.02)); }
.tile-green { border: 1px solid rgba(0,255,156,0.95); box-shadow: 0 8px 30px rgba(0,255,156,0.06); }
.tile-green .icon{ background: linear-gradient(90deg, rgba(0,255,156,0.06), rgba(0,255,156,0.02)); }
.tile-red { border: 1px solid rgba(161,36,36,0.95); box-shadow: 0 8px 30px rgba(161,36,36,0.06); }
.tile-red .icon{ background: linear-gradient(90deg, rgba(161,36,36,0.06), rgba(161,36,36,0.02)); }

.tile .txt .h{font-weight:900;color:#fff}
.tile .txt .p{font-size:13px;color:var(--muted)}

/* duotone SVG tweaks */
.icon svg { display:block; width:28px; height:28px; }
.duo-fill { opacity:0.92; }
.duo-accent { opacity:0.95; mix-blend-mode: screen; }

/* responsive */
@media (max-width:1000px){
  .kpis-row{grid-template-columns:repeat(2,1fr)}
  .tiles-grid{grid-template-columns:repeat(2,1fr)}
}
@media (max-width:640px){
  .kpis-row{grid-template-columns:repeat(1,1fr)}
  .tiles-grid{grid-template-columns:repeat(1,1fr)}
  .header{flex-direction:column;align-items:flex-start;gap:10px}
}

/* footer */
.footer{padding:14px 22px;border-top:1px solid rgba(255,255,255,0.02);color:var(--muted);font-size:13px;text-align:left}
</style>
</head>
<body>
<div class="wrap">
  <div class="container">
    <div class="card" role="region" aria-label="Panel UIAT Norte">

      <!-- header -->
      <div class="header">
        <div class="brand"><span class="logo" aria-hidden="true"></span> Panel — UIAT Norte</div>
        <div style="display:flex;align-items:center;gap:14px">
          <div class="user-info">
            <div style="font-size:12px;color:var(--muted)">Conectado como</div>
            <div style="font-weight:900;color:#fff"><?= htmlspecialchars($yo['nombre'] ?? $yo['email'], ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <a class="logout-btn" href="logout.php" aria-label="Salir">Salir</a>
        </div>
      </div>

      <!-- KPIs -->
      <div class="kpis-area">
        <div class="kpis-row" role="list">
          <div class="kpi-card" role="listitem" aria-label="Total Accidentes">
            <div class="kpi-label">Total Accidentes</div>
            <div class="kpi-value"><?= number_format($tot) ?></div>
            <div class="kpi-sub">Casos registrados en la base</div>
            <div class="kpi-pill">100%</div>
          </div>

          <div class="kpi-card kpi-resuelto" role="listitem" aria-label="Casos Resueltos">
            <div class="kpi-label">Casos Resueltos</div>
            <div class="kpi-value"><?= number_format($res) ?></div>
            <div class="kpi-sub"><?= $pct_res ?>% del total</div>
            <div class="kpi-pill"><?= $pct_res ?>%</div>
            <div class="progress-wrap" aria-hidden="true">
              <div class="progress-bar" id="barResuelto" style="width:0%; background:linear-gradient(90deg,#2db46a,#0aa870)"></div>
            </div>
          </div>

          <div class="kpi-card kpi-pendiente" role="listitem" aria-label="Casos Pendientes">
            <div class="kpi-label">Casos Pendientes</div>
            <div class="kpi-value"><?= number_format($pen) ?></div>
            <div class="kpi-sub"><?= $pct_pen ?>% del total</div>
            <div class="kpi-pill"><?= $pct_pen ?>%</div>
            <div class="progress-wrap" aria-hidden="true">
              <div class="progress-bar" id="barPendiente" style="width:0%; background:linear-gradient(90deg,#b33b3b,#7a1111)"></div>
            </div>
          </div>

          <div class="kpi-card kpi-dil" role="listitem" aria-label="Con Diligencias">
            <div class="kpi-label">Con Diligencias</div>
            <div class="kpi-value"><?= number_format($dil) ?></div>
            <div class="kpi-sub"><?= $pct_dil ?>% del total</div>
            <div class="kpi-pill"><?= $pct_dil ?>%</div>
            <div class="progress-wrap" aria-hidden="true">
              <div class="progress-bar" id="barDil" style="width:0%; background:linear-gradient(90deg,#5c4aa1,#8a6be0)"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="kpi-actions-gap" aria-hidden="true"></div>

      <!-- Actions (tiles) - Lista de Accidentes first and styled gold; personas/vehículos green; others garnet -->
      <div class="actions-area">
        <div class="actions-title">Acciones rápidas</div>
        <div class="tiles-grid" role="list">
          <!-- 1) Lista de Accidentes — dorado -->
          <a class="tile tile-gold" href="accidente_listar.php" role="listitem" aria-label="Lista de Accidentes">
            <div class="icon" aria-hidden="true">
              <!-- duotone file icon (gold accents) -->
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs><linearGradient id="g_gold" x1="0" x2="1"><stop offset="0" stop-color="#ffd98a"/><stop offset="1" stop-color="#d4af37"/></linearGradient></defs>
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="url(#g_gold)" class="duo-fill"/>
                <path d="M14 2v6h6" stroke="#2b2b2b" stroke-width="1.2" class="duo-accent"/>
              </svg>
            </div>
            <div class="txt"><div class="h">Lista de Accidentes</div><div class="p">Gestión de casos</div></div>
          </a>

          <!-- 2) Registrar Persona — verde -->
          <a class="tile tile-green" href="persona_listar.php" role="listitem" aria-label="Registrar Persona">
            <div class="icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs><linearGradient id="g_green" x1="0" x2="1"><stop offset="0" stop-color="#8affd6"/><stop offset="1" stop-color="#00ff9c"/></linearGradient></defs>
                <circle cx="12" cy="7" r="4" fill="url(#g_green)" class="duo-fill"/>
                <path d="M4 20c0-3.314 2.686-6 6-6h4c3.314 0 6 2.686 6 6" stroke="#0b2b1b" stroke-width="1.2" class="duo-accent"/>
              </svg>
            </div>
            <div class="txt"><div class="h">Registrar Persona</div><div class="p">DNI/CE/PAS completos</div></div>
          </a>

          <!-- 3) Registrar Vehículo — verde -->
          <a class="tile tile-green" href="vehiculo_listar.php" role="listitem" aria-label="Registrar Vehículo">
            <div class="icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs><linearGradient id="g_green2" x1="0" x2="1"><stop offset="0" stop-color="#8affd6"/><stop offset="1" stop-color="#00ff9c"/></linearGradient></defs>
                <rect x="2.5" y="9" width="19" height="8" rx="2.2" fill="url(#g_green2)" class="duo-fill"/>
                <path d="M5 19a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm14 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" stroke="#062816" stroke-width="1.1" class="duo-accent"/>
              </svg>
            </div>
            <div class="txt"><div class="h">Registrar Vehículo</div><div class="p">Gestión de vehículos</div></div>
          </a>

          <!-- 4) Oficios — granate -->
          <a class="tile tile-red" href="oficios_listar.php" role="listitem" aria-label="Oficios">
            <div class="icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs><linearGradient id="g_red" x1="0" x2="1"><stop offset="0" stop-color="#f8b7b7"/><stop offset="1" stop-color="#a12424"/></linearGradient></defs>
                <path d="M3 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" fill="url(#g_red)" class="duo-fill"/>
                <path d="M8 11h8" stroke="#3d1313" stroke-width="1.1" class="duo-accent"/>
              </svg>
            </div>
            <div class="txt"><div class="h">Oficios</div><div class="p">Registro y consulta</div></div>
          </a>

          <!-- 5) Comisarías — granate -->
          <a class="tile tile-red" href="comisarias_listar.php" role="listitem" aria-label="Comisarías">
            <div class="icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs><linearGradient id="g_red2" x1="0" x2="1"><stop offset="0" stop-color="#f8b7b7"/><stop offset="1" stop-color="#a12424"/></linearGradient></defs>
                <rect x="3" y="3" width="18" height="18" rx="2" fill="url(#g_red2)" class="duo-fill"/>
                <g stroke="#3d1313" stroke-width="1.0" class="duo-accent">
                  <path d="M8 7h.01M12 7h.01M16 7h.01M8 11h.01M12 11h.01M16 11h.01"/>
                </g>
              </svg>
            </div>
            <div class="txt"><div class="h">Comisarías</div><div class="p">Administración</div></div>
          </a>
        </div>
      </div>

      <div class="footer">UIAT Norte · Panel principal — Estilo Pro</div>
    </div>
  </div>
</div>

<script>
// Animate progress bars on load for smooth effect
document.addEventListener('DOMContentLoaded', function(){
  var resPct = <?= json_encode($pct_res) ?>;
  var penPct = <?= json_encode($pct_pen) ?>;
  var dilPct = <?= json_encode($pct_dil) ?>;

  // small delay so the transition is visible
  setTimeout(function(){
    var b1 = document.getElementById('barResuelto');
    var b2 = document.getElementById('barPendiente');
    var b3 = document.getElementById('barDil');
    if (b1) b1.style.width = resPct + '%';
    if (b2) b2.style.width = penPct + '%';
    if (b3) b3.style.width = dilPct + '%';
  }, 120);
});
</script>
</body>
</html>