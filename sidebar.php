<?php
/** Shared institutional navigation. Embedded forms and print views keep their own layout. */
if (defined('UIAT_SIDEBAR_RENDERED')) return;
if (!empty($embed) || !empty($isEmbed) || (isset($_GET['embed']) && is_scalar($_GET['embed']) && in_array((string)$_GET['embed'], ['1', 'true'], true))) return;
define('UIAT_SIDEBAR_RENDERED', true);

$sidebarPath = basename((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH));
$sidebarAccidentId = (int)($accidente_id ?? $accidenteId ?? $_GET['accidente_id'] ?? 0);
$sidebarCalendarParams = ['return_to' => (string)($_SERVER['REQUEST_URI'] ?? 'index.php')];
if ($sidebarAccidentId > 0) $sidebarCalendarParams['accidente_id'] = $sidebarAccidentId;
$sidebarEscape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$sidebarIcons = [
    'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'folder' => '<path d="M3 7V5a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M3 10h18"/>',
    'map' => '<path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2Zm6-2v16m6-14v16"/>',
    'people' => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M17 4a4 4 0 0 1 0 8m2 3a5 5 0 0 1 3 5"/>',
    'car' => '<path d="m4 9 2-6h12l2 6M3 16v4m18-4v4"/><rect x="2" y="9" width="20" height="8" rx="2"/><path d="M6 13h2m8 0h2"/>',
    'file' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 13h8M8 17h5"/>',
    'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18M7 15h2m6 0h2"/>',
    'building' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 21v-5h6v5M8 7h1m6 0h1m-8 4h1m6 0h1"/>',
    'book' => '<path d="M4 19a2 2 0 0 1 2-2h14V3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14M8 7h8m-8 4h5"/>',
    'link' => '<path d="m9 15 6-6m-7 3-3 3a3 3 0 0 0 4 4l3-3m0-8 3-3a3 3 0 0 1 4 4l-3 3"/>',
    'shield' => '<path d="m12 3-8 3v6c0 5 8 9 8 9s8-4 8-9V6Z"/><path d="m8 12 3 3 5-6"/>',
    'logout' => '<path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4m6-14 5 5-5 5m-7-5h12"/>',
];
$sidebarSvg = static fn($icon) => '<svg viewBox="0 0 24 24" aria-hidden="true">' . $sidebarIcons[$icon] . '</svg>';
$sidebarGroups = [
    'ESPACIO DE TRABAJO' => [
        ['grid', 'Resumen general', 'index.php', $sidebarPath === 'index.php'],
        ['folder', 'Accidentes', 'accidente_listar.php', in_array($sidebarPath, ['accidente_listar.php','accidente_nuevo.php','accidente_editar.php','accidente_creado.php'], true)],
        ['map', 'Mapa de accidentes', 'accidente_mapa.php', $sidebarPath === 'accidente_mapa.php'],
        ['people', 'Personas', 'persona_listar.php', str_starts_with($sidebarPath, 'persona_')],
        ['car', 'Vehículos', 'vehiculo_listar.php', str_starts_with($sidebarPath, 'vehiculo_')],
        ['file', 'Oficios', 'oficios_listar.php', str_starts_with($sidebarPath, 'oficios_')],
        ['calendar', 'Google Calendar', 'citacion_rapida.php?' . http_build_query($sidebarCalendarParams), in_array($sidebarPath, ['citacion_rapida.php','google_calendar.php'], true)],
    ],
];
if ($sidebarAccidentId > 0) {
    $sidebarGroups['EXPEDIENTE ACTUAL'] = [
        ['file', 'Datos generales', 'Dato_General_accidente.php?accidente_id=' . $sidebarAccidentId, in_array($sidebarPath, ['Dato_General_accidente.php','accidente_general_tabs.php','accidente_general_sticky_modulos.php'], true)],
        ['folder', 'Vista del expediente', 'accidente_vista_tabs.php?accidente_id=' . $sidebarAccidentId, $sidebarPath === 'accidente_vista_tabs.php'],
    ];
}
$sidebarGroups['GESTIÓN'] = [
    ['folder', 'Buscador general', 'gestion_expedientes.php', $sidebarPath === 'gestion_expedientes.php'],
    ['folder', 'En espera de recepción', 'expedientes_recepcion.php', $sidebarPath === 'expedientes_recepcion.php'],
    ['file', 'Comunicaciones de guardia', 'guardia.php', $sidebarPath === 'guardia.php'],
];
if (\App\Support\Access::admin()) {
    $sidebarGroups['ADMINISTRACIÓN'] = [
        ['people', 'Usuarios y perfiles', 'usuarios_gestion.php', in_array($sidebarPath, ['usuarios_gestion.php','usuarios_nuevo.php'], true)],
        ['grid', 'Estadísticas de gestión', 'estadisticas.php', $sidebarPath === 'estadisticas.php'],
    ];
}
if ($sidebarAccidentId > 0) {
    $sidebarGroups['EXPEDIENTE ACTUAL'][] = ['people', 'Estado y colaboración', 'accidente_vista_tabs.php?tab=estado&accidente_id=' . $sidebarAccidentId, false];
}
$sidebarGroups['DIRECTORIO'] = [
    ['building', 'Comisarías', 'comisarias_listar.php', str_starts_with($sidebarPath, 'comisarias_')],
    ['book', 'Entidades', 'oficio_entidades_listar.php', str_starts_with($sidebarPath, 'oficio_entidad')],
    ['link', 'Enlaces de interés', 'enlaces_interes_listar.php', str_starts_with($sidebarPath, 'enlace')],
];
if (\App\Support\Access::admin()) {
    $sidebarGroups['DIRECTORIO'][] = ['grid', 'Catálogos', 'catalogos.php', $sidebarPath === 'catalogos.php'];
}
?>
<?php if (empty($uiatSidebarCssPreloaded)): ?>
<link rel="stylesheet" href="assets/css/sidebar-glass.css?v=<?= filemtime(__DIR__ . '/assets/css/sidebar-glass.css') ?>">
<?php endif; ?>
<div id="uiat-navigation">
<button type="button" id="sidebar-toggle" aria-controls="app-sidebar" aria-expanded="false" aria-label="Abrir menú">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path class="sidebar-menu-lines" d="M4 6h16M4 12h16M4 18h16"/><path class="sidebar-menu-close" d="m6 6 12 12M18 6 6 18"/></svg>
</button>
<div id="sidebar-backdrop" hidden></div>
<aside id="app-sidebar" aria-label="Barra lateral institucional">
    <a class="uiat-brand" href="index.php" aria-label="DIVPIAT Lima Norte, inicio"><span class="uiat-emblem"><img src="assets/img/divpiat-logo.jpg" alt="" width="244" height="206"></span><span class="uiat-label"><strong>DIVPIAT</strong><small>LIMA NORTE</small></span></a>
    <p class="uiat-department">Departamento de Investigación<br>de Accidentes de Tránsito</p>
    <nav aria-label="Navegación principal">
        <?php foreach ($sidebarGroups as $caption => $items): ?>
        <div class="uiat-nav-caption"><span><?= $sidebarEscape($caption) ?></span></div>
        <?php foreach ($items as [$icon, $label, $url, $active]): ?>
        <a class="uiat-nav-link<?= $active ? ' active' : '' ?>" href="<?= $sidebarEscape($url) ?>" aria-label="<?= $sidebarEscape($label) ?>" title="<?= $sidebarEscape($label) ?>"<?= $active ? ' aria-current="page"' : '' ?>><span class="uiat-nav-icon"><?= $sidebarSvg($icon) ?></span><span class="uiat-label"><?= $sidebarEscape($label) ?></span><?php if ($active): ?><i class="uiat-active-dot"></i><?php endif; ?></a>
        <?php endforeach; endforeach; ?>
    </nav>
    <div class="uiat-nav-bottom"><div class="uiat-institution"><span class="uiat-nav-icon"><?= $sidebarSvg('shield') ?></span><span class="uiat-label">Gestión institucional<small>Policía Nacional del Perú</small></span></div><a class="uiat-nav-link" href="logout.php" title="Cerrar sesión" aria-label="Cerrar sesión"><span class="uiat-nav-icon"><?= $sidebarSvg('logout') ?></span><span class="uiat-label">Cerrar sesión</span></a></div>
</aside>
</div>
<script src="assets/js/sidebar.js?v=<?= filemtime(__DIR__ . '/assets/js/sidebar.js') ?>" defer></script>
