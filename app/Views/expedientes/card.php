<?php
$s=$pdo->prepare("SELECT a.*,u.nombre responsable,u.grado responsable_grado,u.rol responsable_rol,c.nombre comisaria,d.nombre distrito,f.nombre fiscalia,
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
$archiveQuery=$pdo->prepare("SELECT t.*,u.nombre aceptado_por,u.grado aceptado_grado FROM expediente_transferencias t JOIN usuarios u ON u.id=t.destino_id WHERE t.accidente_id=? AND t.tipo='archivo' AND t.estado='aceptada' ORDER BY t.id DESC LIMIT 1");
$archiveQuery->execute([$id]);
$archiveTransfer=$archiveQuery->fetch(PDO::FETCH_ASSOC)?:null;
$guardiaConsulta=\App\Support\Access::role()==='guardia';
// La tarjeta de consulta ya comprobó que el accidente está activo. Para guardia
// se usan las tablas base porque las vistas importadas pueden conservar un DEFINER ajeno.
$modalidadTabla=$guardiaConsulta?'accidente_modalidad':'accidente_modalidad_activos';
$personasTabla=$guardiaConsulta?'involucrados_personas':'involucrados_personas_activos';
$vehiculosTabla=$guardiaConsulta?'involucrados_vehiculos':'involucrados_vehiculos_activos';
$s=$pdo->prepare("SELECT m.nombre FROM $modalidadTabla am JOIN modalidad_accidente m ON m.id=am.modalidad_id WHERE am.accidente_id=?");$s->execute([$id]);$modalidades=$s->fetchAll(PDO::FETCH_COLUMN);
$s=$pdo->prepare("SELECT ip.vehiculo_id,p.nombres,p.apellido_paterno,p.apellido_materno,COALESCE(ip.lesion,'') lesion,COALESCE(r.Nombre,'Participante') rol,
 v.placa,mv.nombre marca,modv.nombre modelo,iv.orden_participacion,tv.nombre vehiculo_tipo
 FROM $personasTabla ip JOIN personas p ON p.id=ip.persona_id
 LEFT JOIN participacion_persona r ON r.Id=ip.rol_id
 LEFT JOIN vehiculos v ON v.id=ip.vehiculo_id
 LEFT JOIN tipos_vehiculo tv ON tv.id=v.tipo_id
 LEFT JOIN marcas_vehiculo mv ON mv.id=v.marca_id LEFT JOIN modelos_vehiculo modv ON modv.id=v.modelo_id
 LEFT JOIN $vehiculosTabla iv ON iv.accidente_id=ip.accidente_id AND iv.vehiculo_id=ip.vehiculo_id
 WHERE ip.accidente_id=? ORDER BY COALESCE(iv.orden_participacion,''),ip.id");$s->execute([$id]);$people=$s->fetchAll(PDO::FETCH_ASSOC);
$s=$pdo->prepare("SELECT iv.vehiculo_id,iv.orden_participacion,v.placa,mv.nombre marca,modv.nombre modelo,v.color,tv.nombre tipo
 FROM $vehiculosTabla iv JOIN vehiculos v ON v.id=iv.vehiculo_id
 LEFT JOIN tipos_vehiculo tv ON tv.id=v.tipo_id LEFT JOIN marcas_vehiculo mv ON mv.id=v.marca_id LEFT JOIN modelos_vehiculo modv ON modv.id=v.modelo_id WHERE iv.accidente_id=? ORDER BY iv.orden_participacion");$s->execute([$id]);$vehicles=$s->fetchAll(PDO::FETCH_ASSOC);
$icon=static function(string $name): string {
    $icons=['place'=>'📍','date'=>'📅','station'=>'🏢','people'=>'👥','car'=>'🚘','court'=>'⚖️','badge'=>'👤','detective'=>'🕵️','folder'=>'📁','file'=>'📄'];
    return '<span class="case-emoji" aria-hidden="true">'.($icons[$name]??'📄').'</span>';
};
$vehicleIcon=static function(string $type): string {
    $type=mb_strtolower($type, 'UTF-8');
    if (str_contains($type,'trimoto') || str_contains($type,'mototaxi')) return '🛺';
    if (str_contains($type,'moto')) return '🏍️';
    if (str_contains($type,'camion') || str_contains($type,'remolc')) return '🚚';
    if (str_contains($type,'bus') || str_contains($type,'ómnibus') || str_contains($type,'omnibus')) return '🚌';
    if (str_contains($type,'bicic')) return '🚲';
    return '🚘';
};
// Agrupar por el vínculo real con el vehículo; los peatones conservan su fila propia.
$peopleByVehicle=[];
$unlinkedPeople=[];
$vehicleIds=array_fill_keys(array_map(static fn(array $v): int => (int)$v['vehiculo_id'], $vehicles), true);
foreach ($people as $person) {
    $vehicleId=(int)($person['vehiculo_id']??0);
    if ($vehicleId>0 && isset($vehicleIds[$vehicleId]) && !str_contains(mb_strtolower($person['rol']), 'peat')) {
        $peopleByVehicle[$vehicleId][]=$person;
    } else {
        $unlinkedPeople[]=$person;
    }
}
$renderPerson=static function(array $person, ?array $vehicle=null) use ($h, $vehicleIcon): void {
    $role=mb_strtolower($person['rol']);
    $injury=mb_strtolower($person['lesion']);
    $class=str_contains($injury,'fallec')?'is-deceased':(preg_match('/herid|lesion/iu',$injury)?'is-injured':'');
    $symbol=str_contains($role,'peat')?'🚶':($vehicle!==null?$vehicleIcon((string)($vehicle['tipo']??'')):'👤');
    $details=[$person['rol']];
    if ($vehicle!==null) {
        $details=array_merge($details,[$vehicle['orden_participacion']??'', $vehicle['tipo']??'', trim(($vehicle['marca']??'').' '.($vehicle['modelo']??'')), $vehicle['placa']??'']);
    }
    $details=implode(' · ',array_filter($details,static fn($value): bool => trim((string)$value)!==''));
    echo '<div class="case-card-person"><span class="case-person-icon" aria-hidden="true">'.$symbol.'</span><div><strong class="'.$class.'">'.$h(trim($person['nombres'].' '.$person['apellido_paterno'].' '.$person['apellido_materno'])).'</strong><small>'.$h($details).'</small></div></div>';
};
$districtColors=['ancón'=>326,'ancon'=>326,'carabayllo'=>266,'comas'=>220,'independencia'=>190,'los olivos'=>158,'puente piedra'=>42,'san martín de porres'=>18,'san martin de porres'=>18,'santa rosa'=>350,'santa rosa de quives'=>286];
$cardHue=$districtColors[mb_strtolower(trim((string)($a['distrito']??'')), 'UTF-8')]??220;
$state=trim((string)$a['estado'])?:'Pendiente';
?>
<article class="case-detail-card" style="--district-hue:<?= (int)$cardHue ?>">
 <header class="case-card-head">
   <div class="case-card-identifiers"><span class="case-chip case-chip-sidpol" title="SIDPOL"><?= $h($a['registro_sidpol']?:'Sin número') ?></span><?php if($a['nro_informe_policial']): ?><span class="case-chip" title="Número de informe"><?= $h($a['nro_informe_policial']) ?></span><?php endif ?><?php if($a['folder']): ?><span class="case-chip case-chip-folder"><?= $icon('folder') ?> <?= $h($a['folder']) ?></span><?php endif ?><span class="case-chip case-status-<?= $h(mb_strtolower(str_replace(' ','-', $state))) ?>"><?= $h($state) ?></span></div>
   <?php if (!empty($caseCardBackUrl)): ?>
   <a class="case-modal-close" href="<?= $h($caseCardBackUrl) ?>" aria-label="Volver al listado">×</a>
   <?php else: ?>
   <button type="button" class="case-modal-close" data-case-modal-close aria-label="Cerrar">×</button>
   <?php endif ?>
 </header>
 <div class="case-card-badges"><?php if($a['tipo_registro']): ?><span class="case-chip case-chip-type <?= $a['tipo_registro']==='Intervencion'?'is-intervention':'is-folder' ?>"><?= $h($a['tipo_registro']==='Intervencion'?'Intervención':$a['tipo_registro']) ?></span><?php endif ?></div>
 <?php if ($archiveTransfer): ?>
 <section class="case-card-section case-card-archive"><h3><?= $icon('file') ?> REMISIÓN A ARCHIVO</h3>
  <div class="case-card-person"><span class="case-person-icon"><?= $icon('file') ?></span><div><span class="case-card-label">INFORME N°</span><strong><?= $h($archiveTransfer['informe_remision']?:'No registrado') ?></strong></div></div>
  <div class="case-card-person"><span class="case-person-icon"><?= $icon('file') ?></span><div><span class="case-card-label">OFICIO N°</span><strong><?= $h($archiveTransfer['oficio_remision']?:'No registrado') ?></strong></div></div>
 </section>
 <?php endif; ?>
 <?php if($a['eliminado_en']): ?><p class="case-card-deleted">Expediente eliminado</p><?php endif ?>
 <h2 class="case-card-place"><?= $icon('place') ?><span><?= $h($a['lugar']) ?><?= $a['distrito']?' <span class="case-card-muted">· '. $h($a['distrito']).'</span>':'' ?></span></h2>
 <p class="case-card-modality"><span>MODALIDAD</span><?= $h(implode(', ',$modalidades)?:'Sin registrar') ?></p>
 <div class="case-card-facts">
  <div><span class="case-card-label"><?= $icon('date') ?> FECHA</span><strong><?= $h($a['fecha_accidente']?date('d/m/Y H:i',strtotime($a['fecha_accidente'])):'Sin registrar') ?></strong></div>
  <div><span class="case-card-label"><?= $icon('station') ?> COMISARÍA</span><strong><?= $h($a['comisaria']?:'Sin registrar') ?></strong></div>
 </div>
 <section class="case-card-section"><h3>INVOLUCRADOS</h3>
 <?php if(!$people && !$vehicles): ?><p class="case-card-empty">Sin participantes registrados</p><?php endif ?>
 <?php foreach($vehicles as $v):
     $vehiclePeople=$peopleByVehicle[(int)$v['vehiculo_id']]??[];
     usort($vehiclePeople,static fn(array $left,array $right): int => (int)!str_contains(mb_strtolower($left['rol']),'conduc') <=> (int)!str_contains(mb_strtolower($right['rol']),'conduc'));
 ?>
 <?php if($vehiclePeople): ?>
   <?php foreach($vehiclePeople as $person) $renderPerson($person,$v); ?>
 <?php else: ?>
   <div class="case-card-person case-card-vehicle"><span class="case-person-icon" aria-hidden="true"><?= $vehicleIcon((string)($v['tipo']??'')) ?></span><div><strong>Sin conductor registrado</strong><small><?= $h(implode(' · ',array_filter([$v['orden_participacion']??'', $v['tipo']??'', trim(($v['marca']??'').' '.($v['modelo']??'')), $v['placa']?:'Sin placa']))) ?></small></div></div>
 <?php endif ?>
 <?php endforeach ?>
 <?php foreach($unlinkedPeople as $person) $renderPerson($person); ?>
 </section>
 <section class="case-card-section case-card-prosecutor">
  <div class="case-card-person"><span class="case-person-icon"><?= $icon('court') ?></span><div><span class="case-card-label">FISCALÍA</span><strong><?= $h($a['fiscalia']?:'Sin registrar') ?></strong></div></div>
  <div class="case-card-person"><span class="case-person-icon"><?= $icon('badge') ?></span><div><span class="case-card-label">FISCAL A CARGO</span><strong><?= $h($a['fiscal']?:'Sin registrar') ?></strong></div></div>
 </section>
 <section class="case-card-section case-card-owner"><h3><?= $icon('detective') ?> ENCARGADO DEL EXPEDIENTE</h3>
  <div class="case-card-person"><span class="case-person-icon"><?= $icon('detective') ?></span><div><strong><?= $h(trim(($a['responsable_grado']??'').' '.($a['responsable']??''))?:'Pendiente de asignación') ?></strong><small><?= in_array($a['responsable_rol'],['secretaria','administracion'],true) ? 'Archivo · '. $h(\App\Support\Access::ROLES[$a['responsable_rol']]) : 'JEFE EMI responsable' ?></small></div></div>
 </section>

 <?php if ($archiveTransfer): ?>
 <section class="case-card-section"><h3>Archivo aceptado por</h3><strong><?= $h(trim(($archiveTransfer['aceptado_grado'] ?? '').' '.$archiveTransfer['aceptado_por'])) ?></strong></section>
 <?php endif; ?>
</article>
