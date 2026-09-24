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
$s=$pdo->prepare('SELECT m.nombre FROM accidente_modalidad_activos am JOIN modalidad_accidente m ON m.id=am.modalidad_id WHERE am.accidente_id=?');$s->execute([$id]);$modalidades=$s->fetchAll(PDO::FETCH_COLUMN);
$s=$pdo->prepare("SELECT p.nombres,p.apellido_paterno,p.apellido_materno,COALESCE(ip.lesion,'') lesion,COALESCE(r.Nombre,'Participante') rol,
 v.placa,mv.nombre marca,modv.nombre modelo,iv.orden_participacion
 FROM involucrados_personas_activos ip JOIN personas p ON p.id=ip.persona_id
 LEFT JOIN participacion_persona r ON r.Id=ip.rol_id
 LEFT JOIN vehiculos v ON v.id=ip.vehiculo_id
 LEFT JOIN marcas_vehiculo mv ON mv.id=v.marca_id LEFT JOIN modelos_vehiculo modv ON modv.id=v.modelo_id
 LEFT JOIN involucrados_vehiculos_activos iv ON iv.accidente_id=ip.accidente_id AND iv.vehiculo_id=ip.vehiculo_id
 WHERE ip.accidente_id=? ORDER BY COALESCE(iv.orden_participacion,''),ip.id");$s->execute([$id]);$people=$s->fetchAll(PDO::FETCH_ASSOC);
$s=$pdo->prepare("SELECT iv.orden_participacion,v.placa,mv.nombre marca,modv.nombre modelo,v.color,tv.nombre tipo
 FROM involucrados_vehiculos_activos iv JOIN vehiculos v ON v.id=iv.vehiculo_id
 LEFT JOIN tipos_vehiculo tv ON tv.id=v.tipo_id LEFT JOIN marcas_vehiculo mv ON mv.id=v.marca_id LEFT JOIN modelos_vehiculo modv ON modv.id=v.modelo_id WHERE iv.accidente_id=? ORDER BY iv.orden_participacion");$s->execute([$id]);$vehicles=$s->fetchAll(PDO::FETCH_ASSOC);
$icon=static function(string $name): string {
    $paths=['place'=>'<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>','date'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>','station'=>'<path d="M3 10h18M5 10v10m4-10v10m6-10v10m4-10v10M3 20h18M12 3 2 8h20L12 3Z"/>','people'=>'<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 5v1"/>','car'=>'<path d="m5 11 2-5h10l2 5M3 11h18v8H3zM5 19v2m14-2v2M6 15h.01M18 15h.01"/>','court'=>'<path d="m12 3 9 5H3l9-5ZM5 8v11m5-11v11m4-11v11m5-11v11M3 20h18"/>','badge'=>'<circle cx="12" cy="8" r="5"/><path d="M8 12 7 22l5-3 5 3-1-10"/>','folder'=>'<path d="M3 6a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>','file'=>'<path d="M6 3h8l5 5v13H6zM14 3v6h5M9 14h7m-7 4h7"/>'];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'.$paths[$name].'</svg>';
};
$state=trim((string)$a['estado'])?:'Pendiente';
?>
<article class="case-detail-card">
 <header class="case-card-head">
   <div class="case-card-symbol"><?= $icon('badge') ?></div>
   <div class="case-card-identifiers"><span class="case-chip case-chip-sidpol">SIDPOL <?= $h($a['registro_sidpol']?:'Sin número') ?></span><?php if($a['nro_informe_policial']): ?><span class="case-chip">Informe <?= $h($a['nro_informe_policial']) ?></span><?php endif ?><?php if($a['folder']): ?><span class="case-chip case-chip-folder"><?= $icon('folder') ?> Carpeta <?= $h($a['folder']) ?></span><?php endif ?></div>
   <button type="button" class="case-modal-close" data-case-modal-close aria-label="Cerrar">×</button>
 </header>
 <div class="case-card-badges"><span class="case-chip case-status-<?= $h(mb_strtolower(str_replace(' ','-', $state))) ?>"><?= $h($state) ?></span><?php if($a['tipo_registro']): ?><span class="case-chip case-chip-type"><?= $h($a['tipo_registro']) ?></span><?php endif ?></div>
 <?php if($a['eliminado_en']): ?><p class="case-card-deleted">Expediente eliminado</p><?php endif ?>
 <h2 class="case-card-place"><?= $icon('place') ?><span><?= $h($a['lugar']) ?><?= $a['distrito']?' <span class="case-card-muted">· '. $h($a['distrito']).'</span>':'' ?></span></h2>
 <p class="case-card-modality"><span>MODALIDAD</span><?= $h(implode(', ',$modalidades)?:'Sin registrar') ?></p>
 <div class="case-card-facts">
  <div><span class="case-card-label"><?= $icon('date') ?> FECHA</span><strong><?= $h($a['fecha_accidente']?date('d/m/Y H:i',strtotime($a['fecha_accidente'])):'Sin registrar') ?></strong></div>
  <div><span class="case-card-label"><?= $icon('station') ?> COMISARÍA</span><strong><?= $h($a['comisaria']?:'Sin registrar') ?></strong></div>
 </div>
 <section class="case-card-section"><h3><?= $icon('people') ?> INVOLUCRADOS</h3>
 <?php if(!$people && !$vehicles): ?><p class="case-card-empty">Sin participantes registrados</p><?php endif ?>
 <?php foreach($vehicles as $v): ?><div class="case-card-person case-card-vehicle"><span class="case-person-icon"><?= $icon('car') ?></span><div><strong><?= $h(trim(($v['orden_participacion']?:'Unidad').' · '.($v['marca']?:'Vehículo').' '.($v['modelo']??''))) ?></strong><small><?= $h(trim(($v['tipo']??'Vehículo').' · '.($v['placa']?:'Sin placa').' · '.($v['color']??''),' ·')) ?></small></div></div><?php endforeach ?>
 <?php foreach($people as $person): ?><div class="case-card-person"><span class="case-person-icon"><?= $icon('badge') ?></span><div><strong><?= $h(trim($person['nombres'].' '.$person['apellido_paterno'].' '.$person['apellido_materno'])) ?></strong><small><?= $h($person['rol'].($person['lesion']?' · '.$person['lesion']:'')) ?><?= $person['placa']?' · '. $h($person['placa']):'' ?></small></div></div><?php endforeach ?>
 </section>
 <section class="case-card-section case-card-prosecutor"><h3><?= $icon('court') ?> FISCALÍA Y DOCUMENTACIÓN</h3>
  <div class="case-card-person"><span class="case-person-icon"><?= $icon('court') ?></span><div><span class="case-card-label">FISCALÍA</span><strong><?= $h($a['fiscalia']?:'Sin registrar') ?></strong></div></div>
  <div class="case-card-person"><span class="case-person-icon"><?= $icon('badge') ?></span><div><span class="case-card-label">FISCAL A CARGO</span><strong><?= $h($a['fiscal']?:'Sin registrar') ?></strong></div></div>
 </section>
 <section class="case-card-section case-card-owner"><h3><?= $icon('badge') ?> ENCARGADO DEL EXPEDIENTE</h3>
  <div class="case-card-person"><span class="case-person-icon"><?= $icon('badge') ?></span><div><strong><?= $h(trim(($a['responsable_grado']??'').' '.($a['responsable']??''))?:'Pendiente de asignación') ?></strong><small>JEFE EMI responsable</small></div></div>
 </section>

</article>
