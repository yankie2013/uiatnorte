<?php
declare(strict_types=1);
// Installed outside the document root. Credentials are never read from a request.
use Uiat\Connector\{RuntimeDefinition,Users,State,OAuthCore,Consent,CaseQueries,Audit,McpKernel,HttpKernel,SessionBridge,CameraDrafts,CameraDraftStore,CameraDraftRenderer,OfficeQueries,OfficeDrafts};
$settings=json_decode(file_get_contents(__DIR__.'/settings.json'),true,32,JSON_THROW_ON_ERROR);
return new RuntimeDefinition($settings['private_dir'],$settings['rate_key'],static function()use($settings):HttpKernel {
    $pdo=new PDO($settings['dsn'],$settings['db_user'],$settings['db_password'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5,
    ]);
    $users=new Users($pdo);
    $state=new State(new PDO('sqlite:'.$settings['private_dir'].'/oauth.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]),$users);
    // Schema is provisioned offline; web requests never migrate it.
    $oauth=new OAuthCore($state,$settings['issuer'],$settings['resource'],$settings['client_id'],$settings['redirect'],
        file_get_contents(__DIR__.'/private.pem'),file_get_contents(__DIR__.'/public.pem'),$settings['encryption_key']);
    $audit=new Audit($settings['private_dir'].'/audit.jsonl');
    $queries=new CaseQueries($pdo,($settings['camera_case_context_enabled']??false)===true,($settings['camera_year_name_enabled']??false)===true);$drafts=null;
    // Off by default. Private folder must be provisioned offline; no web mkdir/migration.
    if(($settings['camera_drafts_enabled']??false)===true) {
        $drafts=new CameraDrafts($queries,$users,new CameraDraftStore($settings['private_dir'].'/borradores'),
            new CameraDraftRenderer('/opt/uiat-chatgpt-connector/assets/camera-draft-template.docx','630ed97af23b4452438fbc227988a7067f51180c6c481f18a8876559d281a64b'),$settings['issuer'].'/borrador');
    }
    $offices=null;
    if(($settings['office_drafts_enabled']??false)===true){
        if(!$drafts)throw new RuntimeException('Generador base requerido.');
        $offices=new OfficeDrafts(new OfficeQueries($pdo,$queries),$queries,$drafts,new CameraDraftStore($settings['private_dir'].'/borradores'),'/opt/uiat-chatgpt-connector/assets','b251989e59a9776164496d82a23ee0b413ad1154b5ca25fc3e9d75bf011bd3dd',$settings['issuer'].'/borrador');
    }
    $bridge=SessionBridge::forProject('/var/www/uiatnorte',$users,$settings['issuer'],fn(int $id)=>$state->passwordAttempt($id));
    return new HttpKernel($oauth,new Consent($oauth,$users,$settings['issuer'].'/authorize'),
        new McpKernel($oauth,$queries,$audit,$settings['metadata'],$drafts,$offices),
        $settings['issuer'],$settings['resource'],static function():void {},$audit,$bridge,$drafts);
});
