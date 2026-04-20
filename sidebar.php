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

?>
<style>
/* Sidebar layout */
.sidebar-wrap{
    position:fixed; top:0; left:0; height:100vh; z-index:9998;
    pointer-events:none;
}

.sidebar-backdrop{
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.46);
    opacity:0;
    visibility:hidden;
    pointer-events:none;
    transition:opacity .25s ease, visibility .25s ease;
    z-index:9997;
}

.sidebar-toggle{
    position:fixed;
    top:14px;
    left:14px;
    width:44px;
    height:44px;
    display:none;
    align-items:center;
    justify-content:center;
    border:1px solid var(--uiat-sidebar-border, #24344a);
    border-radius:8px;
    background:var(--uiat-sidebar-elevated, #162339);
    color:var(--uiat-sidebar-text, #e8eefc);
    box-shadow:0 10px 24px rgba(15,23,42,.16);
    cursor:pointer;
    z-index:10000;
}

.sidebar-toggle:focus-visible{
    outline:2px solid var(--uiat-sidebar-accent, #60a5fa);
    outline-offset:2px;
}

/* Sidebar collapsed/expanded */
.sidebar{
    width:60px;
    height:100%;
    background:var(--uiat-sidebar-bg, #0d1526);
    color:var(--uiat-sidebar-text, #e8eefc);
    padding:14px 0;
    overflow:visible;
    display:flex;
    flex-direction:column;
    gap:6px;
    transition:width .35s cubic-bezier(.22,.61,.36,1);
    box-shadow:3px 0 18px rgba(0,0,0,.20);
    border-right:1px solid var(--uiat-sidebar-border, #24344a);
    overflow-y:auto;
    overflow-x:hidden;
    pointer-events:auto;
}
.sidebar.expanded{ width:240px; }

/* Ajusta el contenido principal */
body.uiat-has-sidebar{
    padding-left:60px;
    transition:padding-left .35s cubic-bezier(.22,.61,.36,1);
}

body.uiat-has-sidebar.uiat-sidebar-expanded{
    padding-left:240px;
}

body.uiat-sidebar-open .sidebar-backdrop{
    opacity:1;
    visibility:visible;
    pointer-events:auto;
}

/* Títulos */
.section-icon{ width:26px; text-align:center; display:inline-block; }

.section-header{
    display:flex; align-items:center; gap:10px;
    padding:10px 14px;
    background:var(--uiat-sidebar-elevated, #162339);
    border-radius:8px;
    font-size:14px;
    cursor:pointer;
    white-space:nowrap;
    overflow:hidden;
    transition:.2s;
}
.section-header:hover{ background:var(--uiat-sidebar-hover, #243657); }

.section-body{
    overflow:hidden;
    max-height:0;
    transition:max-height .35s cubic-bezier(.22,.61,.36,1);
}

.side-item{
    display:flex; align-items:center; gap:10px;
    padding:10px 22px;
    background:var(--uiat-sidebar-surface, #1a283f);
    border-radius:8px;
    text-decoration:none;
    color:inherit;
    margin-top:4px;
    font-size:13px;
    white-space:nowrap;
    transition:.2s;
    border:1px solid transparent;
}
.side-item:hover{
    background:var(--uiat-sidebar-hover, #243657);
    transform:translateX(6px);
}

/* Botones superiores */
#app-sidebar .top-actions{
    margin:0 10px 8px;
    display:flex;
    flex-direction:column;
    gap:8px;
}
#app-sidebar .top-actions .btn{
    display:flex;
    align-items:center;
    gap:10px;
    padding:10px 14px;
    border-radius:8px;
    background:var(--uiat-sidebar-elevated, #103056);
    color:inherit;
    text-decoration:none;
    white-space:nowrap;
    border:1px solid var(--uiat-sidebar-border, #24344a);
}
#app-sidebar .top-actions .btn:hover{
    background:var(--uiat-sidebar-hover, #13426f);
}

/* Ocultar texto cuando colapsado */
#app-sidebar:not(.expanded) .top-actions .btn > span:not(.section-icon),
#app-sidebar:not(.expanded) .section-header > span:not(.section-icon),
#app-sidebar:not(.expanded) .side-item > span:not(.section-icon){
    display:none;
    opacity:0;
    width:0;
    margin:0;
}

#app-sidebar:not(.expanded) .top-actions .btn{
    justify-content:center;
    padding-left:10px;
    padding-right:10px;
}

/* LEDs */
.led{
    width:9px; height:9px; margin-left:auto;
    border-radius:50%;
    background:var(--uiat-sidebar-muted, #4b5563);
}
.led.on{
    background:#34d399;
    box-shadow:0 0 6px rgba(52,211,153,.45);
}

@media (max-width: 1100px){
    .sidebar-toggle{
        display:none;
    }

    .sidebar-backdrop{
        display:none;
    }

    .sidebar-wrap{
        top:auto;
        bottom:0;
        left:0;
        right:0;
        width:100%;
        height:auto;
        padding:0 0 env(safe-area-inset-bottom, 0px);
        pointer-events:auto;
    }

    .sidebar,
    .sidebar.expanded{
        width:100% !important;
        max-width:none;
        height:auto;
        max-height:none;
        padding:0;
        border-right:none;
        border-top:1px solid var(--uiat-sidebar-border, #24344a);
        border-radius:0;
        transform:none !important;
        transition:box-shadow .25s ease, background-color .2s ease, border-color .2s ease;
        box-shadow:0 -8px 20px rgba(2,6,23,.14);
        overflow:visible;
    }

    #app-sidebar .top-actions{
        margin:0 !important;
        display:flex !important;
        flex-direction:row !important;
        flex-wrap:nowrap !important;
        align-items:stretch !important;
        gap:0 !important;
        width:100% !important;
        overflow-x:auto !important;
        overflow-y:hidden !important;
        padding-bottom:4px !important;
        scrollbar-width:thin;
        scrollbar-color:var(--uiat-sidebar-border, #24344a) var(--uiat-sidebar-surface, #1a283f);
        -webkit-overflow-scrolling:touch;
        overscroll-behavior-x:contain;
    }

    #app-sidebar .top-actions::-webkit-scrollbar{
        height:6px;
    }

    #app-sidebar .top-actions::-webkit-scrollbar-track{
        background:var(--uiat-sidebar-surface, #1a283f);
    }

    #app-sidebar .top-actions::-webkit-scrollbar-thumb{
        background:var(--uiat-sidebar-border, #24344a);
        border-radius:999px;
    }

    #app-sidebar .top-actions .btn{
        display:flex !important;
        flex:0 0 auto !important;
        flex-shrink:0 !important;
        width:112px !important;
        min-width:112px !important;
        max-width:112px !important;
        min-height:72px !important;
        padding:10px 6px !important;
        gap:4px !important;
        justify-content:center !important;
        align-items:center !important;
        flex-direction:column !important;
        text-align:center !important;
        white-space:nowrap !important;
        line-height:1.05 !important;
        font-size:10px !important;
        border-radius:0 !important;
        border:0 !important;
        border-right:1px solid var(--uiat-sidebar-border, #24344a) !important;
        background:var(--uiat-sidebar-surface, #1a283f) !important;
        box-shadow:none !important;
    }

    #app-sidebar .top-actions .btn .section-icon{
        width:auto !important;
        font-size:16px !important;
        line-height:1 !important;
    }

    #app-sidebar:not(.expanded) .top-actions .btn > span:not(.section-icon){
        display:block;
        opacity:1;
        width:auto;
        margin:0;
    }

    #app-sidebar .top-actions .btn > span:not(.section-icon){
        max-width:100% !important;
        overflow:hidden !important;
        text-overflow:ellipsis !important;
        font-size:inherit !important;
        line-height:inherit !important;
    }

    .side-section{
        display:none;
    }

    body.uiat-has-sidebar,
    body.uiat-has-sidebar.uiat-sidebar-expanded{
        padding-left:0;
        padding-bottom:92px;
    }

    body.uiat-sidebar-open{
        overflow:auto;
    }
}

@media (max-width: 560px){
    #app-sidebar .top-actions .btn{
        width:104px !important;
        min-width:104px !important;
        max-width:104px !important;
        min-height:68px !important;
        font-size:9px !important;
    }

    #app-sidebar .top-actions .btn .section-icon{
        font-size:15px !important;
    }
}
</style>

<button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-controls="app-sidebar" aria-expanded="false" aria-label="Abrir menu">
    <span aria-hidden="true">&#9776;</span>
</button>
<div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
<div class="sidebar-wrap">
<nav class="sidebar" id="app-sidebar">

    <!-- BOTONES SUPERIORES -->
    <div class="top-actions">


<a class="btn" href="index.php">
        <span class="section-icon">🏠</span>
        <span>Ir a panel</span>
    </a>

        <a class="btn" href="accidente_listar.php">
            <span class="section-icon">⬅️</span>
            <span>Volver a lista</span>
        </a>

        <?php if($accidente_id !== null): ?>
        <a class="btn" href="Dato_General_accidente.php?accidente_id=<?=h($accidente_id)?>">
            <span class="section-icon">📄</span>
            <span>Ver datos generales</span>
        </a>
        <a class="btn" href="accidente_vista_tabs.php?accidente_id=<?=h($accidente_id)?>">
            <span class="section-icon">🗂</span>
            <span>Vista por tabs</span>
        </a>
        <?php endif; ?>
        <a class="btn" href="enlaces_interes_listar.php">
            <span class="section-icon">&#128279;</span>
            <span>Enlaces de interes</span>
        </a>
        <a class="btn" href="oficio_entidades_listar.php">
            <span class="section-icon">&#128214;</span>
            <span>Prontuario entidades</span>
        </a>
        <a class="btn" href="catalogos.php">
            <span class="section-icon">&#128451;</span>
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
document.querySelectorAll(".section-header").forEach(h => {
    h.addEventListener("click", () => {
        const target = document.getElementById("sec-" + h.dataset.sec);

        document.querySelectorAll(".section-body").forEach(b => {
            if (b !== target) b.style.maxHeight = "0px";
        });

        target.style.maxHeight =
            (target.style.maxHeight !== "0px" && target.style.maxHeight !== "")
            ? "0px"
            : target.scrollHeight + "px";
    });
});

document.body.classList.add('uiat-has-sidebar');

const sidebar = document.getElementById('app-sidebar');
const sidebarToggle = document.getElementById('sidebar-toggle');
const sidebarBackdrop = document.getElementById('sidebar-backdrop');
const mobileQuery = window.matchMedia('(max-width: 1100px)');
let expandTimeout = null;

function closeSections() {
    document.querySelectorAll('.section-body').forEach(b => b.style.maxHeight = "0px");
}

function isMobileSidebar() {
    return mobileQuery.matches;
}

function syncToggleState() {
    const expanded = sidebar.classList.contains('expanded');
    if (sidebarToggle) {
        sidebarToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
}

function openSidebar() {
    clearTimeout(expandTimeout);
    sidebar.classList.add('expanded');
    if (isMobileSidebar()) {
        document.body.classList.add('uiat-sidebar-open');
        if (sidebarBackdrop) sidebarBackdrop.hidden = false;
    } else {
        document.body.classList.add('uiat-sidebar-expanded');
    }
    syncToggleState();
}

function closeSidebar() {
    sidebar.classList.remove('expanded');
    document.body.classList.remove('uiat-sidebar-open', 'uiat-sidebar-expanded');
    if (sidebarBackdrop) sidebarBackdrop.hidden = true;
    closeSections();
    syncToggleState();
}

function syncSidebarMode() {
    if (isMobileSidebar()) {
        document.body.classList.remove('uiat-sidebar-open', 'uiat-sidebar-expanded');
        if (sidebarBackdrop) sidebarBackdrop.hidden = true;
        sidebar.classList.remove('expanded');
    } else {
        document.body.classList.remove('uiat-sidebar-open');
        if (sidebarBackdrop) sidebarBackdrop.hidden = true;
        sidebar.classList.remove('expanded');
    }
    syncToggleState();
}

sidebar.addEventListener('mouseenter', () => {
    if (isMobileSidebar()) return;
    openSidebar();
});

sidebar.addEventListener('mouseleave', () => {
    if (isMobileSidebar()) return;
    expandTimeout = setTimeout(() => {
        closeSidebar();
    }, 160);
});

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        if (!isMobileSidebar()) return;
        if (document.body.classList.contains('uiat-sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });
}

if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', closeSidebar);
}

document.querySelectorAll('#app-sidebar a').forEach(link => {
    link.addEventListener('click', () => {
        if (isMobileSidebar()) {
            closeSidebar();
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.body.classList.contains('uiat-sidebar-open')) {
        closeSidebar();
    }
});

if (typeof mobileQuery.addEventListener === 'function') {
    mobileQuery.addEventListener('change', syncSidebarMode);
} else if (typeof mobileQuery.addListener === 'function') {
    mobileQuery.addListener(syncSidebarMode);
}

syncSidebarMode();
</script>
