<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

use App\Support\Access;
use App\Support\WorkspacePage as Page;
use App\Services\ExpedienteAccessService;

if (!Access::admin()) {
    http_response_code(403);
    exit('Acceso exclusivo del administrador.');
}
$id = max(0, (int)($_GET['id'] ?? 0));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';
    try {
        Access::checkCsrf();
        $action = (string)($_POST['action'] ?? '');
        if (!in_array($action, ['reasignar', 'eliminar'], true)) throw new RuntimeException('Acción no válida.');
        $caseId = max(0, (int)($_POST['expediente_id'] ?? $id));
        $reason = trim((string)($_POST['motivo'] ?? ''));
        if ($action === 'reasignar' && $reason === '') $reason = 'asignado por el administrador';
        (new ExpedienteAccessService($pdo))->change($caseId, $action, (int)($_POST['usuario_id'] ?? 0), $reason);
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>true, 'message'=>'Responsable guardado.']);
            exit;
        }
        $query = $_GET;
        unset($query['id']);
        $query['ok'] = '1';
        header('Location: listado_general.php?'.http_build_query($query));
        exit;
    } catch (Throwable $e) {
        http_response_code(422);
        $error = $e->getMessage();
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>false, 'message'=>$error]);
            exit;
        }
    }
}
Page::start('Listado general');
Page::notice($error, true);
if (isset($_GET['ok'])) Page::notice('Cambio guardado correctamente.');
$h = static fn($v) => Page::escape($v);
$q = trim((string)($_GET['q'] ?? ''));
$district = trim((string)($_GET['distrito'] ?? ''));
$station = max(0, (int)($_GET['comisaria_id'] ?? 0));
$query = ['q'=>$q, 'distrito'=>$district, 'comisaria_id'=>$station];
if ($id) {
    echo '<p><a href="listado_general.php?'.$h(http_build_query($query)).'">← Volver al listado</a></p>';
    $caseCardBackUrl = 'listado_general.php?'.http_build_query($query);
    require __DIR__.'/app/Views/expedientes/card.php';
    if ($a && !$a['eliminado_en']) {
        $users = $pdo->query("SELECT id,nombre,grado FROM usuarios WHERE activo=1 AND rol='jefe_emi' ORDER BY nombre")->fetchAll();
        echo '<section><h2>Administrar expediente</h2><p><a href="accidente_vista_tabs.php?accidente_id='.$id.'">Abrir expediente completo</a></p>';
        echo '<form method="post">';
        Page::token();
        echo '<input type="hidden" name="action" value="reasignar"><label>A cargo de<select name="usuario_id" required><option value="">Seleccionar responsable</option>';
        foreach ($users as $user) echo '<option value="'.(int)$user['id'].'" '.((int)$a['responsable_id']===(int)$user['id']?'selected':'').'>'.$h(trim(($user['grado']??'').' '.$user['nombre'])).'</option>';
        echo '</select></label><label>Motivo de la asignación o cambio<input name="motivo" value="asignado por el administrador" required maxlength="2000"></label><button>Guardar responsable</button></form>';
        echo '<details><summary>Eliminar registro</summary><p>Se retirará del listado y se conservará en el historial. Esta acción requiere un motivo.</p><form method="post">';
        Page::token();
        echo '<input type="hidden" name="action" value="eliminar"><label>Motivo de eliminación<input name="motivo" required maxlength="2000"></label><button class="danger">Confirmar eliminación</button></form></details></section>';
    }
    Page::end();
    exit;
}
$stations = $pdo->query('SELECT id,nombre FROM comisarias ORDER BY nombre')->fetchAll();
$districts = $pdo->query("SELECT DISTINCT CONCAT(d.cod_dep,'-',d.cod_prov,'-',d.cod_dist) codigo,d.nombre FROM accidentes a JOIN ubigeo_distrito d ON d.cod_dep=a.cod_dep AND d.cod_prov=a.cod_prov AND d.cod_dist=a.cod_dist WHERE a.eliminado_en IS NULL ORDER BY d.nombre")->fetchAll();
?>
<section>
<form method="get">
<label>Nombre, apellidos, vehículo o SIDPOL<input name="q" value="<?= $h($q) ?>" placeholder="Persona, placa, marca o modelo"></label>
<label>Distrito<select name="distrito"><option value="">Todos los distritos</option>
<?php foreach ($districts as $d): ?><option value="<?= $h($d['codigo']) ?>" <?= $district===$d['codigo']?'selected':'' ?>><?= $h($d['nombre']) ?></option><?php endforeach ?>
</select></label>
<label>Comisaría<select name="comisaria_id"><option value="0">Todas las comisarías</option>
<?php foreach ($stations as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $station===(int)$c['id']?'selected':'' ?>><?= $h($c['nombre']) ?></option><?php endforeach ?>
</select></label><button>Buscar</button><a href="listado_general.php">Limpiar filtros</a>
</form>
</section>
<?php
$where = 'a.eliminado_en IS NULL';
$params = [];
if ($q !== '') {
    $where .= " AND (a.registro_sidpol LIKE ? OR a.lugar LIKE ? OR u.nombre LIKE ?
    OR EXISTS(SELECT 1 FROM involucrados_personas ip JOIN personas p ON p.id=ip.persona_id WHERE ip.accidente_id=a.id AND CONCAT_WS(' ',p.nombres,p.apellido_paterno,p.apellido_materno) LIKE ?)
    OR EXISTS(SELECT 1 FROM involucrados_vehiculos iv JOIN vehiculos v ON v.id=iv.vehiculo_id LEFT JOIN marcas_vehiculo mv ON mv.id=v.marca_id LEFT JOIN modelos_vehiculo mo ON mo.id=v.modelo_id WHERE iv.accidente_id=a.id AND CONCAT_WS(' ',v.placa,mv.nombre,mo.nombre) LIKE ?))";
    $params = array_fill(0, 5, '%'.$q.'%');
}
if ($district !== '') {
    $where .= " AND CONCAT(a.cod_dep,'-',a.cod_prov,'-',a.cod_dist)=?";
    $params[] = $district;
}
if ($station) {
    $where .= ' AND a.comisaria_id=?';
    $params[] = $station;
}
$from = "FROM accidentes a LEFT JOIN usuarios u ON u.id=a.responsable_id LEFT JOIN comisarias c ON c.id=a.comisaria_id LEFT JOIN ubigeo_distrito d ON d.cod_dep=a.cod_dep AND d.cod_prov=a.cod_prov AND d.cod_dist=a.cod_dist WHERE $where";
$s=$pdo->prepare("SELECT COUNT(*) $from"); $s->execute($params); $total=(int)$s->fetchColumn();
$page=min(max(1,(int)($_GET['pagina']??1)),max(1,(int)ceil($total/50)));
$offset=($page-1)*50;
$s=$pdo->prepare("SELECT a.id,a.registro_sidpol,a.lugar,a.estado,a.responsable_id,c.nombre comisaria,d.nombre distrito,u.nombre responsable,u.grado,
(SELECT GROUP_CONCAT(m.nombre ORDER BY m.nombre SEPARATOR ', ') FROM accidente_modalidad am JOIN modalidad_accidente m ON m.id=am.modalidad_id WHERE am.accidente_id=a.id) modalidad
$from ORDER BY a.id DESC LIMIT 50 OFFSET $offset");
$s->execute($params);$rows=$s->fetchAll();
$users = $pdo->query("SELECT id,nombre,grado FROM usuarios WHERE activo=1 AND rol='jefe_emi' ORDER BY nombre")->fetchAll();
$eligibleIds = array_map('intval', array_column($users, 'id'));
echo '<section><p>'.$total.' registros · Todos los responsables, incluidos los expedientes sin asignar.</p><div class="table-scroll"><table><thead><tr><th>SIDPOL</th><th>Lugar del accidente</th><th>Distrito</th><th>Comisaría</th><th>A cargo de</th><th>Estado</th><th>Tipo de accidente</th><th>Detalle</th></tr></thead><tbody>';
foreach ($rows as $row) {
    $href='listado_general.php?'.http_build_query($query+['id'=>(int)$row['id']]);
    echo '<tr><td>'.$h($row['registro_sidpol']?:'Sin SIDPOL').'</td><td>'.$h($row['lugar']).'</td><td>'.$h($row['distrito']?:'Sin registrar').'</td><td>'.$h($row['comisaria']?:'Sin registrar').'</td><td>';
    echo '<form method="post" class="js-responsible-form"><input type="hidden" name="action" value="reasignar"><input type="hidden" name="expediente_id" value="'.(int)$row['id'].'">';
    Page::token();
    echo '<select name="usuario_id" required aria-label="A cargo del expediente '.$h($row['registro_sidpol']?:$row['id']).'" style="min-width:220px"><option value="">Seleccionar JEFE EMI</option>';
    if ($row['responsable_id'] && !in_array((int)$row['responsable_id'], $eligibleIds, true)) {
        echo '<option value="" selected disabled>'.$h(trim(($row['grado']??'').' '.($row['responsable']??''))?:'Responsable no disponible').' (actual)</option>';
    }
    foreach ($users as $user) {
        echo '<option value="'.(int)$user['id'].'" '.((int)$row['responsable_id']===(int)$user['id']?'selected':'').'>'.$h(trim(($user['grado']??'').' '.$user['nombre'])).'</option>';
    }
    echo '</select><button>Guardar responsable</button><span role="status" class="responsible-status" style="display:block;height:3em;overflow:auto;font-size:12px"></span></form></td><td>'.$h($row['estado']).'</td><td>'.$h($row['modalidad']?:'Sin registrar').'</td><td><a href="'.$h($href).'">Ver resumen y gestionar</a></td></tr>';
}
if (!$rows) echo '<tr><td colspan="8">No hay registros con estos filtros.</td></tr>';
echo '</tbody></table></div><nav class="actions" aria-label="Paginación">';
if($page>1) echo '<a href="?'.$h(http_build_query($query+['pagina'=>$page-1])).'">Anterior</a>';
echo '<span>Página '.$page.' de '.max(1,(int)ceil($total/50)).'</span>';
if($page*50<$total) echo '<a href="?'.$h(http_build_query($query+['pagina'=>$page+1])).'">Siguiente</a>';
echo '</nav></section>';
echo '<script src="assets/js/listado-general.js?v=1" defer></script>';
Page::end();
