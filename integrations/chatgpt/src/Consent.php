<?php
declare(strict_types=1);
namespace Uiat\Connector;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/** Explicit browser consent bound to a recently authenticated session and a one-use CSRF nonce. */
final class Consent
{
    public function __construct(private OAuthCore $core,private Users $users,private string $authorizeUri) {}
    public function begin(ServerRequestInterface $request,array &$session): array {
        $this->core->validateAuthorization($request);
        $actor=$this->users->fromSession($session);
        $pending=$session['uiat_oauth_pending']??[];
        $pending=array_filter($pending,fn($r)=>is_array($r)&&($r['expires']??0)>time());
        if (count($pending)>=5)throw new RuntimeException('Demasiadas autorizaciones pendientes.');
        $ticket=bin2hex(random_bytes(24));$csrf=bin2hex(random_bytes(32));
        $pending[$ticket]=['query'=>$request->getQueryParams(),'csrf'=>$csrf,'id'=>$actor->id,'version'=>$actor->version,'expires'=>time()+300];
        $session['uiat_oauth_pending']=$pending;
        $offline=str_contains(' '.($request->getQueryParams()['scope']??'').' ',' offline_access ');
        return ['ticket'=>$ticket,'csrf'=>$csrf,'scope'=>'uiat:read',
            'descripcion'=>'Permitir que ChatGPT reciba código, fecha y estado del expediente; nombres y roles de participantes; tipos y estados de diligencias; e indicadores básicos de campos vacíos. Al preparar un oficio, también podrá recibir los datos necesarios de la persona o vehículo seleccionados: identificación, fecha de nacimiento y datos de filiación cuando la plantilla los requiera; características y propietario del vehículo; comisaría, fiscalía, referencia y número pericial o de informe. Los datos de defunción se usarán solo para el oficio RENIEC solicitado. Se podrá generar un Word temporal después de revisar y confirmar su contenido. El número de oficio debe proporcionarlo el usuario; no se registra ni envía automáticamente. Solo en expedientes autorizados. No se permite modificar registros.'.($offline?' Se solicita renovación automática de la conexión por un máximo de siete días, revocable.':' La autorización vence a los quince minutos y no se renueva automáticamente.')];
    }
    public function complete(array $form,array &$session): ResponseInterface {
        $actor=$this->users->fromSession($session);
        $ticket=$form['ticket']??null;$csrf=$form['csrf']??null;$decision=$form['decision']??null;
        if (!is_string($ticket)||!is_string($csrf)||!in_array($decision,['autorizar','denegar'],true))throw new RuntimeException('Formulario no válido.');
        $r=$session['uiat_oauth_pending'][$ticket]??null;
        if (!is_array($r)||$r['expires']<=time()||$r['id']!==$actor->id||$r['version']!==$actor->version||!hash_equals($r['csrf'],$csrf))throw new RuntimeException('Autorización vencida o no válida.');
        unset($session['uiat_oauth_pending'][$ticket]);
        $request=(new ServerRequest('GET',$this->authorizeUri))->withQueryParams($r['query']);
        return $this->core->authorize($request,$actor,$decision==='autorizar');
    }
}
