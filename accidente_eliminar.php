<?php
require __DIR__.'/auth.php';require_login();require __DIR__.'/db.php';
if(!\App\Support\Access::admin()){http_response_code(403);exit('Solo el administrador puede eliminar expedientes.');}
$id=(int)($_GET['id']??$_POST['id']??0);
header('Location: gestion_expedientes.php?id='.$id);exit;
