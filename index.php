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
<link rel="stylesheet" href="assets/theme/theme.css">
<script src="assets/theme/theme.js" defer></script>
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

/* ---------- Apple Liquid Glass + tema claro/oscuro ---------- */
html{
  --dash-bg:#edf3fa;--dash-bg-2:#f8fbff;--dash-fg:#14213d;--dash-muted:#64748b;
  --dash-glass:rgba(255,255,255,.62);--dash-glass-strong:rgba(255,255,255,.76);
  --dash-border:rgba(255,255,255,.72);--dash-line:rgba(148,163,184,.2);
  --dash-shadow:0 26px 70px rgba(30,64,110,.14);--dash-card-shadow:0 13px 28px rgba(30,64,110,.09);
}
html[data-theme-resolved="dark"]{
  --dash-bg:#07101f;--dash-bg-2:#0c1728;--dash-fg:#e8eef8;--dash-muted:#9cabc0;
  --dash-glass:rgba(15,23,42,.58);--dash-glass-strong:rgba(22,33,52,.72);
  --dash-border:rgba(148,163,184,.16);--dash-line:rgba(148,163,184,.13);
  --dash-shadow:0 28px 75px rgba(0,0,0,.34);--dash-card-shadow:0 14px 32px rgba(0,0,0,.2);
}
html,body{background:
  radial-gradient(circle at 12% 8%,rgba(99,102,241,.13),transparent 27%),
  radial-gradient(circle at 88% 88%,rgba(6,182,212,.12),transparent 30%),
  linear-gradient(145deg,var(--dash-bg-2),var(--dash-bg))!important;color:var(--dash-fg)!important}
.wrap{min-height:100%;align-items:center;padding-block:38px}
.card{
  position:relative;isolation:isolate;border:1px solid var(--dash-border);border-radius:30px;
  background:linear-gradient(145deg,var(--dash-glass-strong),var(--dash-glass));
  box-shadow:inset 0 1px rgba(255,255,255,.55),var(--dash-shadow);
  backdrop-filter:blur(28px) saturate(155%);-webkit-backdrop-filter:blur(28px) saturate(155%);
}
.card::before{content:"";position:absolute;z-index:-1;inset:0;border-radius:inherit;background:radial-gradient(circle at 15% 0,rgba(255,255,255,.32),transparent 25%),radial-gradient(circle at 95% 100%,rgba(99,102,241,.08),transparent 32%);pointer-events:none}
.header{border-color:var(--dash-line)}
.brand,.kpi-value,.actions-title,.tile .txt .h,.user-info div:last-child{color:var(--dash-fg)!important}
.user-info,.kpi-label,.kpi-sub,.tile .txt .p,.footer{color:var(--dash-muted)!important}
.logo{border:1px solid rgba(255,255,255,.62);border-radius:9px;box-shadow:inset 0 1px rgba(255,255,255,.7),0 8px 17px rgba(6,182,212,.18)}
.logout-btn{border-color:var(--dash-border);background:linear-gradient(145deg,var(--dash-glass-strong),var(--dash-glass));color:var(--dash-fg);box-shadow:inset 0 1px rgba(255,255,255,.45),0 7px 16px rgba(15,23,42,.08)}
.kpi-card{
  border:1px solid var(--dash-border);border-radius:22px;
  background:linear-gradient(145deg,var(--dash-glass-strong),var(--dash-glass));
  box-shadow:inset 0 1px rgba(255,255,255,.55),var(--dash-card-shadow);
  backdrop-filter:blur(20px) saturate(145%);-webkit-backdrop-filter:blur(20px) saturate(145%);
}
.kpi-card:hover{box-shadow:inset 0 1px rgba(255,255,255,.65),0 22px 42px rgba(30,64,110,.17)}
.kpi-pill{border-color:var(--dash-border);background:var(--dash-glass-strong);color:var(--dash-fg);box-shadow:inset 0 1px rgba(255,255,255,.5)}
.progress-wrap{background:rgba(100,116,139,.13)}
.kpi-resuelto{border-left:3px solid rgba(16,185,129,.55)}.kpi-pendiente{border-left:3px solid rgba(239,68,68,.48)}.kpi-dil{border-left:3px solid rgba(139,92,246,.5)}
.actions-area,.footer{border-color:var(--dash-line)}
.tile{
  border:1px solid var(--dash-border);border-radius:20px;
  background:linear-gradient(145deg,var(--dash-glass-strong),var(--dash-glass));color:var(--dash-fg);
  box-shadow:inset 0 1px rgba(255,255,255,.5),var(--dash-card-shadow);
  backdrop-filter:blur(18px) saturate(145%);-webkit-backdrop-filter:blur(18px) saturate(145%);
}
.tile:hover{transform:translateY(-6px) scale(1.01);box-shadow:inset 0 1px rgba(255,255,255,.65),0 20px 38px rgba(30,64,110,.16)}
.tile .icon{border:1px solid var(--dash-border);border-radius:15px;background:rgba(255,255,255,.16);box-shadow:inset 0 1px rgba(255,255,255,.45)}
.tile-gold{border-color:rgba(212,175,55,.48)}.tile-green{border-color:rgba(16,185,129,.42)}.tile-red{border-color:rgba(239,68,68,.35)}
html[data-theme-resolved="dark"] .card::before{opacity:.42}
html[data-theme-resolved="dark"] .kpi-card,html[data-theme-resolved="dark"] .tile,html[data-theme-resolved="dark"] .logout-btn{box-shadow:inset 0 1px rgba(255,255,255,.09),var(--dash-card-shadow)}

/* ---------- Hero inmersivo inspirado en interfaces macOS modernas ---------- */
.container{max-width:1460px}
.card{background:
  radial-gradient(circle at 29% 37%,rgba(40,190,255,.22),transparent 26%),
  radial-gradient(circle at 90% 15%,rgba(124,58,237,.17),transparent 34%),
  linear-gradient(135deg,rgba(239,248,255,.8),rgba(238,235,255,.68));
}
html[data-theme-resolved="dark"] .card{background:
  radial-gradient(circle at 29% 38%,rgba(14,165,233,.25),transparent 29%),
  radial-gradient(circle at 90% 18%,rgba(124,58,237,.28),transparent 38%),
  linear-gradient(135deg,rgba(5,43,94,.88),rgba(48,24,112,.83));
}
.dashboard-hero{display:grid;grid-template-columns:minmax(280px,.9fr) minmax(360px,1.1fr);gap:clamp(35px,7vw,100px);align-items:center;min-height:420px;padding:42px clamp(38px,7vw,105px) 34px;border-bottom:1px solid var(--dash-line)}
.hero-visual{position:relative;display:grid;place-items:center;min-height:330px;perspective:900px}
.hero-glow{position:absolute;width:min(90%,430px);aspect-ratio:1;border-radius:50%;background:radial-gradient(circle,rgba(34,211,238,.34),rgba(59,130,246,.12) 42%,transparent 72%);filter:blur(10px);animation:heroGlow 4.5s ease-in-out infinite}
.hero-gem{position:relative;display:grid;place-items:center;width:min(72vw,315px);aspect-ratio:1;border:1px solid rgba(255,255,255,.66);border-radius:31% 31% 39% 39% / 28% 28% 45% 45%;background:
  radial-gradient(circle at 30% 18%,rgba(255,255,255,.78),transparent 18%),
  radial-gradient(circle at 66% 70%,rgba(34,211,238,.4),transparent 34%),
  linear-gradient(155deg,rgba(103,232,249,.82),rgba(30,64,175,.94) 58%,rgba(76,29,149,.94));
  box-shadow:inset 0 2px 2px rgba(255,255,255,.72),inset 0 -24px 38px rgba(15,23,42,.26),0 28px 45px rgba(15,23,42,.32),0 0 45px rgba(34,211,238,.2);
  transform:rotateX(8deg) rotateZ(-2deg);animation:heroFloat 5s ease-in-out infinite;overflow:hidden}
.hero-gem::before{content:"";position:absolute;inset:8px;border:1px solid rgba(255,255,255,.28);border-radius:inherit;box-shadow:inset 0 0 28px rgba(255,255,255,.12)}
.hero-gem::after{content:"";position:absolute;left:12%;right:12%;bottom:-13px;height:23px;border-radius:50%;background:rgba(2,6,23,.28);filter:blur(10px)}
.hero-gem-mark{position:relative;z-index:1;text-align:center;color:#fff;text-shadow:0 5px 15px rgba(15,23,42,.28)}
.hero-gem-mark strong{display:block;font-size:clamp(48px,6vw,78px);font-weight:950;letter-spacing:-.07em;line-height:.88}
.hero-gem-mark span{display:block;margin-top:13px;font-size:12px;font-weight:850;letter-spacing:.22em;text-transform:uppercase}
.hero-copy{position:relative;z-index:1}
.hero-eyebrow{display:inline-flex;align-items:center;gap:8px;margin-bottom:13px;padding:7px 12px;border:1px solid var(--dash-border);border-radius:999px;background:var(--dash-glass);color:var(--dash-muted);font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;backdrop-filter:blur(14px)}
.hero-title{margin:0;color:var(--dash-fg);font-size:clamp(40px,5vw,68px);font-weight:500;line-height:1;letter-spacing:-.045em}
.hero-description{max-width:600px;margin:19px 0 27px;color:var(--dash-muted);font-size:clamp(16px,1.8vw,21px);line-height:1.48}
.hero-links{display:grid;gap:12px;max-width:520px}
.hero-link{display:flex;align-items:center;gap:13px;padding:10px 12px;border-radius:16px;color:var(--dash-fg);font-weight:850;text-decoration:none;transition:transform .2s ease,background .2s ease}
.hero-link:hover{transform:translateX(8px);background:rgba(255,255,255,.12)}
.hero-link-icon{display:grid;place-items:center;width:38px;height:38px;border:1px solid rgba(255,255,255,.32);border-radius:13px;background:linear-gradient(145deg,rgba(56,189,248,.72),rgba(37,99,235,.8));color:#fff;box-shadow:inset 0 1px rgba(255,255,255,.5),0 8px 17px rgba(37,99,235,.2)}
@keyframes heroFloat{0%,100%{transform:translateY(0) rotateX(8deg) rotateZ(-2deg)}50%{transform:translateY(-12px) rotateX(5deg) rotateZ(1deg)}}
@keyframes heroGlow{0%,100%{transform:scale(.95);opacity:.72}50%{transform:scale(1.06);opacity:1}}
@media(prefers-reduced-motion:reduce){.hero-gem,.hero-glow{animation:none}}
@media(max-width:850px){.dashboard-hero{grid-template-columns:1fr;padding:30px 24px}.hero-visual{min-height:280px}.hero-gem{width:min(72vw,260px)}.hero-copy{text-align:center}.hero-description,.hero-links{margin-left:auto;margin-right:auto}.hero-link{text-align:left}}

/* ---------- Composición panorámica: aprovechar toda la pantalla ---------- */
.wrap{display:block;min-height:100%;padding:18px 24px}
.container{width:100%;max-width:none}
.card{width:100%;min-height:calc(100vh - 36px);border-radius:38px;box-sizing:border-box;overflow:hidden}
.header{position:relative;min-height:68px;padding:14px 28px 14px 92px;box-sizing:border-box}
.header::before{content:"";position:absolute;left:30px;top:50%;width:13px;height:13px;border-radius:50%;background:#ff5f57;box-shadow:22px 0 #febc2e,44px 0 #28c840;transform:translateY(-50%)}
.brand{font-size:15px}.logo{width:30px;height:30px}
.dashboard-hero{grid-template-columns:minmax(380px,.95fr) minmax(520px,1.05fr);min-height:clamp(480px,55vh,650px);padding:44px clamp(55px,7vw,130px);gap:clamp(55px,9vw,150px)}
.hero-visual{min-height:420px}
.hero-glow{width:min(95%,560px)}
.hero-gem{width:clamp(330px,25vw,440px)}
.hero-gem-mark strong{font-size:clamp(72px,6vw,110px)}
.hero-gem-mark span{font-size:14px}
.hero-title{max-width:780px;font-size:clamp(60px,6vw,102px)}
.hero-description{max-width:760px;font-size:clamp(18px,1.55vw,26px)}
.hero-links{max-width:700px;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.hero-link{min-height:74px;padding:13px 15px;border:1px solid var(--dash-border);border-radius:22px;background:linear-gradient(145deg,var(--dash-glass-strong),var(--dash-glass));box-shadow:inset 0 1px rgba(255,255,255,.5),0 11px 24px rgba(30,64,110,.1)}
.hero-link:hover{transform:translateY(-5px);background:var(--dash-glass-strong)}
.hero-link-icon{flex:0 0 42px;width:42px;height:42px;border-radius:15px}
.kpis-area{padding:28px 32px 12px}.kpis-row{gap:20px}.kpi-card{min-height:128px;padding:22px}.kpi-value{font-size:42px}
.kpi-actions-gap{height:18px}
.actions-area{padding:28px 32px 38px}.actions-title{font-size:18px;margin-bottom:18px}.tiles-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.tile{min-height:74px;padding:16px 18px}.tile .icon{width:56px;height:56px}.tile .txt .h{font-size:16px}.tile .txt .p{font-size:12px}
.footer{padding:17px 32px}
@media(max-width:1350px){
  .dashboard-hero{grid-template-columns:minmax(300px,.85fr) minmax(440px,1.15fr);padding-inline:55px;gap:60px}
  .hero-links{grid-template-columns:1fr}.hero-link{min-height:58px}
  .tiles-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
}
@media(max-width:950px){
  .wrap{padding:10px}.card{min-height:calc(100vh - 20px);border-radius:28px}
  .dashboard-hero{grid-template-columns:1fr;min-height:0;padding:34px 24px;gap:20px}
  .hero-visual{min-height:320px}.hero-gem{width:min(68vw,310px)}
  .hero-copy{text-align:center}.hero-description,.hero-links{margin-left:auto;margin-right:auto}.hero-link{text-align:left}
  .tiles-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:640px){
  .header{padding-left:74px}.header::before{left:20px;width:11px;height:11px;box-shadow:18px 0 #febc2e,36px 0 #28c840}
  .hero-title{font-size:45px}.kpis-area,.actions-area{padding-inline:16px}.tiles-grid{grid-template-columns:1fr}
}
/* ---------- Centro de mando Liquid Glass ---------- */
.card{min-height:auto;max-width:none!important;overflow:hidden}
.command-intro{position:relative;display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:28px;align-items:stretch;padding:34px 32px 18px;overflow:hidden}
.command-intro::before{content:"";position:absolute;width:430px;height:430px;right:-150px;top:-245px;border-radius:50%;background:radial-gradient(circle,rgba(139,92,246,.23),rgba(56,189,248,.09) 45%,transparent 70%);filter:blur(3px);pointer-events:none}
.welcome-panel,.case-orbit{position:relative;border:1px solid var(--dash-border);background:linear-gradient(135deg,rgba(255,255,255,.46),rgba(255,255,255,.16));box-shadow:inset 0 1px rgba(255,255,255,.7),0 18px 45px rgba(30,64,110,.1);backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%)}
.welcome-panel{min-height:210px;padding:34px 36px;border-radius:30px;overflow:hidden}
.welcome-panel::after{content:"";position:absolute;width:240px;height:240px;right:-60px;bottom:-150px;border-radius:50%;background:linear-gradient(145deg,rgba(34,211,238,.3),rgba(99,102,241,.18));filter:blur(2px)}
.welcome-kicker{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border:1px solid var(--dash-border);border-radius:999px;background:rgba(255,255,255,.22);color:var(--dash-muted);font-size:11px;font-weight:850;letter-spacing:.1em;text-transform:uppercase}
.welcome-kicker::before{content:"";width:7px;height:7px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 5px rgba(34,197,94,.12)}
.welcome-title{position:relative;z-index:1;max-width:700px;margin:20px 0 10px;color:var(--dash-fg);font-size:clamp(30px,3.5vw,52px);font-weight:750;line-height:1.02;letter-spacing:-.045em}
.welcome-copy{position:relative;z-index:1;max-width:660px;margin:0;color:var(--dash-muted);font-size:15px;line-height:1.55}
.welcome-actions{position:relative;z-index:1;display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}
.primary-action,.secondary-action{display:inline-flex;align-items:center;gap:9px;min-height:44px;padding:0 17px;border-radius:15px;font-size:13px;font-weight:850;text-decoration:none;transition:transform .22s ease,box-shadow .22s ease,background .22s ease}
.primary-action{color:#fff;background:linear-gradient(135deg,#2563eb,#7c3aed);box-shadow:inset 0 1px rgba(255,255,255,.35),0 12px 24px rgba(79,70,229,.24)}
.secondary-action{color:var(--dash-fg);border:1px solid var(--dash-border);background:rgba(255,255,255,.2)}
.primary-action:hover,.secondary-action:hover{transform:translateY(-3px)}
.case-orbit{display:grid;place-items:center;min-height:210px;border-radius:30px;overflow:hidden}
.orbit-ring{position:relative;display:grid;place-items:center;width:154px;height:154px;border-radius:50%;background:conic-gradient(from 15deg,#22d3ee,#6366f1 44%,#a855f7 72%,rgba(255,255,255,.2) 72%);box-shadow:0 20px 44px rgba(79,70,229,.22);animation:orbitBreathe 4s ease-in-out infinite}
.orbit-ring::before{content:"";position:absolute;inset:9px;border-radius:50%;background:linear-gradient(145deg,var(--dash-glass-strong),var(--dash-glass));box-shadow:inset 0 1px rgba(255,255,255,.7)}
.orbit-content{position:relative;text-align:center}.orbit-content strong{display:block;color:var(--dash-fg);font-size:46px;line-height:.9;letter-spacing:-.06em}.orbit-content span{display:block;margin-top:9px;color:var(--dash-muted);font-size:10px;font-weight:850;letter-spacing:.12em;text-transform:uppercase}
.orbit-note{position:absolute;right:18px;bottom:17px;left:18px;color:var(--dash-muted);font-size:11px;text-align:center}
.section-heading{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:18px}.section-heading .actions-title{margin:0}.section-note{color:var(--dash-muted);font-size:12px}
.kpis-area{padding-top:18px}.kpi-card{isolation:isolate;min-height:135px}
.kpi-card::before{content:"";position:absolute;z-index:-1;width:95px;height:95px;right:-35px;bottom:-40px;border-radius:50%;background:var(--kpi-glow,rgba(59,130,246,.14));filter:blur(5px);transition:transform .35s ease}.kpi-card:hover::before{transform:scale(1.28)}
.kpi-card:nth-child(1){--kpi-glow:rgba(14,165,233,.2)}.kpi-resuelto{--kpi-glow:rgba(16,185,129,.22)}.kpi-pendiente{--kpi-glow:rgba(244,63,94,.18)}.kpi-dil{--kpi-glow:rgba(139,92,246,.22)}
.actions-area{border-top:0;padding-top:24px}.tiles-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.tile{position:relative;min-height:82px;padding-right:48px;overflow:hidden}
.tile::after{content:"\2192";position:absolute;right:18px;top:50%;display:grid;place-items:center;width:28px;height:28px;border:1px solid var(--dash-border);border-radius:10px;background:rgba(255,255,255,.18);color:var(--dash-fg);font-size:15px;transform:translateY(-50%);transition:transform .22s ease,background .22s ease}.tile:hover::after{transform:translate(4px,-50%);background:rgba(255,255,255,.36)}
.footer{display:flex;justify-content:space-between;gap:12px}.footer::after{content:"Sistema operativo";color:#22a06b;font-weight:800}
html[data-theme-resolved="dark"] .welcome-panel,html[data-theme-resolved="dark"] .case-orbit{background:linear-gradient(135deg,rgba(30,41,59,.7),rgba(15,23,42,.38));box-shadow:inset 0 1px rgba(255,255,255,.1),0 18px 45px rgba(0,0,0,.18)}
html[data-theme-resolved="dark"] .welcome-kicker,html[data-theme-resolved="dark"] .secondary-action{background:rgba(15,23,42,.3)}
@keyframes orbitBreathe{0%,100%{transform:scale(.97) rotate(-1deg)}50%{transform:scale(1.03) rotate(2deg)}}
@media(prefers-reduced-motion:reduce){.orbit-ring{animation:none}.tile,.primary-action,.secondary-action{transition:none}}
@media(max-width:1100px){.command-intro{grid-template-columns:1fr}.case-orbit{min-height:190px}.tiles-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){.command-intro{padding:22px 16px 8px}.welcome-panel{padding:26px 22px}.welcome-actions{display:grid}.primary-action,.secondary-action{justify-content:center}.section-heading{align-items:start;flex-direction:column;gap:5px}.kpis-row,.tiles-grid{grid-template-columns:1fr}.footer::after{display:none}}
</style>
<link rel="stylesheet" href="assets/dashboard-modern.css?v=20260828-1">
</head>
<body class="modern-dashboard-body">
<div class="dashboard-shell">
  <main class="dashboard-main">
    <header class="modern-topbar">
      <div>
        <span class="today-label" id="dashboardDate">Panel general</span>
        <h1>Buenos dias, <?= htmlspecialchars($yo['nombre'] ?? $yo['email'], ENT_QUOTES, 'UTF-8') ?></h1>
      </div>
      <div class="topbar-actions">
        <a class="topbar-search" href="accidente_listar.php" aria-label="Buscar accidentes"><span>⌕</span> Buscar expediente</a>
        <a class="profile-chip" href="logout.php" title="Cerrar sesion"><span class="profile-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($yo['nombre'] ?? $yo['email'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></span><span class="profile-copy"><strong><?= htmlspecialchars($yo['nombre'] ?? $yo['email'], ENT_QUOTES, 'UTF-8') ?></strong><small>Cerrar sesion</small></span></a>
      </div>
    </header>

    <section class="dashboard-content">
      <section class="metrics-section" aria-labelledby="metrics-title">
        <div class="modern-section-heading"><div><span>Vista general</span><h2 id="metrics-title">Estado de expedientes</h2></div><a href="accidente_listar.php">Ver detalle →</a></div>
        <div class="modern-metrics-grid">
          <article class="modern-metric metric-total"><div class="metric-top"><span class="metric-icon">◫</span><small>Total</small><b>100%</b></div><strong><?= number_format($tot) ?></strong><p>Accidentes registrados</p><div class="metric-line"><i style="width:100%"></i></div></article>
          <article class="modern-metric metric-resolved"><div class="metric-top"><span class="metric-icon">✓</span><small>Resueltos</small><b><?= $pct_res ?>%</b></div><strong><?= number_format($res) ?></strong><p>Casos finalizados</p><div class="metric-line"><i style="width:<?= $pct_res ?>%"></i></div></article>
          <article class="modern-metric metric-pending"><div class="metric-top"><span class="metric-icon">◷</span><small>Pendientes</small><b><?= $pct_pen ?>%</b></div><strong><?= number_format($pen) ?></strong><p>Requieren seguimiento</p><div class="metric-line"><i style="width:<?= $pct_pen ?>%"></i></div></article>
          <article class="modern-metric metric-process"><div class="metric-top"><span class="metric-icon">↗</span><small>Diligencias</small><b><?= $pct_dil ?>%</b></div><strong><?= number_format($dil) ?></strong><p>En proceso operativo</p><div class="metric-line"><i style="width:<?= $pct_dil ?>%"></i></div></article>
        </div>
      </section>

      <section class="quick-section" aria-labelledby="quick-title">
        <div class="modern-section-heading"><div><span>Herramientas</span><h2 id="quick-title">Accesos rapidos</h2></div><small>Selecciona un modulo para comenzar</small></div>
        <div class="modern-quick-grid">
          <a class="quick-card quick-featured" href="accidente_listar.php"><span class="quick-icon">◫</span><div><strong>Lista de accidentes</strong><small>Consulta y gestiona todos los casos</small></div><span class="quick-arrow">→</span></a>
          <a class="quick-card" href="persona_listar.php"><span class="quick-icon mint">♙</span><div><strong>Personas</strong><small>DNI, CE y pasaportes</small></div><span class="quick-arrow">→</span></a>
          <a class="quick-card" href="vehiculo_listar.php"><span class="quick-icon cyan">▰</span><div><strong>Vehiculos</strong><small>Registro vehicular</small></div><span class="quick-arrow">→</span></a>
          <a class="quick-card" href="oficios_listar.php"><span class="quick-icon violet">◇</span><div><strong>Oficios</strong><small>Registro y consulta</small></div><span class="quick-arrow">→</span></a>
          <a class="quick-card" href="comisarias_listar.php"><span class="quick-icon rose">▥</span><div><strong>Comisarias</strong><small>Administracion</small></div><span class="quick-arrow">→</span></a>
          <a class="quick-card" href="oficio_entidades_listar.php"><span class="quick-icon amber">▤</span><div><strong>Entidades</strong><small>Directorio y contactos</small></div><span class="quick-arrow">→</span></a>
          <a class="quick-card" href="enlaces_interes_listar.php"><span class="quick-icon blue">↗</span><div><strong>Enlaces de interes</strong><small>Accesos y consultas</small></div><span class="quick-arrow">→</span></a>
        </div>
      </section>
    </section>
  </main>
</div>

<div class="wrap legacy-dashboard" hidden>
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

      <section class="command-intro" aria-labelledby="welcome-title">
        <div class="welcome-panel">
          <div class="welcome-kicker">Centro de operaciones</div>
          <h1 class="welcome-title" id="welcome-title">Todo bajo control, <?= htmlspecialchars($yo['nombre'] ?? $yo['email'], ENT_QUOTES, 'UTF-8') ?>.</h1>
          <p class="welcome-copy">Gestiona accidentes, personas, vehiculos y documentos desde un solo espacio claro y agil.</p>
          <div class="welcome-actions">
            <a class="primary-action" href="accidente_listar.php"><span aria-hidden="true">+</span> Gestionar accidentes</a>
            <a class="secondary-action" href="oficios_listar.php">Consultar oficios <span aria-hidden="true">&#8594;</span></a>
          </div>
        </div>
        <div class="case-orbit" aria-label="<?= number_format($tot) ?> accidentes registrados">
          <div class="orbit-ring" aria-hidden="true"><div class="orbit-content"><strong><?= number_format($tot) ?></strong><span>casos totales</span></div></div>
          <div class="orbit-note">Resumen actualizado de la base de datos</div>
        </div>
      </section>

      <!-- KPIs -->
      <div class="kpis-area">
        <div class="section-heading"><div class="actions-title">Estado de los casos</div><div class="section-note">Distribucion general de accidentes registrados</div></div>
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
        <div class="section-heading"><div class="actions-title">Acciones rápidas</div><div class="section-note">Accesos frecuentes del sistema</div></div>
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

          <a class="tile tile-red" href="oficio_entidades_listar.php" role="listitem" aria-label="Prontuario de entidades">
            <div class="icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs><linearGradient id="g_red3" x1="0" x2="1"><stop offset="0" stop-color="#f8b7b7"/><stop offset="1" stop-color="#a12424"/></linearGradient></defs>
                <path d="M6 4h9l3 3v13H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" fill="url(#g_red3)" class="duo-fill"/>
                <path d="M9 8h3M9 12h6M9 16h6" stroke="#3d1313" stroke-width="1.1" class="duo-accent"/>
              </svg>
            </div>
            <div class="txt"><div class="h">Prontuario Entidades</div><div class="p">Directorio y contactos</div></div>
          </a>

          <a class="tile tile-red" href="enlaces_interes_listar.php" role="listitem" aria-label="Enlaces de interes">
            <div class="icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs><linearGradient id="g_red4" x1="0" x2="1"><stop offset="0" stop-color="#f8b7b7"/><stop offset="1" stop-color="#a12424"/></linearGradient></defs>
                <path d="M9.5 8.5l-2 2a3 3 0 1 0 4.243 4.243l1.414-1.414" stroke="url(#g_red4)" stroke-width="2" stroke-linecap="round"/>
                <path d="M14.5 15.5l2-2A3 3 0 1 0 12.257 9.257l-1.414 1.414" stroke="#3d1313" stroke-width="2" stroke-linecap="round" class="duo-accent"/>
              </svg>
            </div>
            <div class="txt"><div class="h">Enlaces de Interes</div><div class="p">Accesos y consultas</div></div>
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
