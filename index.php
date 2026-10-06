<?php
require_once __DIR__ . '/auth.php';
require_login();

use App\Database\Database;
use App\Repositories\DashboardRepository;

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$yo = $_SESSION['user'];
$archiveDashboard = in_array(\App\Support\Access::role(), ['secretaria', 'administracion'], true);
$now = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
$yearInput = is_string($_GET['anio'] ?? null) ? $_GET['anio'] : '';
$year = preg_match('/^[1-9][0-9]{3}$/D', $yearInput) ? (int)$yearInput : null;
$years = [];
$data = null;
$archiveData = null;
$error = false;
try {
    $repository = new DashboardRepository(Database::connection());
    $years = $repository->years($archiveDashboard);
    if ($year !== null && !in_array($year, $years, true)) $year = null;
    $data = $repository->snapshot($year, $archiveDashboard);
    if ($archiveDashboard) $archiveData = $repository->archiveSnapshot($year);
} catch (Throwable $e) {
    error_log('Dashboard read failed: ' . $e->getMessage());
    $error = true;
}
function dh($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function dn($value): string { return number_format((float)$value, 0, ',', '.'); }
function dp(int $value, int $total): string { return $total > 0 ? number_format($value * 100 / $total, 1, ',', '.') . '%' : '—'; }
function ddate(?string $value): string {
    if (!$value || substr($value, 0, 4) === '0000') return 'Sin fecha';
    try { return (new DateTimeImmutable($value))->format('d/m/Y'); } catch (Throwable $e) { return 'Sin fecha'; }
}
function ddatetime(?string $value): string {
    if (!$value || substr($value, 0, 4) === '0000') return 'Sin fecha';
    try { return (new DateTimeImmutable($value))->format('d/m/Y H:i'); } catch (Throwable $e) { return 'Sin fecha'; }
}
function dlink(array $extra = []): string {
    global $year;
    return 'accidente_listar.php?' . http_build_query(array_merge([
        'ver_todos' => '1', 'estado' => 'todos', 'anio' => $year ?? '',
        'q' => '', 'desde' => '', 'hasta' => '', 'distrito' => '', 'comisaria_id' => '',
        'persona' => '', 'vehiculo' => '', 'registro_sidpol' => '', 'nro_informe_policial' => '', 'tipo_registro' => '', 'favoritos' => '0',
    ], $extra));
}
function di(string $name, string $class = ''): string {
    return '<svg class="icon ' . dh($class) . '" aria-hidden="true"><use href="#i-' . dh($name) . '"/></svg>';
}
$months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$name = trim((string)($yo['nombre'] ?? $yo['email'] ?? 'Usuario')) ?: 'Usuario';
$initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($name, 0, 1)) : strtoupper(substr($name, 0, 1));
$greeting = (int)$now->format('H') < 12 ? 'Buenos días' : ((int)$now->format('H') < 19 ? 'Buenas tardes' : 'Buenas noches');
$stateColors = ['Pendiente' => '#d8a044', 'Resuelto' => '#287c62', 'Con diligencias' => '#6c83ac', 'Desestimado' => '#a7afb0'];
$total = $data['total'] ?? 0;
$counts = $data['counts'] ?? [];
$segments = []; $position = 0;
foreach ($counts as $state => $count) {
    if (!$count || !$total) continue;
    $end = $position + $count / $total * 100;
    $segments[] = ($stateColors[$state] ?? '#8c819f') . " $position% $end%";
    $position = $end;
}
$donut = $segments ? 'conic-gradient(' . implode(', ', $segments) . ')' : '#edf0ec';
$timeline = [];
if ($data) {
    $map = array_column($data['timeline'], 'total', 'period');
    if ($year !== null) {
        for ($m = 1; $m <= 12; $m++) $timeline[] = ['label' => $months[$m-1], 'value' => (int)($map[$m] ?? 0), 'year' => $year, 'month' => $m];
    } elseif ($map) {
        for ($y = min(array_keys($map)); $y <= max(array_keys($map)); $y++) $timeline[] = ['label' => (string)$y, 'value' => (int)($map[$y] ?? 0), 'year' => (int)$y, 'month' => null];
    }
}
$maxValue = $timeline ? max(array_column($timeline, 'value')) : 0;
$tick = max(1, (int)ceil($maxValue / 4));
$axisMax = $tick * 4;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#163c31">
<title>Panel de gestión · DIVPIAT Lima Norte</title>
<link rel="icon" href="favicon.ico">
<link rel="stylesheet" href="assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>">

</head>
<body class="uiat-dashboard">
<a class="skip-link" href="#main">Ir al contenido</a>
<svg class="icon-defs" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
<symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></symbol>
<symbol id="i-folder" viewBox="0 0 24 24"><path d="M3 7V5a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M3 10h18"/></symbol>
<symbol id="i-map" viewBox="0 0 24 24"><path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2Zm6-2v16m6-14v16"/></symbol>
<symbol id="i-people" viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M17 4a4 4 0 0 1 0 8m2 3a5 5 0 0 1 3 5"/></symbol>
<symbol id="i-car" viewBox="0 0 24 24"><path d="m4 9 2-6h12l2 6M3 16v4m18-4v4"/><rect x="2" y="9" width="20" height="8" rx="2"/><path d="M6 13h2m8 0h2"/></symbol>
<symbol id="i-file" viewBox="0 0 24 24"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 13h8M8 17h5"/></symbol>
<symbol id="i-building" viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 21v-5h6v5M8 7h1m6 0h1m-8 4h1m6 0h1"/></symbol>
<symbol id="i-book" viewBox="0 0 24 24"><path d="M4 19a2 2 0 0 1 2-2h14V3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14M8 7h8m-8 4h5"/></symbol>
<symbol id="i-link" viewBox="0 0 24 24"><path d="m9 15 6-6m-7 3-3 3a3 3 0 0 0 4 4l3-3m0-8 3-3a3 3 0 0 1 4 4l-3 3"/></symbol>
<symbol id="i-search" viewBox="0 0 24 24"><circle cx="10" cy="10" r="7"/><path d="m15 15 6 6"/></symbol>
<symbol id="i-arrow" viewBox="0 0 24 24"><path d="M4 12h16m-6-6 6 6-6 6"/></symbol>
<symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
<symbol id="i-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m7 12 3 3 7-7"/></symbol>
<symbol id="i-activity" viewBox="0 0 24 24"><path d="M2 12h5l3-8 4 16 3-8h5"/></symbol>
<symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
<symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18"/></symbol>
<symbol id="i-logout" viewBox="0 0 24 24"><path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4m6-14 5 5-5 5m-7-5h12"/></symbol>
<symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
<symbol id="i-shield" viewBox="0 0 24 24"><path d="m12 3-8 3v6c0 5 8 9 8 9s8-4 8-9V6Z"/><path d="m8 12 3 3 5-6"/></symbol>
</svg>
<div class="app-layout">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="page">
    <header class="topbar">
        <div class="breadcrumb">Gestión de la información <span>/</span> <b>Panel general</b></div>
        <div class="topbar-right"><a class="search-link" href="<?= dh(dlink()) ?>"><?= di('search') ?><span>Buscar expediente</span></a><details class="uiat-user-menu"><summary class="profile" aria-label="Cuenta: <?= dh($name) ?>"><span class="avatar"><?= dh($initial) ?></span><div><strong><?= dh($name) ?></strong><small><?= dh(\App\Support\Access::ROLES[(string)($yo['rol'] ?? '')] ?? 'Personal autorizado') ?></small></div></summary><div class="uiat-user-menu-panel"><a href="logout.php">Cerrar sesión</a></div></details></div>
    </header>
    <main id="main">
        <div class="page-heading"><div><div class="greeting"><?= dh($greeting) ?>, <?= dh($name) ?></div><h1><?= $archiveDashboard ? 'Panorama de expedientes' : 'Panorama de investigaciones' ?><span>.</span></h1><p><?= $archiveDashboard ? 'Resumen institucional de casos y archivo.' : (\App\Support\Access::admin() ? 'Todos los expedientes de Lima Norte.' : 'Expedientes asignados a tu espacio de trabajo.') ?> <a href="gestion_expedientes.php">Consulta general</a></p></div><?php if ($archiveDashboard): ?><a class="button primary" href="expedientes_recepcion.php"><?= di('folder') ?> Recepciones para archivo</a><?php else: ?><a class="button primary" href="accidente_nuevo.php"><?= di('plus') ?> Registrar accidente</a><?php endif; ?></div>
        <div class="scope-row"><div class="scope-label"><span class="live-dot <?= $error ? 'offline' : '' ?>"></span><?= $error ? 'Datos no disponibles' : 'Consultado a las ' . $now->format('H:i') ?> <span class="scope-date">· <?= $now->format('d/m/Y') ?></span></div><form class="period-form" method="get"><label for="year"><?= di('calendar') ?> Año del accidente</label><select id="year" name="anio"><option value="">Todo el historial</option><?php foreach ($years as $option): ?><option value="<?= $option ?>" <?= $year === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select><button type="submit" class="apply-button">Aplicar</button></form></div>
        <?php if ($error): ?>
            <section class="error-panel" role="alert"><?= di('activity') ?><h2>No pudimos cargar el resumen</h2><p>La información no está disponible en este momento. Intenta actualizar la página.</p><a class="button primary" href="index.php">Volver a intentar</a></section>
        <?php else: ?>
        <?php if ($archiveDashboard): ?>
        <?php $archiveCounts = $archiveData['counts']; ?>
        <section class="kpi-grid archive-kpi-grid" aria-label="Indicadores institucionales de expedientes">
          <?php foreach ([
            ['total','folder','Casos vigentes','vigentes','Sin recepción de archivo aceptada'],
            ['resolved','check','Casos resueltos','resueltos','Estado actual: resuelto'],
            ['pending','clock','Casos pendientes','pendientes','Pendiente o con diligencias'],
            ['process','book','Casos archivados','archivados','Recepción de archivo aceptada'],
            ['pending','file','Por aceptar para archivo','por_aceptar',($archiveCounts['por_aceptar_mios'] ?? 0) . ' asignados a tu cuenta'],
          ] as [$kind,$icon,$label,$key,$description]): ?>
          <?php if ($key === 'por_aceptar'): ?><a class="kpi <?= $kind ?>" href="expedientes_recepcion.php"><?php else: ?><div class="kpi <?= $kind ?>"><?php endif; ?>
            <div class="kpi-top"><span><?= dh($label) ?></span><span class="kpi-icon"><?= di($icon) ?></span></div>
            <div class="kpi-value"><?= dn($archiveCounts[$key] ?? 0) ?></div>
            <div class="kpi-bottom"><span><?= dh($description) ?></span><?php if ($key === 'por_aceptar') echo di('arrow'); ?></div>
          <?= $key === 'por_aceptar' ? '</a>' : '</div>' ?>
          <?php endforeach; ?>
        </section>
        <div class="archive-grid">
          <section class="panel district-panel" aria-labelledby="archive-district-title"><div class="panel-heading"><div><h2 id="archive-district-title">Accidentes por distrito</h2><p>Total institucional según el año del accidente</p></div><?= di('map', 'muted') ?></div>
            <?php if ($data['districts']): ?><div class="district-list"><?php foreach ($data['districts'] as $district): ?><div class="district-row"><div><span><?= dh($district['label']) ?></span><strong><?= dn($district['total']) ?> <small><?= dp((int)$district['total'], $total) ?></small></strong></div><div class="district-track"><i style="width:<?= $total ? (int)$district['total'] * 100 / $total : 0 ?>%"></i></div></div><?php endforeach; ?></div><?php else: ?><div class="empty-state"><p>Sin accidentes para mostrar.</p></div><?php endif; ?>
          </section>
          <section class="panel archive-history-panel" aria-labelledby="archive-history-title"><div class="panel-heading"><div><h2 id="archive-history-title">Historial de casos</h2><p>Registros, resoluciones y archivos por mes · <?= $year === null ? 'Todo el historial' : 'Accidentes de ' . $year ?></p></div><span class="small-tag">Por fecha del evento</span></div>
            <?php if ($archiveData['history'] || $archiveData['undated_resolved']): ?><div class="table-scroll"><table><thead><tr><th>Mes</th><th>Registrados</th><th>Resueltos</th><th>Archivados</th></tr></thead><tbody><?php foreach (array_reverse($archiveData['history'], true) as $period => $values): ?><tr><td><strong><?= dh($period) ?></strong></td><td><?= dn($values['Registrado']) ?></td><td><?= dn($values['Resuelto']) ?></td><td><?= dn($values['Archivado']) ?></td></tr><?php endforeach; ?><?php if ($archiveData['undated_resolved']): ?><tr><td><strong>Sin fecha registrada</strong></td><td>—</td><td><?= dn($archiveData['undated_resolved']) ?></td><td>—</td></tr><?php endif; ?></tbody></table></div><?php else: ?><div class="empty-state"><p>Sin eventos para mostrar.</p></div><?php endif; ?>
            <p class="archive-history-note">Las resoluciones históricas se muestran cuando existe un cambio de estado con fecha registrada.</p>
          </section>
        </div>
        <section class="panel archive-events-panel" aria-labelledby="archive-events-title"><div class="panel-heading"><div><h2 id="archive-events-title">Actividad reciente</h2><p>Nuevos casos registrados, resueltos y archivados</p></div></div>
          <?php if ($archiveData['events']): ?><div class="table-scroll"><table><thead><tr><th>Fecha</th><th>Movimiento</th><th>Expediente</th><th>Distrito</th><th></th></tr></thead><tbody><?php foreach ($archiveData['events'] as $event): ?><tr><td><?= ddatetime($event['at']) ?></td><td><span class="status <?= $event['kind'] === 'Archivado' ? 'process' : ($event['kind'] === 'Resuelto' ? 'resolved' : 'pending') ?>"><i></i><?= dh($event['kind']) ?></span></td><td><?= dh($event['sidpol'] ?: 'Expediente #' . $event['id']) ?></td><td><?= dh($event['district']) ?></td><td><a class="row-open" href="gestion_expedientes.php?id=<?= (int)$event['id'] ?>" aria-label="Consultar expediente <?= (int)$event['id'] ?>"><?= di('arrow') ?></a></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state"><p>Sin actividad reciente.</p></div><?php endif; ?>
        </section>
        <?php else: ?>
        <section class="kpi-grid <?= \App\Support\Access::role() === 'jefe_emi' ? 'emi-kpi-grid' : '' ?>" aria-label="Indicadores del período seleccionado">
        <?php $cards = [
            ['total','folder','Accidentes registrados',$total,'Expedientes en el período','todos'],
            ['pending','clock','Pendientes',$counts['Pendiente'],'Requieren seguimiento','Pendiente'],
            ['resolved','check','Resueltos',$counts['Resuelto'],'Estado actual del expediente','Resuelto'],
            ['process','activity','Con diligencias',$counts['Con diligencias'],'Expedientes en este estado','Con diligencias'],
        ]; foreach ($cards as [$kind,$icon,$label,$value,$description,$state]): ?>
            <a class="kpi <?= $kind ?>" href="<?= dh(dlink(['estado' => $state])) ?>"><div class="kpi-top"><span><?= dh($label) ?></span><span class="kpi-icon"><?= di($icon) ?></span></div><div class="kpi-value"><?= dn($value) ?><span class="kpi-ratio"><?= $kind === 'total' ? ($year ?? 'Histórico') : dp($value, $total) ?></span></div><div class="kpi-bottom"><span><?= dh($description) ?></span><?= di('arrow') ?></div></a>
        <?php endforeach; ?>
        <?php if (\App\Support\Access::role() === 'jefe_emi'): ?>
            <a class="kpi process" href="<?= dh(dlink(['estado' => 'Archivados'])) ?>"><div class="kpi-top"><span>Casos archivados</span><span class="kpi-icon"><?= di('book') ?></span></div><div class="kpi-value"><?= dn($data['archive']['accepted']) ?><span class="kpi-ratio"><?= dp($data['archive']['accepted'], $total) ?></span></div><div class="kpi-bottom"><span>Recepción de archivo aceptada</span><?= di('arrow') ?></div><div class="kpi-archive-pending"><strong><?= dn($data['archive']['pending']) ?></strong><span>Pendientes de aceptación en Archivo</span></div></a>
        <?php endif; ?>
        </section>
        <div class="charts-grid">
            <section class="panel trend-panel" aria-labelledby="trend-title"><div class="panel-heading"><div><h2 id="trend-title">Evolución de accidentes</h2><p>Según fecha del accidente · <?= $year === null ? 'Por año' : 'Por mes de ' . $year ?></p></div><span class="small-tag"><?= $year ?? 'Histórico' ?></span></div>
                <?php if ($total && $timeline): ?>
                <div class="trend-summary"><strong><?= dn($total - $data['undated']) ?></strong><span>accidentes con fecha válida</span><span class="chart-key"><i></i> Accidentes</span></div>
                <div class="chart-scroll"><svg class="bar-chart" viewBox="0 0 700 220" role="group" aria-labelledby="trend-title trend-desc"><desc id="trend-desc"><?= dh(implode('; ', array_map(fn($p) => $p['label'] . ': ' . $p['value'] . ' accidentes', $timeline))) ?>. Las barras permiten consultar los expedientes.</desc>
                <defs><linearGradient id="bar-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3f9273"/><stop offset="1" stop-color="#245f4b"/></linearGradient></defs>
                <?php for ($i = 0; $i <= 4; $i++): $y = 174 - $i * 38; ?><line x1="38" y1="<?= $y ?>" x2="687" y2="<?= $y ?>" class="grid-line"/><text x="26" y="<?= $y + 4 ?>" class="axis-label" text-anchor="end"><?= $i * $tick ?></text><?php endfor; ?>
                <?php $step = 642 / count($timeline); foreach ($timeline as $i => $point):
                    $x = 42 + $i * $step; $width = min(64, $step * .55); $center = $x + $step / 2; $height = $point['value'] / $axisMax * 152;
                    $future = $point['year'] > (int)$now->format('Y') || ($point['year'] === (int)$now->format('Y') && $point['month'] !== null && $point['month'] > (int)$now->format('n'));
                    $query = ['anio' => $point['year']];
                    if ($point['month'] !== null) {
                        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $point['year'], $point['month']));
                        $query['desde'] = $start->format('Y-m-d 00:00:00'); $query['hasta'] = $start->modify('last day of this month')->format('Y-m-d 23:59:59');
                    }
                ?>
                    <a href="<?= dh(dlink($query)) ?>" aria-label="<?= dh($point['label'] . ': ' . $point['value'] . ' accidentes. Ver expedientes') ?>" class="chart-bar"><title><?= dh($point['label']) ?>: <?= $point['value'] ?> accidentes<?= $future ? ' (fecha futura)' : '' ?></title><rect x="<?= $x ?>" y="16" width="<?= $step ?>" height="175" fill="transparent"/><rect x="<?= $center - $width/2 ?>" y="<?= 174 - $height ?>" width="<?= $width ?>" height="<?= $height ?>" rx="5" fill="url(#bar-fill)"/><text x="<?= $center ?>" y="<?= 163 - $height ?>" text-anchor="middle" class="bar-value"><?= $future && !$point['value'] ? '—' : $point['value'] ?></text><text x="<?= $center ?>" y="202" class="axis-label" text-anchor="middle"><?= dh($point['label']) ?></text></a>
                <?php endforeach; ?></svg></div>
                <div class="chart-foot"><span><?= $year === (int)$now->format('Y') ? 'Año en curso · — indica meses futuros sin registros.' : 'Selecciona una barra para ver los expedientes.' ?><?= $year === null && in_array((int)$now->format('Y'), $years, true) ? ' ' . $now->format('Y') . ' es un año en curso.' : '' ?><?= $data['undated'] ? ' ' . dn($data['undated']) . ' sin fecha válida, excluidos de la gráfica.' : '' ?></span><details class="chart-data"><summary>Ver cifras</summary><div><?php foreach ($timeline as $point): ?><span><?= dh($point['label']) ?> <b><?= $point['value'] ?></b></span><?php endforeach; ?></div></details></div>
                <?php else: ?><div class="empty-state"><?= di('activity') ?><p>No hay accidentes con fecha válida en este período.</p></div><?php endif; ?>
            </section>
            <section class="panel state-panel" aria-labelledby="state-title"><div class="panel-heading"><div><h2 id="state-title">Estado de expedientes</h2><p>Distribución del período seleccionado</p></div><?= di('grid', 'muted') ?></div>
                <div class="donut" style="--donut:<?= dh($donut) ?>" role="img" aria-label="<?= $total ? 'Resueltos: ' . dp($counts['Resuelto'], $total) . '. Distribución detallada a continuación.' : 'Sin expedientes en el período' ?>"><div><strong><?= $total ? dp($counts['Resuelto'], $total) : '—' ?></strong><span>resueltos del total</span></div></div>
                <div class="legend"><?php foreach ($counts as $state => $count): if (!$count && !in_array($state, ['Pendiente','Resuelto','Con diligencias'], true)) continue; ?><div class="legend-row"><span><i style="background:<?= dh($stateColors[$state] ?? '#8c819f') ?>"></i><?= dh($state) ?></span><strong><?= dn($count) ?></strong><small><?= dp($count, $total) ?></small></div><?php endforeach; ?></div>
                <div class="state-note">Estado actual de los casos; no representa cierres realizados durante el período.</div>
            </section>
        </div>
        <div class="detail-grid">
            <section class="panel district-panel" aria-labelledby="district-title"><div class="panel-heading"><div><h2 id="district-title">Accidentes por distrito</h2><p>Distribución territorial de los expedientes</p></div><a class="icon-button" href="accidente_mapa.php" aria-label="Abrir mapa de accidentes"><?= di('map') ?></a></div>
                <?php if ($data['districts']): ?><div class="district-list"><?php foreach ($data['districts'] as $district): ?><a class="district-row" <?= $district['label'] === 'Sin distrito' ? 'role="group"' : 'href="' . dh(dlink(['distrito' => $district['label']])) . '"' ?>><div><span><?= dh($district['label']) ?></span><strong><?= dn($district['total']) ?> <small><?= dp((int)$district['total'], $total) ?></small></strong></div><div class="district-track"><i style="width:<?= $total ? (int)$district['total'] * 100 / $total : 0 ?>%"></i></div></a><?php endforeach; ?></div><?php else: ?><div class="empty-state"><p>Sin accidentes para mostrar.</p></div><?php endif; ?>
            </section>
            <section class="panel recent-panel" aria-labelledby="recent-title"><div class="panel-heading"><div><h2 id="recent-title">Últimos registros</h2><p>Hasta 5 expedientes incorporados recientemente</p></div><a class="text-link" href="<?= dh(dlink()) ?>">Ver todos <?= di('arrow') ?></a></div>
                <?php if ($data['recent']): ?><div class="table-scroll"><table><thead><tr><th>Expediente / comisaría</th><th>Fecha del accidente</th><th>Estado</th><th><span class="sr-only">Acción</span></th></tr></thead><tbody><?php foreach ($data['recent'] as $row): $statusClass = ['Resuelto'=>'resolved','Pendiente'=>'pending','Con diligencias'=>'process'][$row['estado']] ?? 'other'; ?><tr><td><a class="case-link" href="accidente_vista_tabs.php?accidente_id=<?= (int)$row['id'] ?>"><?= dh($row['registro_sidpol'] ?: 'Expediente #' . $row['id']) ?></a><small><?= dh($row['comisaria']) ?></small></td><td><?= ddate($row['fecha_accidente']) ?></td><td><span class="status <?= $statusClass ?>"><i></i><?= dh($row['estado']) ?></span></td><td><a class="row-open" href="accidente_vista_tabs.php?accidente_id=<?= (int)$row['id'] ?>" aria-label="Abrir expediente <?= (int)$row['id'] ?>"><?= di('arrow') ?></a></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state"><p>Todavía no hay registros en este período.</p><a href="accidente_nuevo.php">Registrar un accidente</a></div><?php endif; ?>
                <div class="priority-note"><?= di('shield') ?><div><strong><?= dn($data['priority']) ?> <?= $data['priority'] === 1 ? 'expediente prioritario' : 'expedientes prioritarios' ?> en seguimiento</strong><span>Marcados como prioritarios y en estado pendiente o con diligencias.</span></div></div>
            </section>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <section class="quick-section" aria-labelledby="quick-title"><div class="quick-heading"><h2 id="quick-title">Tu trabajo, a un clic</h2><span>Accesos rápidos</span></div><div class="quick-grid"><?php foreach ($archiveDashboard ? [['folder','Consultar expedientes','Búsqueda institucional','gestion_expedientes.php'], ['book','Recepción para archivo','Pendientes de aceptar','expedientes_recepcion.php'], ['file','Oficios','Documentación y comunicaciones','oficios_listar.php'], ['map','Mapa de accidentes','Distribución territorial','accidente_mapa.php']] : [['folder','Explorar expedientes','Consulta y seguimiento',dlink()], ...(\App\Support\Access::admin() ? [['people','Personas','Registro de involucrados','persona_listar.php'], ['car','Vehículos','Consulta del registro vehicular','vehiculo_listar.php']] : [['book','Recepciones pendientes','Expedientes por aceptar','expedientes_recepcion.php'], ['map','Mapa de accidentes','Distribución territorial','accidente_mapa.php']]), ['file','Oficios','Documentación y comunicaciones','oficios_listar.php']] as [$icon,$label,$description,$url]): ?><a class="quick-card" href="<?= dh($url) ?>"><span class="quick-icon"><?= di($icon) ?></span><div><strong><?= dh($label) ?></strong><small><?= dh($description) ?></small></div><?= di('arrow') ?></a><?php endforeach; ?></div></section>
        <footer class="page-footer"><span>DIVPIAT <b>/</b> Investigación de Accidentes de Tránsito · Lima Norte</span><details><summary>Acerca de los indicadores</summary><p><?= $archiveDashboard ? 'Fuente: expedientes institucionales activos. Cada caso cuenta una vez. Vigentes excluye los recibidos en Archivo; pendientes incluye los que tienen diligencias. Resueltos muestra el estado actual y puede incluir casos archivados. El filtro utiliza la fecha del accidente. El historial usa la fecha registrada de cada evento; las resoluciones antiguas sin fecha de cambio figuran aparte.' : 'Fuente: expedientes de tu espacio de trabajo; el administrador ve el total institucional. Cada accidente cuenta una vez. El filtro usa la fecha del accidente; los estados corresponden a su situación actual. Los porcentajes se calculan sobre el total filtrado. Los registros recientes se ordenan por fecha de creación. Los datos se consultan al cargar o actualizar esta página.' ?></p></details></footer>
    </main>
</div>
</div>
</body>
</html>
