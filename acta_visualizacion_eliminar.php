<?php
require __DIR__.'/auth.php';require_login();require __DIR__.'/db.php';
use App\Repositories\ActaVisualizacionRepository;
$repo=new ActaVisualizacionRepository($pdo);$id=(int)($_GET['id']??$_POST['id']??0);$embed=(int)($_GET['embed']??$_POST['embed']??0)===1;$row=$repo->find($id);if(!$row){http_response_code(404);exit('Acta no encontrada');}
if($_SERVER['REQUEST_METHOD']==='POST'){$repo->delete($id);if($embed){echo '<script>parent.postMessage({type:"acta.deleted"},"*")</script>';exit;}header('Location: accidente_vista_tabs.php?accidente_id='.$row['accidente_id'].'&tab=documentos');exit;}
?><!doctype html><meta charset="utf-8"><body style="font:14px system-ui;padding:25px"><h1>Eliminar acta de visualizacion #<?=$id?></h1><p>Esta accion eliminara tambien sus discos, archivos y participantes seleccionados.</p><form method="post"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="embed" value="<?=$embed?1:0?>"><button>Eliminar</button></form></body>
