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
$st=$pdo->prepare("SELECT c.*,a.registro_sidpol,a.eliminado_en accidente_eliminado,co.nombre comisaria,d.nombre distrito,u.nombre jefe,u.grado,DATE_ADD(c.registrado_en,INTERVAL 12 HOUR) limite,NOW()<DATE_ADD(c.registrado_en,INTERVAL 12 HOUR) vigente $from WHERE $where ORDER BY c.registrado_en DESC,c.id DESC LIMIT 50 OFFSET $offset");$st->execute($params);$rows=$st->fetchAll();
$st=$pdo->prepare("SELECT DISTINCT CONCAT(d.cod_dep,'-',d.cod_prov,'-',d.cod_dist) codigo,d.nombre $from WHERE c.creado_por=? AND c.eliminado_en IS NULL AND d.cod_dist IS NOT NULL ORDER BY d.nombre");$st->execute([Access::id()]);$districts=$st->fetchAll();
$st=$pdo->prepare("SELECT DISTINCT co.id,co.nombre $from WHERE c.creado_por=? AND c.eliminado_en IS NULL AND co.id IS NOT NULL ORDER BY co.nombre");$st->execute([Access::id()]);$stations=$st->fetchAll();
$h=static fn($v)=>Page::escape($v);
Page::start('Mis registros de guardia');
echo '<p>Conservas aquí todos tus ingresos, incluidos los derivados al JEFE EMI. El plazo de 12 horas se cuenta desde el registro original.</p><a class="button" href="accidente_nuevo.php">+ Registrar accidente</a>';
?>
<section><form method="get"><label>Distrito<select name="distrito"><option value="">Todos</option>
<?php foreach($districts as $d): ?><option value="<?= $h($d['codigo']) ?>" <?= $district===$d['codigo']?'selected':'' ?>><?= $h($d['nombre']) ?></option><?php endforeach ?>
</select></label><label>Comisaría<select name="comisaria_id"><option value="0">Todas</option>
<?php foreach($stations as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $station===(int)$c['id']?'selected':'' ?>><?= $h($c['nombre']) ?></option><?php endforeach ?>
</select></label><button>Filtrar</button><a href="guardia_historial.php">Limpiar filtros</a></form></section>
<section><p><?= $total ?> registros propios</p><div class="table-scroll"><table>
<thead><tr><th>Ingreso / SIDPOL</th><th>Lugar</th><th>Distrito</th><th>Comisaría</th><th>Resolución de guardia</th><th>Plazo de edición</th><th>Detalle</th></tr></thead><tbody>
<?php foreach($rows as $r):
$derived=!empty($r['jefe_id']);
$editable=$r['vigente']&&!$derived&&!$r['accidente_eliminado'];
$url=$r['accidente_id']?($editable?'guardia_registro.php?accidente_id=':'gestion_expedientes.php?id=').(int)$r['accidente_id']:'guardia.php?id='.(int)$r['id'];
?>
<tr><td>#<?= (int)$r['id'] ?> · <?= $h($r['registro_sidpol']?:'Sin SIDPOL') ?><br><?= $h($r['registrado_en']) ?></td>
<td><?= $h($r['lugar']) ?></td><td><?= $h($r['distrito']?:'Por determinar') ?></td><td><?= $h($r['comisaria']?:'Por determinar') ?></td>
<td><strong><?= $derived?'Derivado a JEFE EMI':'Pendiente de derivación' ?></strong><?php if($derived): ?><br><?= $h(trim(($r['grado']??'').' '.$r['jefe'])) ?><br><?= $h($r['asignado_en']) ?><?php endif ?></td>
<td><?= $editable?'Puede completar hasta:':'Solo consulta · límite:' ?><br><?= $h($r['limite']) ?></td><td><a href="<?= $h($url) ?>"><?= $editable?'Continuar registro':'Ver registro' ?></a></td></tr>
<?php endforeach ?>
<?php if(!$rows): ?><tr><td colspan="7">No hay registros con estos filtros.</td></tr><?php endif ?>
</tbody></table></div><nav class="actions">
<?php $query=['distrito'=>$district,'comisaria_id'=>$station];if($page>1)echo '<a href="?'.$h(http_build_query($query+['pagina'=>$page-1])).'">Anterior</a>'; ?>
<span>Página <?= $page ?> de <?= max(1,(int)ceil($total/50)) ?></span>
<?php if($page*50<$total)echo '<a href="?'.$h(http_build_query($query+['pagina'=>$page+1])).'">Siguiente</a>'; ?>
</nav></section>
<?php Page::end();
