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
    position:fixed; top:0; left:0; height:100vh; z-index:9999;
}

/* Sidebar collapsed/expanded */
.sidebar{
    width:60px;
    height:100%;
    background:#0d1526;
    color:white;
    padding:14px 0;
    overflow:visible;
    display:flex;
    flex-direction:column;
    gap:6px;
    transition:width .35s cubic-bezier(.22,.61,.36,1);
    box-shadow:3px 0 18px rgba(0,0,0,.35);
}
.sidebar.expanded{ width:240px; }

/* Ajusta el contenido principal */
body { padding-left:60px; transition:.35s; }
.sidebar.expanded ~ * { padding-left:240px; }

/* Títulos */
.section-icon{ width:26px; text-align:center; display:inline-block; }

.section-header{
    display:flex; align-items:center; gap:10px;
    padding:10px 14px;
    background:#162339;
    border-radius:8px;
    font-size:14px;
    cursor:pointer;
    white-space:nowrap;
    overflow:hidden;
    transition:.2s;
}
.section-header:hover{ background:#1d2d4b; }

.section-body{
    overflow:hidden;
    max-height:0;
    transition:max-height .35s cubic-bezier(.22,.61,.36,1);
}

.side-item{
    display:flex; align-items:center; gap:10px;
    padding:10px 22px;
    background:#1a283f;
    border-radius:8px;
    text-decoration:none;
    color:white;
    margin-top:4px;
    font-size:13px;
    white-space:nowrap;
    transition:.2s;
}
.side-item:hover{
    background:#243657;
    transform:translateX(6px);
}

/* Botones superiores */
.top-actions{
    margin:0 10px 8px;
    display:flex;
    flex-direction:column;
    gap:8px;
}
.top-actions .btn{
    display:flex;
    align-items:center;
    gap:10px;
    padding:10px 14px;
    border-radius:8px;
    background:#103056;
    color:white;
    text-decoration:none;
    white-space:nowrap;
}
.top-actions .btn:hover{
    background:#13426f;
}

/* Ocultar texto cuando colapsado */
.sidebar:not(.expanded) .top-actions .btn > span:not(.section-icon),
.sidebar:not(.expanded) .section-header > span:not(.section-icon),
.sidebar:not(.expanded) .side-item > span:not(.section-icon){
    display:none;
    opacity:0;
    width:0;
    margin:0;
}

.sidebar:not(.expanded) .top-actions .btn{
    justify-content:center;
    padding-left:10px;
    padding-right:10px;
}

/* LEDs */
.led{
    width:9px; height:9px; margin-left:auto;
    border-radius:50%;
    background:#4b5563;
}
.led.on{
    background:#34d399;
    box-shadow:0 0 6px rgba(52,211,153,.45);
}
</style>

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

const sidebar = document.getElementById('app-sidebar');
let expandTimeout = null;

sidebar.addEventListener('mouseenter', () => {
    clearTimeout(expandTimeout);
    sidebar.classList.add('expanded');
});

sidebar.addEventListener('mouseleave', () => {
    expandTimeout = setTimeout(() => {
        sidebar.classList.remove('expanded');
        document.querySelectorAll('.section-body').forEach(b => b.style.maxHeight = "0px");
    }, 160);
});
</script>
