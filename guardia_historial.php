<?php
require __DIR__.'/auth.php';require_login();require __DIR__.'/db.php';
use App\Support\Access;
use App\Support\WorkspacePage as Page;
if(Access::role()!=='guardia'){http_response_code(403);exit('Esta sección corresponde al comandante de guardia.');}
$district=trim((string)($_GET['distrito']??''));
$station=(int)($_GET['comisaria_id']??0);
$where='c.creado_por=? AND c.eliminado_en IS NULL';
$params=[Access::id()];
if($district!==''){$where.=" AND CONCAT(COALESCE(a.cod_dep,c.cod_dep),'-',COALESCE(a.cod_prov,c.cod_prov),'-',COALESCE(a.cod_dist,c.cod_dist))=?";$params[]=$district;}
if($station>0){$where.=' AND a.comisaria_id=?';$params[]=$station;}
$from="FROM comunicaciones_guardia c LEFT JOIN accidentes a ON a.id=c.accidente_id LEFT JOIN comisarias co ON co.id=a.comisaria_id LEFT JOIN ubigeo_distrito d ON BINARY d.cod_dep=BINARY COALESCE(a.cod_dep,c.cod_dep) AND BINARY d.cod_prov=BINARY COALESCE(a.cod_prov,c.cod_prov) AND BINARY d.cod_dist=BINARY COALESCE(a.cod_dist,c.cod_dist) LEFT JOIN usuarios u ON u.id=c.jefe_id";
$st=$pdo->prepare("SELECT COUNT(*) $from WHERE $where");$st->execute($params);$total=(int)$st->fetchColumn();
$page=min(max(1,(int)($_GET['pagina']??1)),max(1,(int)ceil($total/50)));$offset=($page-1)*50;
$st=$pdo->prepare("SELECT c.*,a.registro_sidpol,a.eliminado_en accidente_eliminado,a.tipo_registro,a.estado,a.fecha_accidente,a.nro_informe_policial,a.folder,co.nombre comisaria,d.nombre distrito,u.nombre jefe,u.grado,f.nombre fiscalia,TRIM(CONCAT_WS(' ',fi.nombres,fi.apellido_paterno,fi.apellido_materno)) fiscal,DATE_ADD(c.registrado_en,INTERVAL 12 HOUR) limite,NOW()<DATE_ADD(c.registrado_en,INTERVAL 12 HOUR) vigente $from LEFT JOIN fiscalia f ON f.id=a.fiscalia_id LEFT JOIN fiscales fi ON fi.id=a.fiscal_id WHERE $where ORDER BY c.registrado_en DESC,c.id DESC LIMIT 50 OFFSET $offset");$st->execute($params);$rows=$st->fetchAll();
$caseIds=array_values(array_unique(array_filter(array_map(static fn($r)=>(int)$r['accidente_id'],$rows))));
$modalities=[];$people=[];
if($caseIds){
    $marks=implode(',',array_fill(0,count($caseIds),'?'));
    $st=$pdo->prepare("SELECT am.accidente_id,m.nombre FROM accidente_modalidad_activos am JOIN modalidad_accidente m ON m.id=am.modalidad_id WHERE am.accidente_id IN ($marks) ORDER BY m.nombre");$st->execute($caseIds);
    foreach($st as $item)$modalities[(int)$item['accidente_id']][]=$item['nombre'];
    $st=$pdo->prepare("SELECT ip.accidente_id,CONCAT_WS(' ',p.nombres,p.apellido_paterno,p.apellido_materno) nombre,COALESCE(pp.Nombre,'Participante') rol FROM involucrados_personas_activos ip JOIN personas p ON p.id=ip.persona_id LEFT JOIN participacion_persona pp ON pp.Id=ip.rol_id WHERE ip.accidente_id IN ($marks) ORDER BY ip.id");$st->execute($caseIds);
    foreach($st as $item)$people[(int)$item['accidente_id']][]=$item;
}
$st=$pdo->prepare("SELECT DISTINCT CONCAT(d.cod_dep,'-',d.cod_prov,'-',d.cod_dist) codigo,d.nombre $from WHERE c.creado_por=? AND c.eliminado_en IS NULL AND d.cod_dist IS NOT NULL ORDER BY d.nombre");$st->execute([Access::id()]);$districts=$st->fetchAll();
$st=$pdo->prepare("SELECT DISTINCT co.id,co.nombre $from WHERE c.creado_por=? AND c.eliminado_en IS NULL AND co.id IS NOT NULL ORDER BY co.nombre");$st->execute([Access::id()]);$stations=$st->fetchAll();
$h=static fn($v)=>Page::escape($v);
Page::start('Mis registros de guardia','assets/css/guardia-cards.css');
echo '<p>Conservas aquí todos tus ingresos, incluidos los derivados al JEFE EMI. Las tarjetas muestran el resumen del accidente; el contenido de investigación corresponde al JEFE EMI.</p><a class="button" href="accidente_nuevo.php">+ Registrar accidente</a>';
?>
<section><form method="get"><label>Distrito<select name="distrito"><option value="">Todos</option>
<?php foreach($districts as $d): ?><option value="<?= $h($d['codigo']) ?>" <?= $district===$d['codigo']?'selected':'' ?>><?= $h($d['nombre']) ?></option><?php endforeach ?>
</select></label><label>Comisaría<select name="comisaria_id"><option value="0">Todas</option>
<?php foreach($stations as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $station===(int)$c['id']?'selected':'' ?>><?= $h($c['nombre']) ?></option><?php endforeach ?>
</select></label><button>Filtrar</button><a href="guardia_historial.php">Limpiar filtros</a></form></section>
<section><p><?= $total ?> registros propios</p><div class="guard-card-list" role="list">
<?php foreach($rows as $r):
$derived=!empty($r['jefe_id']);
$editable=$r['vigente']&&!$derived&&!$r['accidente_eliminado'];
$caseId=(int)$r['accidente_id'];$cardPeople=$people[$caseId]??[];
?>
<article class="guard-card" role="listitem">
  <div class="guard-card-head"><span class="guard-sidpol"><?= $h($r['registro_sidpol']?:'Ingreso #'.$r['id']) ?></span><?php if($r['nro_informe_policial']): ?><span class="guard-report"><?= $h($r['nro_informe_policial']) ?></span><?php endif ?><?php if($r['folder']): ?><span class="guard-report">Folder <?= $h($r['folder']) ?></span><?php endif ?></div>
  <div class="guard-card-chips"><span class="guard-chip <?= ($r['estado']??'')==='Resuelto'?'guard-derived':'guard-pending' ?>"><?= $h($r['estado']?:'Pendiente') ?></span><?php if($r['tipo_registro']): ?><span class="guard-chip guard-type"><?= $h($r['tipo_registro']) ?></span><?php endif ?></div>
  <h2>📍 <?= $h($r['lugar']?:'Lugar por determinar') ?> <span>– <?= $h($r['distrito']?:'Distrito por determinar') ?></span></h2>
  <?php if(!empty($modalities[$caseId])): ?><p class="guard-modality"><small>MODALIDAD</small> <?= $h(implode(', ',$modalities[$caseId])) ?></p><?php endif ?>
  <div class="guard-card-meta"><div><small>📅 FECHA</small><strong><?= $h($r['fecha_accidente']?date('d/m/Y H:i',strtotime($r['fecha_accidente'])):'—') ?></strong></div><div><small>🏢 COMISARÍA</small><strong><?= $h($r['comisaria']?:'Por determinar') ?></strong></div></div>
  <div class="guard-card-section"><small>INVOLUCRADOS</small><?php if($cardPeople): foreach(array_slice($cardPeople,0,3) as $person): ?><p>👤 <strong><?= $h($person['nombre']) ?></strong><br><span><?= $h($person['rol']) ?></span></p><?php endforeach; if(count($cardPeople)>3): ?><p>+<?= count($cardPeople)-3 ?> personas más</p><?php endif; else: ?><p>Sin personas registradas</p><?php endif ?></div>
  <div class="guard-card-section"><p>⚖️ <small>FISCALÍA</small><br><strong><?= $h($r['fiscalia']?:'Por determinar') ?></strong></p><p>👤 <small>FISCAL A CARGO</small><br><strong><?= $h($r['fiscal']?:'Por determinar') ?></strong></p></div>
  <div class="guard-card-footer"><strong><?= $derived?$h(trim(($r['grado']??'').' '.$r['jefe'])):'Aún sin JEFE EMI' ?></strong><span><?= $derived?'Asignado: '.$h($r['asignado_en']):'Edición hasta: '.$h($r['limite']) ?></span><?php if($editable && $caseId): ?><a class="button" href="guardia_registro.php?accidente_id=<?= $caseId ?>">Continuar registro</a><?php endif ?></div>
</article>
<?php endforeach ?>
<?php if(!$rows): ?><p>No hay registros con estos filtros.</p><?php endif ?>
</div><nav class="actions">
<?php $query=['distrito'=>$district,'comisaria_id'=>$station];if($page>1)echo '<a href="?'.$h(http_build_query($query+['pagina'=>$page-1])).'">Anterior</a>'; ?>
<span>Página <?= $page ?> de <?= max(1,(int)ceil($total/50)) ?></span>
<?php if($page*50<$total)echo '<a href="?'.$h(http_build_query($query+['pagina'=>$page+1])).'">Siguiente</a>'; ?>
</nav></section>
<?php Page::end();
