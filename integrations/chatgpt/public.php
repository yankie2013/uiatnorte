<?php
declare(strict_types=1);
// No route in production points at this draft. Default closed even if copied accidentally.
ini_set('display_errors','0');
header('Cache-Control: no-store');
if (getenv('UIAT_CONNECTOR_ENABLED')!=='1') {
    http_response_code(503);header('Content-Type: application/json');exit('{"error":"Conector deshabilitado."}');
}
try {
    $path=(string)getenv('UIAT_CONNECTOR_PRIVATE_CONFIG');
    $real=realpath($path);$web=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));
    if (!str_starts_with($path,'/')||!$real||is_link($path)||!is_file($real)
        ||(fileperms($real)&0027)!==0
        ||($web&&($real===$web||str_starts_with($real,$web.DIRECTORY_SEPARATOR))))throw new RuntimeException('Configuración privada requerida.');
    require __DIR__.'/vendor/autoload.php';
    foreach (['OAuthCore','FileBudget','RuntimeDefinition','CaseQueries','CameraDrafts','OfficeQueries','OfficeDrafts','Consent','SessionBridge','McpKernel','HttpKernel'] as $class)require __DIR__.'/src/'.$class.'.php';
    $factory=require $real;
    if (!$factory instanceof \Uiat\Connector\RuntimeDefinition)throw new RuntimeException('Factoría en dos fases requerida.');
    $psr=new \Nyholm\Psr7\Factory\Psr17Factory();
    $request=(new \Nyholm\Psr7Server\ServerRequestCreator($psr,$psr,$psr,$psr))->fromGlobals();
    // Apache's HTTPS state is required; forwarded headers do not establish trust.
    if (empty($_SERVER['HTTPS'])||strtolower((string)$_SERVER['HTTPS'])==='off') {
        http_response_code(403);header('Content-Type: application/json');exit('{"error":"HTTPS requerido."}');
    }
    $kernel=$factory->open($request);
    $response=$kernel->handle($request);
    http_response_code($response->getStatusCode());
    foreach($response->getHeaders() as $name=>$values)foreach($values as $index=>$value)header($name.': '.$value,$index===0&&strcasecmp($name,'Set-Cookie')!==0);
    echo $response->getBody();
} catch (\Uiat\Connector\RateLimited $e) {
    http_response_code(429);header('Content-Type: application/json');header('Retry-After: '.$e->retryAfter);echo '{"error":"Límite de solicitudes alcanzado."}';
} catch (Throwable $e) {
    http_response_code(503);header('Content-Type: application/json');echo '{"error":"Conector no disponible."}';
}
