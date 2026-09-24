<?php
require __DIR__.'/auth.php';require_login();require __DIR__.'/db.php';
use App\Support\Access;
use App\Support\WorkspacePage as Page;
function responsible_label(?string $name, ?string $grade = null): string {
    return trim(trim((string)$grade).' '.trim((string)$name)) ?: 'Sin asignar';
}

$id=(int)($_GET['id']??$_POST['id']??0);$error='';$ok='';
if($_SERVER['REQUEST_METHOD']==='POST') { require __DIR__.'/expediente_estado.php'; exit; }
if($id && ($_GET['modal']??'')==='1') {
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__.'/app/Views/expedientes/card.php';
    exit;
}
Page::start('Buscador general');Page::notice($error,true);
if($id) {
    require __DIR__.'/app/Views/expedientes/card.php';
    Page::end();exit;
}
$q=trim((string)($_GET['q']??''));$oficio=trim((string)($_GET['oficio']??''));$filter=(string)($_GET['filtro']??'todos');
echo '<section><form method="get"><label>Buscar por SIDPOL, lugar, responsable, nombres, apellidos o placa<input name="q" value="'.Page::escape($q).'"></label><label>Número de oficio<input name="oficio" value="'.Page::escape($oficio).'" placeholder="Ej.: 28 o 028-2025"></label><label>Mostrar<select name="filtro">';
$options=['todos'=>'Todos','mios'=>'Bajo mi responsabilidad','colaboracion'=>'Mis colaboraciones'];if(Access::admin())$options['eliminados']='Eliminados';
foreach($options as $k=>$v)echo '<option value="'.$k.'" '.($filter===$k?'selected':'').'>'.$v.'</option>';echo '</select></label><button>Buscar</button></form></section>';
if($q==='' && $oficio===''){Page::notice('Ingresa un dato o un número de oficio para buscar expedientes.');Page::end();exit;}
$where=$filter==='eliminados' && Access::admin()?'a.eliminado_en IS NOT NULL':'a.eliminado_en IS NULL';$params=[];
if($filter==='mios'){$where.=' AND a.responsable_id=?';$params[]=Access::id();}
if($filter==='colaboracion'){$where.=' AND EXISTS(SELECT 1 FROM expediente_colaboradores c WHERE c.accidente_id=a.id AND c.usuario_id=? AND c.revocado_en IS NULL)';$params[]=Access::id();}
if($q!==''){$where.=" AND (a.registro_sidpol LIKE ? OR a.lugar LIKE ? OR u.nombre LIKE ? OR EXISTS(SELECT 1 FROM involucrados_personas ip JOIN personas p ON p.id=ip.persona_id WHERE ip.accidente_id=a.id AND CONCAT_WS(' ',p.nombres,p.apellido_paterno,p.apellido_materno) LIKE ?) OR EXISTS(SELECT 1 FROM involucrados_vehiculos iv JOIN vehiculos v ON v.id=iv.vehiculo_id WHERE iv.accidente_id=a.id AND v.placa LIKE ?))";for($i=0;$i<5;$i++)$params[]='%'.$q.'%';}
if($oficio!==''){
 if(!preg_match('/^0*([0-9]+)(?:\s*[-\/]\s*([0-9]{4}))?$/D',$oficio,$match)){Page::notice('Ingresa el número de oficio, por ejemplo 28 o 028-2025.',true);Page::end();exit;}
 $where.=' AND EXISTS(SELECT 1 FROM oficios_activos o WHERE o.accidente_id=a.id AND o.numero=?';$params[]=(int)$match[1];if(isset($match[2])){$where.=' AND o.anio=?';$params[]=(int)$match[2];}$where.=')';
}
$page=max(1,(int)($_GET['pagina']??1));$offset=($page-1)*50;
$s=$pdo->prepare("SELECT a.*,u.nombre responsable,u.grado responsable_grado FROM accidentes a LEFT JOIN usuarios u ON u.id=a.responsable_id WHERE $where ORDER BY a.id DESC LIMIT 51 OFFSET $offset");$s->execute($params);$rows=$s->fetchAll();$more=count($rows)>50;$rows=array_slice($rows,0,50);
echo '<section><div class="table-scroll"><table><thead><tr><th>Expediente</th><th>Lugar</th><th>Responsable</th><th>Estado</th><th></th></tr></thead><tbody>';
foreach($rows as $row){$case=(int)$row['id'];$href='gestion_expedientes.php?id='.$case;echo '<tr><td><a class="js-case-modal-trigger" data-case-id="'.$case.'" href="'.$href.'">#'.$case.' · '.Page::escape($row['registro_sidpol']).'</a></td><td>'.Page::escape($row['lugar']).'</td><td>'.Page::escape(responsible_label($row['responsable'],$row['responsable_grado'])).'</td><td>'.Page::escape($row['estado']).'</td><td><a class="js-case-modal-trigger" data-case-id="'.$case.'" href="'.$href.'">Ver gestión</a></td></tr>';}
if(!$rows)echo '<tr><td colspan="5">No hay expedientes para esta búsqueda.</td></tr>';echo '</tbody></table></div><div class="actions">';
$query=['q'=>$q,'oficio'=>$oficio,'filtro'=>$filter];if($page>1)echo '<a href="?'.Page::escape(http_build_query($query+['pagina'=>$page-1])).'">Anterior</a>';if($more)echo '<a href="?'.Page::escape(http_build_query($query+['pagina'=>$page+1])).'">Siguiente</a>';echo '</div></section>';
?>
<div class="case-modal-backdrop" id="case-modal" hidden aria-hidden="true"><div class="case-modal-dialog" role="dialog" aria-modal="true" aria-label="Detalle del expediente" tabindex="-1"><div class="case-modal-loading" role="status">Cargando expediente…</div></div></div>
<script>
(()=>{
 const modal=document.getElementById('case-modal'),dialog=modal?.querySelector('.case-modal-dialog');if(!modal||!dialog)return;
 let previousFocus=null,requestController=null;
 const close=()=>{modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.body.style.overflow='';setTimeout(()=>{modal.hidden=true;dialog.innerHTML='<div class="case-modal-loading" role="status">Cargando expediente…</div>';},180);const url=new URL(location.href);url.searchParams.delete('id');url.searchParams.delete('modal');history.replaceState({},'',url);previousFocus?.focus();};
 const open=async(id,push=true)=>{requestController?.abort();requestController=new AbortController();previousFocus=document.activeElement;modal.hidden=false;modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';dialog.innerHTML='<div class="case-modal-loading" role="status">Cargando expediente…</div>';requestAnimationFrame(()=>modal.classList.add('is-open'));dialog.focus();if(push){const url=new URL(location.href);url.searchParams.set('id',id);history.pushState({caseModal:true},'',url);}
  try{const response=await fetch('gestion_expedientes.php?id='+encodeURIComponent(id)+'&modal=1',{headers:{'X-Requested-With':'XMLHttpRequest'},signal:requestController.signal});if(!response.ok)throw new Error();dialog.innerHTML=await response.text();}
  catch(error){if(error.name==='AbortError')return;dialog.innerHTML='<div class="case-modal-error">No se pudo cargar el expediente. <button type="button" data-case-modal-retry="'+id+'">Reintentar</button></div>';}
 };
 document.addEventListener('click',event=>{const link=event.target.closest('.js-case-modal-trigger');if(link){event.preventDefault();open(link.dataset.caseId);return;}if(event.target.closest('[data-case-modal-close]')||event.target===modal){close();return;}const retry=event.target.closest('[data-case-modal-retry]');if(retry)open(retry.dataset.caseModalRetry,false);});
 document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!modal.hidden)close();});
 window.addEventListener('popstate',()=>{const id=new URL(location.href).searchParams.get('id');if(id)open(id,false);else if(!modal.hidden)close();});
})();
</script>
<?php Page::end();
