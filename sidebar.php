<?php
if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}

$accidente_id = $accidente_id ?? ($_GET['accidente_id'] ?? null);
if ($accidente_id === '') $accidente_id = null;

$pdo_conn = $pdo ?? ($db ?? null);

function sc($pdo,$sql,$p){
    if(!$pdo) return 0;
    try{ $st=$pdo->prepare($sql); $st->execute($p); return (int)$st->fetchColumn(); }
    catch(Exception $e){ return 0; }
}

$cntItp  = $cntItp  ?? sc($pdo_conn,"SELECT COUNT(*) FROM itp WHERE accidente_id=?",[$accidente_id]);
$cntEfec = $cntEfec ?? sc($pdo_conn,"SELECT COUNT(*) FROM policial_interviniente WHERE accidente_id=?",[$accidente_id]);
$cntVeh  = $cntVeh  ?? sc($pdo_conn,"SELECT COUNT(*) FROM involucrados_vehiculos WHERE accidente_id=?",[$accidente_id]);
$cntPer  = $cntPer  ?? sc($pdo_conn,"SELECT COUNT(*) FROM involucrados_personas WHERE accidente_id=?",[$accidente_id]);
$cntFam  = $cntFam  ?? sc($pdo_conn,"SELECT COUNT(*) FROM familiar_fallecido WHERE accidente_id=?",[$accidente_id]);
$cntProp = $cntProp ?? sc($pdo_conn,"SELECT COUNT(*) FROM propietario_vehiculo WHERE accidente_id=?",[$accidente_id]);
$cntAbog = $cntAbog ?? sc($pdo_conn,"SELECT COUNT(*) FROM abogado WHERE accidente_id=?",[$accidente_id]);
$cntOfi  = $cntOfi  ?? sc($pdo_conn,"SELECT COUNT(*) FROM oficios WHERE accidente_id=?",[$accidente_id]);
$cntCit  = $cntCit  ?? sc($pdo_conn,"SELECT COUNT(*) FROM citacion WHERE accidente_id=?",[$accidente_id]);
$cntDil  = $cntDil  ?? sc($pdo_conn,"SELECT COUNT(*) FROM diligencias_pendientes WHERE accidente_id=?",[$accidente_id]);

$sidebarCurrentPath = basename((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '')), PHP_URL_PATH));
$sidebarReturnTo = trim((string) ($_SERVER['REQUEST_URI'] ?? ''));
$googleCalendarParams = [];
if ($accidente_id !== null) {
    $googleCalendarParams['accidente_id'] = (int) $accidente_id;
}
if ($sidebarReturnTo !== '') {
    $googleCalendarParams['return_to'] = $sidebarReturnTo;
}
$googleCalendarUrl = 'citacion_rapida.php' . ($googleCalendarParams !== [] ? ('?' . http_build_query($googleCalendarParams)) : '');

function sidebar_active(string ...$routes): string {
    global $sidebarCurrentPath;
    return in_array($sidebarCurrentPath, $routes, true) ? ' active' : '';
}

?>
<link rel="stylesheet" href="assets/css/sidebar-glass.css?v=<?= filemtime(__DIR__ . '/assets/css/sidebar-glass.css') ?>">

<button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-controls="app-sidebar" aria-expanded="false" aria-label="Abrir menu">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path class="sidebar-menu-lines" d="M4 6h16M4 12h16M4 18h16"/><path class="sidebar-menu-close" d="m6 6 12 12M18 6 6 18"/></svg>
</button>
<div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
<div class="sidebar-wrap">
<nav class="sidebar" id="app-sidebar" aria-label="Navegación principal">
    <div class="sidebar-brand"><span>UIAT NORTE</span><small>Espacio de trabajo</small></div>

    <!-- BOTONES SUPERIORES -->
    <div class="top-actions">
<a class="btn<?= sidebar_active('index.php') ?>" href="index.php">
        <span class="section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 10l9-7 9 7M5 9v12h5v-7h4v7h5V9"/></svg></span>
        <span>Ir a panel</span>
    </a>

        <a class="btn<?= sidebar_active('accidente_listar.php') ?>" href="accidente_listar.php">
            <span class="section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 12H4m6-6-6 6 6 6"/></svg></span>
            <span>Volver a lista</span>
        </a>
        <a class="btn<?= sidebar_active('accidente_mapa.php') ?>" href="accidente_mapa.php">
            <span class="section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2zM9 3v16M15 5v16"/></svg></span>
            <span>Mapa de accidentes</span>
        </a>

        <?php if($accidente_id !== null): ?>
        <a class="btn<?= sidebar_active('Dato_General_accidente.php','accidente_general_tabs.php','accidente_general_sticky_modulos.php') ?>" href="Dato_General_accidente.php?accidente_id=<?=h($accidente_id)?>">
            <span class="section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 3H5v18h14V8zM14 3v5h5M9 12h6M9 16h6"/></svg></span>
            <span>Ver datos generales</span>
        </a>
        <a class="btn<?= sidebar_active('accidente_vista_tabs.php') ?>" href="accidente_vista_tabs.php?accidente_id=<?=h($accidente_id)?>">
            <span class="section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 7h7l2 2h9v12H3zM3 7V3h7l2 4"/></svg></span>
            <span>Vista por tabs</span>
        </a>
        <?php endif; ?>
        <a class="btn<?= sidebar_active('citacion_rapida.php') ?>" href="<?= h($googleCalendarUrl) ?>">
            <span class="section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16v16H4zM8 3v4M16 3v4M4 10h16M8 14h2M14 14h2"/></svg></span>
            <span>Google Calendar</span>
        </a>
        <a class="btn<?= sidebar_active('enlaces_interes_listar.php') ?>" href="enlaces_interes_listar.php">
            <span class="section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m10 13 4-4M8 15l-1 1a4 4 0 0 1-6-6l4-4a4 4 0 0 1 6 0M16 9l1-1a4 4 0 0 1 6 6l-4 4a4 4 0 0 1-6 0"/></svg></span>
            <span>Enlaces de interes</span>
        </a>
        <a class="btn<?= sidebar_active('oficio_entidades_listar.php') ?>" href="oficio_entidades_listar.php">
            <span class="section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v16M12 5C8 2 3 3 3 3v16s5-1 9 2c4-3 9-2 9-2V3s-5-1-9 2"/></svg></span>
            <span>Prontuario entidades</span>
        </a>
        <a class="btn<?= sidebar_active('catalogos.php') ?>" href="catalogos.php">
            <span class="section-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/></svg></span>
            <span>Catalogos</span>
        </a>
    </div>

    <?php if (false): ?>
    <!-- INVOLUCRADOS -->
    <div class="side-section">
        <div class="section-header" data-sec="inv">
            <span class="section-icon">🚦</span>
            <span>Involucrados</span>
        </div>

        <div class="section-body" id="sec-inv">
            <a class="side-item" href="involucrados_vehiculos_listar.php?accidente_id=<?=h($accidente_id)?>">
                <span class="section-icon">🚗</span>
                <span>Vehículos inv.</span>
                <span class="led <?=$cntVeh>0?'on':''?>"></span>
            </a>

            <a class="side-item" href="involucrados_personas_listar.php?accidente_id=<?=h($accidente_id)?>">
                <span class="section-icon">👥</span>
                <span>Personas inv.</span>
                <span class="led <?=$cntPer>0?'on':''?>"></span>
            </a>
        </div>
    </div>

    <!-- AUXILIARES -->
    <div class="side-section">
        <div class="section-header" data-sec="aux">
            <span class="section-icon">🛟</span>
            <span>Auxiliares</span>
        </div>

        <div class="section-body" id="sec-aux">
            <a class="side-item" href="propietario_vehiculo_listar.php?accidente_id=<?=h($accidente_id)?>">
                <span class="section-icon">🚘</span>
                <span>Propietario</span>
                <span class="led <?=$cntProp>0?'on':''?>"></span>
            </a>

            <a class="side-item" href="familiar_fallecido_listar.php?accidente_id=<?=h($accidente_id)?>">
                <span class="section-icon">💀</span>
                <span>Familiar</span>
                <span class="led <?=$cntFam>0?'on':''?>"></span>
            </a>

            <a class="side-item" href="policial_interviniente_listar.php?accidente_id=<?=h($accidente_id)?>">
                <span class="section-icon">👮‍♂️</span>
                <span>Efectivo policial</span>
                <span class="led <?=$cntEfec>0?'on':''?>"></span>
            </a>
        </div>
    </div>

    <!-- ASESORÍA JURÍDICA -->
    <div class="side-section">
        <div class="section-header" data-sec="ase">
            <span class="section-icon">⚖️</span>
            <span>Asesoría jurídica</span>
        </div>

        <div class="section-body" id="sec-ase">
            <a class="side-item" href="abogado_listar.php?accidente_id=<?=h($accidente_id)?>">
                <span class="section-icon">👨‍⚖️</span>
                <span>Abogados</span>
                <span class="led <?=$cntAbog>0?'on':''?>"></span>
            </a>
        </div>
    </div>

    <!-- DOCUMENTOS (NUEVO) -->
    <div class="side-section">
        <div class="section-header" data-sec="doc">
            <span class="section-icon">📑</span>
            <span>Documentos</span>
        </div>

        <div class="section-body" id="sec-doc">
            <a class="side-item" href="documento_recibido_listar.php?accidente_id=<?=h($accidente_id)?>">
                <span class="section-icon">📥</span>
                <span>Documentos recibidos</span>
                <span class="led on"></span>
            </a>
        </div>
    </div>

    <!-- DILIGENCIAS -->
    <div class="side-section">
        <div class="section-header" data-sec="dil">
            <span class="section-icon">📂</span>
            <span>Diligencias</span>
        </div>

        <div class="section-body" id="sec-dil">
            <a class="side-item" href="itp_listar.php?accidente_id=<?=h($accidente_id)?>">
                <span class="section-icon">🧾</span>
                <span>ITP</span>
                <span class="led <?=$cntItp>0?'on':''?>"></span>
            </a>

            <a class="side-item" href="oficios_listar.php?accidente_id=<?=h($accidente_id)?>">
                <span class="section-icon">📄</span>
                <span>Oficios</span>
                <span class="led <?=$cntOfi>0?'on':''?>"></span>
            </a>

            <a class="side-item" href="citacion_listar.php?accidente_id=<?=h($accidente_id)?>">
                <span class="section-icon">📅</span>
                <span>Citaciones</span>
                <span class="led <?=$cntCit>0?'on':''?>"></span>
            </a>

            <a class="side-item" href="diligenciapendiente_listar.php?accidente_id=<?=h($accidente_id)?>">
                <span class="section-icon">📋</span>
                <span>Diligencias pendientes</span>
                <span class="led <?=$cntDil>0?'on':''?>"></span>
            </a>
        </div>
    </div>

    <?php endif; ?>
</nav>
</div>

<script>
(() => {
  const sidebar = document.getElementById('app-sidebar');
  const toggle = document.getElementById('sidebar-toggle');
  const backdrop = document.getElementById('sidebar-backdrop');
  const mobile = window.matchMedia('(max-width: 1100px)');
  const hoverPointer = window.matchMedia('(hover: hover) and (pointer: fine)');
  let hoverCloseTimer;
  document.body.classList.add('uiat-has-sidebar');
  const links = [...sidebar.querySelectorAll('.top-actions a')];
  links.forEach(link => {
    const label = link.textContent.trim();
    link.setAttribute('aria-label', label);
    link.title = label;
    if (link.classList.contains('active')) link.setAttribute('aria-current', 'page');
  });
  const setOpen = (open, restoreFocus = false) => {
    clearTimeout(hoverCloseTimer);
    sidebar.classList.toggle('expanded', open);
    document.body.classList.toggle('uiat-sidebar-expanded', open && !mobile.matches);
    document.body.classList.toggle('uiat-sidebar-open', open && mobile.matches);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    sidebar.inert = mobile.matches && !open;
    backdrop.hidden = !(mobile.matches && open);
    if (restoreFocus) toggle.focus();
  };
  // Include the floating toggle in the same hover area as the drawer.
  const isInsideMenu = target => target instanceof Node && (sidebar.contains(target) || toggle.contains(target));
  const scheduleHoverClose = () => {
    clearTimeout(hoverCloseTimer);
    hoverCloseTimer = setTimeout(() => {
      if (!sidebar.contains(document.activeElement)) setOpen(false);
    }, 180);
  };
  [sidebar, toggle].forEach(element => {
    element.addEventListener('mouseenter', event => {
      if (mobile.matches || !hoverPointer.matches) return;
      clearTimeout(hoverCloseTimer);
      if (!isInsideMenu(event.relatedTarget)) setOpen(true);
    });
    element.addEventListener('mouseleave', event => {
      if (mobile.matches || !hoverPointer.matches || isInsideMenu(event.relatedTarget)) return;
      scheduleHoverClose();
    });
  });
  sidebar.addEventListener('focusout', event => {
    if (!mobile.matches && hoverPointer.matches && !isInsideMenu(event.relatedTarget) && !sidebar.matches(':hover') && !toggle.matches(':hover')) scheduleHoverClose();
  });
  toggle.addEventListener('click', () => {
    const open = !sidebar.classList.contains('expanded');
    setOpen(open);
    if (open && mobile.matches) links[0]?.focus();
  });
  backdrop.addEventListener('click', () => setOpen(false, true));
  links.forEach(link => link.addEventListener('click', () => setOpen(false)));
  document.addEventListener('keydown', event => {
    if (!sidebar.classList.contains('expanded')) return;
    if (event.key === 'Escape') { setOpen(false, true); return; }
    if (event.key === 'Tab' && mobile.matches) {
      const focusable = [toggle, ...links];
      const current = focusable.indexOf(document.activeElement);
      if (event.shiftKey && current <= 0) {event.preventDefault();links[links.length - 1]?.focus();}
      else if (!event.shiftKey && (current === focusable.length - 1 || current < 0)) {event.preventDefault();toggle.focus();}
    }
  });
  mobile.addEventListener('change', () => {
    const hadFocus = sidebar.contains(document.activeElement);
    setOpen(false, hadFocus);
  });
  setOpen(false);
})();
</script>
