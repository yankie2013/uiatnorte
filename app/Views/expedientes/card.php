<?php
$s=$pdo->prepare("SELECT a.*,u.nombre responsable,u.grado responsable_grado,c.nombre comisaria,d.nombre distrito,f.nombre fiscalia,
 TRIM(CONCAT_WS(' ',fi.nombres,fi.apellido_paterno,fi.apellido_materno)) fiscal
 FROM accidentes a LEFT JOIN usuarios u ON u.id=a.responsable_id
 LEFT JOIN comisarias c ON c.id=a.comisaria_id
 LEFT JOIN ubigeo_distrito d ON d.cod_dep=a.cod_dep AND d.cod_prov=a.cod_prov AND d.cod_dist=a.cod_dist
 LEFT JOIN fiscalia f ON f.id=a.fiscalia_id LEFT JOIN fiscales fi ON fi.id=a.fiscal_id WHERE a.id=?");
$s->execute([$id]);$a=$s->fetch();
if(!$a || ($a['eliminado_en'] && !\App\Support\Access::admin())){
    \App\Support\WorkspacePage::notice('Expediente no disponible.',true);
    return;
}
$h=static fn($v)=>\App\Support\WorkspacePage::escape($v);
$s=$pdo->prepare('SELECT m.nombre FROM accidente_modalidad am JOIN modalidad_accidente m ON m.id=am.modalidad_id WHERE am.accidente_id=?');$s->execute([$id]);$modalidades=$s->fetchAll(PDO::FETCH_COLUMN);
$s=$pdo->prepare("SELECT DISTINCT CONCAT_WS(' ',p.nombres,p.apellido_paterno,p.apellido_materno) nombre FROM involucrados_personas ip JOIN personas p ON p.id=ip.persona_id WHERE ip.accidente_id=?");$s->execute([$id]);$people=$s->fetchAll(PDO::FETCH_COLUMN);
?>
<style>
.case-detail-card{max-width:520px;background:#f3fcf9;border:1px solid #56cda9;border-radius:16px;padding:22px;margin:24px 0;color:#263e4d;box-shadow:0 6px 22px #164c3610}
.case-detail-card .badges{display:flex;gap:9px;flex-wrap:wrap}.case-detail-card .badge{background:#e5edf2;border-radius:20px;padding:5px 11px;font-size:12px;font-weight:700}
.case-detail-card .status{background:#f8dbd8}.case-detail-card .type{background:#d0f5fc}.case-detail-card h2{font-size:17px;margin:18px 0 10px}
.case-detail-card dl{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:18px 0}.case-detail-card dt,.case-detail-card .caption{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#758796}
.case-detail-card dd{margin:4px 0 0;font-size:13px;font-weight:600}.case-detail-card .block{padding:14px 0;border-top:1px solid #d5e5e8}
.case-detail-card p{margin:5px 0;font-size:13px;color:#334b60}.case-detail-card .actions{padding-top:16px}
.reception-item{border-bottom:1px solid #d5e3dd;padding:16px 0}
</style>
<article class="case-detail-card">
 <div class="badges"><span class="badge"><?= $h($a['registro_sidpol']?:'#'.$id) ?></span><span class="badge status"><?= $h($a['estado']?:'Pendiente') ?></span><?php if($a['tipo_registro']): ?><span class="badge type"><?= $h($a['tipo_registro']) ?></span><?php endif ?></div>
 <?php if($a['eliminado_en']): ?><p>Expediente eliminado</p><?php endif ?>
 <h2>📍 <?= $h($a['lugar']) ?><?= $a['distrito']?' · '.$h($a['distrito']):'' ?></h2>
 <p><span class="caption">Modalidad</span> <?= $h(implode(', ',$modalidades)?:'Sin registrar') ?></p>
 <dl><div><dt>Fecha</dt><dd><?= $h($a['fecha_accidente']?date('d/m/Y H:i',strtotime($a['fecha_accidente'])):'Sin registrar') ?></dd></div><div><dt>Comisaría</dt><dd><?= $h($a['comisaria']?:'Sin registrar') ?></dd></div></dl>
 <div class="block"><span class="caption">Involucrados</span><?php foreach($people as $person): ?><p><?= $h($person) ?></p><?php endforeach ?><?php if(!$people): ?><p>Sin personas registradas</p><?php endif ?></div>
 <div class="block"><span class="caption">Fiscalía</span><p><?= $h($a['fiscalia']?:'Sin registrar') ?></p><span class="caption">Fiscal a cargo</span><p><?= $h($a['fiscal']?:'Sin registrar') ?></p></div>
 <div class="block"><span class="caption">Responsable</span><p><?= $h(responsible_label($a['responsable'],$a['responsable_grado'])) ?></p></div>
 <div class="actions"><a href="accidente_vista_tabs.php?accidente_id=<?= $id ?>&tab=estado">Estado y gestión</a><a href="accidente_vista_tabs.php?accidente_id=<?= $id ?>">Abrir expediente</a><a href="gestion_expedientes.php">Volver al buscador</a></div>
</article>
