<?php
require __DIR__.'/auth.php';
require __DIR__.'/db.php';
use App\Services\LoginService;
use App\Support\Access;
$pending=LoginService::pendingSetup();
if($pending===null){header('Location: login.php');exit;}
header('Cache-Control: no-store');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        Access::checkCsrf();
        $user=(new LoginService($pdo))->completeSetup($pending,(string)($_POST['password']??''),(string)($_POST['confirmation']??''));
        LoginService::beginSession($user);
        header('Location: '.\App\Support\Auth::postLoginDestination());exit;
    }catch(Throwable $e){$error=$e->getMessage();}
}
function password_h(string $value):string{return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Crea tu contraseña · DEPIAT</title><link rel="stylesheet" href="assets/css/gestion.css"></head>
<body><main class="gestion" style="max-width:650px;padding:24px;margin:5vh auto"><header><p class="eyebrow">DEPIAT · PRIMER ACCESO</p><h1>Crea tu nueva contraseña</h1><p>Antes de ingresar al sistema debes reemplazar tu contraseña inicial.</p></header><section>
<?php if($error!==''):?><p class="notice error" role="alert"><?=password_h($error)?></p><?php endif;?>
<p id="rules">Usa al menos 10 caracteres, incluyendo una mayúscula, una minúscula, un número y un carácter especial, por ejemplo ! @ # %. Debe ser diferente de tu CIP.</p>
<form method="post"><input type="hidden" name="_csrf" value="<?=password_h(Access::csrf())?>"><label>Nueva contraseña<input type="password" name="password" required minlength="10" autocomplete="new-password" aria-describedby="rules"></label><label>Repite la nueva contraseña<input type="password" name="confirmation" required minlength="10" autocomplete="new-password"></label><button>Guardar contraseña e ingresar</button></form><a href="logout.php">Cancelar y cerrar sesión</a>
</section></main></body></html>
