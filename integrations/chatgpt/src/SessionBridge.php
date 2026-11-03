<?php
declare(strict_types=1);
namespace Uiat\Connector;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/** Uses UIAT's existing login. Passwords stay on UIAT; they never become tool inputs. */
final class SessionBridge
{
    public function __construct(private Users $users,private string $issuer,private \Closure $boot,private \Closure $verifyPassword,private \Closure $passwordBudget) {}
    public static function forProject(string $project,Users $users,string $issuer,\Closure $passwordBudget): self {
        return new self($users,$issuer,
            static function()use($project):void {
                if(!defined('UIAT_PWA_BOOTED'))define('UIAT_PWA_BOOTED',true);
                require_once $project.'/auth.php';
                \App\Support\Auth::requireLogin('/login.php');
            },
            static function(string $password): void {
                $current=$_SESSION['user']??[];
                $pdo=\App\Database\Database::connection();
                $identifier=trim((string)($current['email']??''));
                if ($identifier==='') {
                    $s=$pdo->prepare('SELECT cip FROM usuarios WHERE id=? AND activo=1');$s->execute([(int)($current['id']??0)]);
                    $identifier=trim((string)$s->fetchColumn());
                }
                if ($identifier==='')throw new RuntimeException('Vuelve a iniciar sesión en UIAT.');
                $verified=(new \App\Services\LoginService($pdo))->authenticate($identifier,$password);
                if ((int)$verified['id']!==(int)$current['id'] || (int)$verified['must_change_password']!==0
                    || (int)$verified['auth_version']!==(int)$current['auth_version'])throw new RuntimeException('Sesión no autorizada.');
                \App\Services\LoginService::beginSession($verified);
            },$passwordBudget);
    }
    public function start(): void {($this->boot)();}
    public function offer(ServerRequestInterface $request,array &$session): ?Response {
        try {$this->users->fromSession($session);return null;}catch(RuntimeException $e) {}
        $current=$session['user']??[];$actor=$this->users->active((int)($current['id']??0));
        if (!$actor || !isset($current['auth_version']) || $actor->version!==(int)$current['auth_version'])throw new RuntimeException('Inicia sesión en UIAT.');
        $pending=array_filter($session['uiat_oauth_reauth']??[],fn($r)=>($r['expires']??0)>time());
        if(count($pending)>=5)throw new RuntimeException('Demasiadas solicitudes pendientes.');
        $ticket=bin2hex(random_bytes(24));$csrf=bin2hex(random_bytes(32));
        $pending[$ticket]=['query'=>$request->getQueryParams(),'csrf'=>$csrf,'user'=>$actor->id,'version'=>$actor->version,'expires'=>time()+300];
        $session['uiat_oauth_reauth']=$pending;
        $h=fn($s)=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $html='<!doctype html><html lang="es"><meta charset="utf-8"><title>Confirmar sesión UIAT</title><main><h1>Confirma tu sesión de UIAT NORTE</h1><p>Introduce la contraseña de tu sesión actual para autorizar la conexión con ChatGPT. La contraseña se verifica en UIAT y no se envía a ChatGPT.</p><form method="post" action="'.$h($this->issuer.'/reauth').'"><input type="hidden" name="ticket" value="'.$ticket.'"><input type="hidden" name="csrf" value="'.$csrf.'"><label>Contraseña <input type="password" name="password" required maxlength="1024" autocomplete="current-password"></label><button>Confirmar sesión</button></form></main></html>';
        return new Response(200,['Content-Type'=>'text/html; charset=utf-8','Cache-Control'=>'no-store','Referrer-Policy'=>'no-referrer','Content-Security-Policy'=>"default-src 'none'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'"],$html);
    }
    public function complete(array $form,array &$session): Response {
        $ticket=$form['ticket']??null;$csrf=$form['csrf']??null;$password=$form['password']??null;
        if(!is_string($ticket)||!is_string($csrf)||!is_string($password)||$password===''||strlen($password)>1024)throw new RuntimeException('Formulario inválido.');
        $r=$session['uiat_oauth_reauth'][$ticket]??null;$current=$session['user']??[];
        if(!$r||$r['expires']<=time()||$r['user']!==(int)($current['id']??0)||$r['version']!==(int)($current['auth_version']??-1)||!hash_equals($r['csrf'],$csrf))throw new RuntimeException('Formulario vencido.');
        unset($session['uiat_oauth_reauth'][$ticket]);
        // One attempt per nonce; admission rate limits are also required by the runtime.
        ($this->passwordBudget)($r['user']);
        ($this->verifyPassword)($password);
        $actor=$this->users->fromSession($session);
        if($actor->id!==$r['user'])throw new RuntimeException('La identidad cambió.');
        return new Response(303,['Location'=>$this->issuer.'/authorize?'.http_build_query($r['query']),'Cache-Control'=>'no-store','Referrer-Policy'=>'no-referrer']);
    }
}
