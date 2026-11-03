<?php
declare(strict_types=1);
namespace Uiat\Connector;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/** Routes for a separately configured connector. Factory/router remain disabled without explicit private config. */
final class HttpKernel
{
    public function __construct(private OAuthCore $oauth,private Consent $consent,private McpKernel $mcp,private string $issuer,private string $resource,private \Closure $startSession,private Audit $linkAudit,private ?SessionBridge $bridge=null,private ?CameraDrafts $drafts=null) {}
    private function attempt(string $operation): void {$this->linkAudit->write(bin2hex(random_bytes(16)),null,$operation,202);}
    private function tracked(ResponseInterface $response,string $operation,?int $user=null): ResponseInterface {
        try {$this->linkAudit->write(bin2hex(random_bytes(16)),$user,$operation,$response->getStatusCode());return $response;}
        catch(Throwable $e){return $this->json(503,['error'=>'Servicio no disponible.']);}
    }
    private function json(int $status,array $data): Response {
        return new Response($status,['Content-Type'=>'application/json','Cache-Control'=>'no-store','Referrer-Policy'=>'no-referrer','X-Content-Type-Options'=>'nosniff'],json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    public function handle(ServerRequestInterface $request): ResponseInterface {
        $path=$request->getUri()->getPath();$method=$request->getMethod();
        $issuerPath=(string)parse_url($this->issuer,PHP_URL_PATH);$resourcePath=(string)parse_url($this->resource,PHP_URL_PATH);
        $authorizationMetadata='/.well-known/oauth-authorization-server'.$issuerPath;
        $resourceMetadata='/.well-known/oauth-protected-resource'.$resourcePath;
        try {
            if ($request->getUri()->getScheme()!=='https')return $this->json(403,['error'=>'HTTPS requerido.']);
            if ($path===$authorizationMetadata && $method==='GET')return $this->json(200,$this->oauth->metadata());
            if ($path===$resourceMetadata && $method==='GET')return $this->json(200,['resource'=>$this->resource,'authorization_servers'=>[$this->issuer],'scopes_supported'=>['uiat:read']]);
            if ($path===$resourcePath)return $this->mcp->handle($request);
            if ($path===$issuerPath.'/borrador' && $this->drafts && $this->bridge) {
                if($method!=='GET')return $this->json(405,['error'=>'Solo GET.']);
                $this->attempt('borrador/descarga');$this->bridge->start();
                $q=$request->getQueryParams();$file=$q['archivo']??null;
                if(!is_string($file)||array_diff(array_keys($q),['archivo']))return $this->json(404,['error'=>'Borrador no disponible.']);
                try{$bytes=$this->drafts->download($file,$_SESSION);}
                catch(\PDOException $e){throw $e;}
                catch(Throwable $e){return $this->tracked($this->json(404,['error'=>'Borrador no disponible.']),'borrador/rechazo');}
                return $this->tracked(new Response(200,[
                    'Content-Type'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'Content-Disposition'=>'attachment; filename="Oficio-UIAT.docx"',
                    'Cache-Control'=>'no-store, private','Pragma'=>'no-cache','Referrer-Policy'=>'no-referrer','X-Content-Type-Options'=>'nosniff',
                    'Content-Length'=>(string)strlen($bytes)],$bytes),'borrador/descarga',(int)($_SESSION['user']['id']??0));
            }
            if ($path===$issuerPath.'/token') {
                if($method!=='POST')return $this->json(405,['error'=>'Solo POST.']);
                $this->attempt('oauth/token');
                $response=$this->oauth->exchange($request)->withHeader('Cache-Control','no-store')->withHeader('Pragma','no-cache');
                $issued=json_decode((string)$response->getBody(),true,512,JSON_THROW_ON_ERROR);
                $actor=$this->oauth->authenticate($request->withHeader('Authorization','Bearer '.$issued['access_token']));
                return $this->tracked($response,'oauth/token',$actor->id);
            }
            if ($path===$issuerPath.'/revoke') {
                if($method!=='POST')return $this->json(405,['error'=>'Solo POST.']);
                $this->attempt('oauth/revoke');$this->oauth->revoke($request);
                return $this->tracked($this->json(200,[]),'oauth/revoke');
            }
            if ($path===$issuerPath.'/reauth' && $this->bridge) {
                if($method!=='POST')return $this->json(405,['error'=>'Solo POST.']);
                $this->attempt('oauth/reauth');
                $this->bridge->start();
                return $this->tracked($this->bridge->complete((array)$request->getParsedBody(),$_SESSION),'oauth/reauth',(int)($_SESSION['user']['id']??0));
            }
            if ($path===$issuerPath.'/authorize') {
                if($method==='GET')$this->oauth->validateAuthorization($request);
                $this->attempt('oauth/authorize');
                if($this->bridge)$this->bridge->start();else ($this->startSession)();
                if ($method==='GET') {
                    if($this->bridge && ($reauth=$this->bridge->offer($request,$_SESSION)))return $this->tracked($reauth,'oauth/reauth_form');
                    $form=$this->consent->begin($request,$_SESSION);
                    $escape=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
                    $html='<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Autorizar consulta UIAT</title><main><h1>Conectar UIAT NORTE con ChatGPT</h1><p>'.$escape($form['descripcion']).'</p><p>Esta autorización permite transferir los campos indicados a ChatGPT. Revísala antes de continuar.</p><form method="post" action="'.$escape($this->issuer.'/authorize').'"><input type="hidden" name="ticket" value="'.$escape($form['ticket']).'"><input type="hidden" name="csrf" value="'.$escape($form['csrf']).'"><button name="decision" value="autorizar">Autorizar consulta</button><button name="decision" value="denegar">Denegar</button></form></main></html>';
                    return $this->tracked(new Response(200,['Content-Type'=>'text/html; charset=utf-8','Cache-Control'=>'no-store','Referrer-Policy'=>'no-referrer','Content-Security-Policy'=>"default-src 'none'; form-action 'self' https://chatgpt.com; frame-ancestors 'none'; base-uri 'none'"],$html),'oauth/consent_form',(int)$_SESSION['user']['id']);
                }
                if ($method==='POST')return $this->tracked($this->consent->complete((array)$request->getParsedBody(),$_SESSION)->withHeader('Cache-Control','no-store')->withHeader('Referrer-Policy','no-referrer'),'oauth/consent',(int)($_SESSION['user']['id']??0));
                return $this->json(405,['error'=>'Método no permitido.']);
            }
            return $this->json(404,['error'=>'Ruta no disponible.']);
        } catch (AuditUnavailable $e) {
            return $this->json(503,['error'=>'Servicio no disponible.']);
        } catch (RateLimited $e) {
            return $this->tracked($this->json(429,['error'=>'Límite de intentos alcanzado.'])->withHeader('Retry-After',(string)$e->retryAfter),'oauth/rechazo');
        } catch (\PDOException $e) {
            return $this->json(503,['error'=>'Servicio no disponible.']);
        } catch (OAuthServerException $e) {
            // Library response contains standard OAuth errors; no custom debug stack or traces.
            $response=$e->generateHttpResponse(new Response());
            if($response->hasHeader('Location'))$response=$response->withHeader('Location',$response->getHeaderLine('Location').'&iss='.rawurlencode($this->issuer));
            return $this->tracked($response->withHeader('Cache-Control','no-store')->withHeader('Referrer-Policy','no-referrer'),'oauth/rechazo');
        } catch (Throwable $e) {
            return $this->tracked($this->json(400,['error'=>'Solicitud no autorizada, sesión vencida o parámetros inválidos.']),'oauth/rechazo');
        }
    }
}
