#!/usr/bin/env python3
"""Local TLS + real PHP CGI + ephemeral MySQL fixtures. Never loads the project's .env."""
import argparse, contextlib, email.parser, http.server, json, os, pathlib, re, shutil, ssl, subprocess, tempfile, threading, urllib.error, urllib.request, urllib.parse

parser = argparse.ArgumentParser()
parser.add_argument('--isolated-local', action='store_true')
parser.add_argument('--prepare-local', type=pathlib.Path, help='Provision an inactive fictional sandbox in a new private directory; no HTTP server starts')
args = parser.parse_args()
if not args.isolated_local:
    parser.error('requires --isolated-local; creates and deletes fictional database/account')
repo = pathlib.Path(__file__).resolve().parents[1]
php = '/Applications/MAMP/bin/php/php8.3.30/bin/php'
cgi = php + '-cgi'
checks = 0
prepared = False
if args.prepare_local:
    target = args.prepare_local.expanduser().resolve()
    # Persist only outside MAMP's document root and outside the project.
    if target.exists() or not target.parent.is_dir() or target == repo or repo in target.parents or pathlib.Path('/Applications/MAMP/htdocs') in target.parents:
        parser.error('prepare-local requires a new directory outside the web/project roots')
    target.mkdir(mode=0o700)
    context = contextlib.nullcontext(str(target))
else:
    context = tempfile.TemporaryDirectory(prefix='uiat-https-fictional-')
with context as tmp:
    base = pathlib.Path(tmp)
    root = base / 'site'
    private = base / 'private'
    sessions = base / 'sessions'
    for directory in (root, private, sessions): directory.mkdir(mode=0o700)
    # Copy only required source files; no .env, uploads, logs or existing sessions.
    for folder in ('api/v1', 'app/Api', 'bootstrap', 'app/Database'):
        shutil.copytree(repo / folder, root / folder)
    for name in ('Env.php','Config.php','helpers.php','Auth.php','PwaSupport.php','Access.php'):
        dest = root / 'app/Support' / name
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(repo / 'app/Support' / name, dest)
    (root / 'app/Services').mkdir()
    shutil.copy2(repo / 'app/Services/LoginService.php', root / 'app/Services/LoginService.php')
    (root / 'app/Repositories').mkdir()
    shutil.copy2(repo / 'app/Repositories/UserRepository.php', root / 'app/Repositories/UserRepository.php')
    for name in ('login.php','auth.php','db.php'): shutil.copy2(repo / name, root / name)
    (root / 'assets/css').mkdir(parents=True)
    shutil.copy2(repo / 'assets/css/login.css', root / 'assets/css/login.css')
    (root / 'config').mkdir()
    for name in ('app.php','session.php','database.php'): shutil.copy2(repo / 'config' / name, root / 'config' / name)
    manager = base / 'fixture.php'
    manager.write_text('''<?php
$port=getenv('UIAT_TEST_PORT');
if($port!==false&&(!ctype_digit($port)||(int)$port<1024||(int)$port>65535))exit(2);
$dsn=$port===false?'mysql:unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock':'mysql:host=127.0.0.1;port='.$port;
$p=new PDO($dsn.';charset=utf8mb4',getenv('UIAT_TEST_ADMIN_USER')?:'root',getenv('UIAT_TEST_ADMIN_PASSWORD')?:'root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db=getenv('FIXTURE_DB');$u=getenv('FIXTURE_USER');
if(!preg_match('/^uiat_https_[a-f0-9]{12}_test$/D',$db)||!preg_match('/^uiat_https_[a-f0-9]{12}$/D',$u))exit(2);
$host=$port===false?"@'localhost'":"@'%'";
$account=$p->quote($u).$host;
$loginAccount=$p->quote($u.'_login').$host;
try {
 if($argv[1]==='drop'){$p->exec("DROP USER IF EXISTS $loginAccount");$p->exec("DROP USER IF EXISTS $account");$p->exec("DROP DATABASE IF EXISTS `$db`");exit;}
 if($argv[1]==='setup'){
  $p->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4");$p->exec("USE `$db`");
  $source=file_get_contents(getenv('FIXTURE_SOURCE'));
  if(!preg_match('/\\$p->exec\\("(.*?)"\\);/s',$source,$m))exit(3);
  foreach(explode(';',$m[1]) as $sql)if(trim($sql)!=='')$p->exec($sql);
  $p->exec("CREATE TABLE usuarios(id INT,rol VARCHAR(30),activo INT,must_change_password INT,auth_version INT,email VARCHAR(100),nombre VARCHAR(100),pass_hash VARCHAR(255),cip VARCHAR(20),grado VARCHAR(30),unidad VARCHAR(50))");
  $p->exec("INSERT INTO usuarios(id,rol,activo,must_change_password,auth_version) VALUES(10,'jefe_emi',1,0,4),(11,'adjunto',1,0,4),(12,'secretaria',1,0,4),(13,'guardia',1,0,4)");
  $p->exec('CREATE USER '.$account.' IDENTIFIED BY '.$p->quote(getenv('FIXTURE_PASSWORD')));
  $hash=password_hash(getenv('FIXTURE_LOGIN_PASSWORD'),PASSWORD_DEFAULT);
  $st=$p->prepare("UPDATE usuarios SET email='fictional@example.invalid',nombre='Usuario ficticio',cip='90000010',pass_hash=? WHERE id=10");$st->execute([$hash]);
  $p->exec('CREATE USER '.$loginAccount.' IDENTIFIED BY '.$p->quote(getenv('FIXTURE_PASSWORD')));
  $p->exec("GRANT SELECT ON `$db`.usuarios TO $loginAccount");
  echo $port!==false?$port:$p->query('SELECT @@port')->fetchColumn();
  foreach(['accidentes_activos'=>'id,sidpol,registro_sidpol,fecha_accidente,estado,responsable_id','diligencias_pendientes_activos'=>'id,accidente_id,tipo_diligencia_id,estado','usuarios'=>'id,rol,activo,must_change_password,auth_version','expediente_colaboradores'=>'accidente_id,usuario_id,revocado_en','expediente_transferencias'=>'accidente_id,destino_id,tipo,estado'] as $table=>$cols)$p->exec("GRANT SELECT ($cols) ON `$db`.`$table` TO $account");
 }else{
  $p->exec("USE `$db`");
  $sql=match($argv[1]){'revoke'=>"UPDATE expediente_colaboradores SET revocado_en='2000-01-01' WHERE usuario_id=11",'disable'=>'UPDATE usuarios SET activo=0 WHERE id=10','enable'=>'UPDATE usuarios SET activo=1 WHERE id=10','version'=>'UPDATE usuarios SET auth_version=5 WHERE id=10','reset_login'=>'UPDATE usuarios SET auth_version=4,activo=1 WHERE id=10',default=>throw new RuntimeException()};$p->exec($sql);
 }
}catch(Throwable $e){fwrite(STDERR,"Fixture operation failed; details suppressed.\\n");exit(1);}
''')
    suffix = os.urandom(6).hex()
    env = os.environ.copy()
    env.update(FIXTURE_DB='uiat_https_'+suffix+'_test', FIXTURE_USER='uiat_https_'+suffix,
               FIXTURE_PASSWORD=os.urandom(32).hex(), FIXTURE_LOGIN_PASSWORD=os.urandom(24).hex(), FIXTURE_SOURCE=str(repo/'tests/read_api.php'))
    def fixture(action):
        result = subprocess.run([php, str(manager), action], env=env, capture_output=True)
        if result.returncode: raise RuntimeError('Fictional fixture operation failed: '+action)
        return result.stdout.decode().strip()
    env.update(UIAT_READ_API_ENABLED='1', UIAT_READ_API_PRIVATE_DIR=str(private),
               UIAT_READ_API_DSN='mysql:unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock;dbname='+env['FIXTURE_DB']+';charset=utf8mb4',
               UIAT_READ_API_USER=env['FIXTURE_USER'], UIAT_READ_API_PASSWORD=env['FIXTURE_PASSWORD'],
               SESSION_NAME='UIAT_FICTIONAL_TEST', SESSION_SAVE_PATH=str(sessions), SESSION_SECURE_COOKIE='1', SESSION_SAME_SITE='Strict', APP_DEBUG='0')
    if env.get('UIAT_TEST_PORT'):
        env['UIAT_READ_API_DSN']='mysql:host=127.0.0.1;port='+env['UIAT_TEST_PORT']+';dbname='+env['FIXTURE_DB']+';charset=utf8mb4'
    # Create sessions offline using the real login session initializer. This is not a public login endpoint.
    seed = base/'seed.php'
    seed.write_text('''<?php
require getenv('FIXTURE_ROOT').'/bootstrap/app.php';
App\\Support\\Auth::startSession();
App\\Services\\LoginService::beginSession(['id'=>(int)$argv[1],'rol'=>'ignored','auth_version'=>4,'must_change_password'=>0]);
if($argv[2]==='idle')$_SESSION['api_last_seen']=time()-901;
if($argv[2]==='absolute') {$_SESSION['api_authenticated_at']=time()-28801;$_SESSION['api_last_seen']=time();}
if($argv[2]==='legacy')unset($_SESSION['api_authenticated_at']);
echo session_id();session_write_close();
''')
    env['FIXTURE_ROOT'] = str(root)
    def session(user=10, mode='valid'):
        return subprocess.check_output([php,str(seed),str(user),mode],env=env,stderr=subprocess.DEVNULL).decode().strip()
    cert, key = base/'cert.pem', base/'key.pem'
    subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(key),'-out',str(cert),'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    class Handler(http.server.BaseHTTPRequestHandler):
        def log_message(self,*unused): pass
        def do_GET(self): self.serve()
        def do_POST(self): self.serve()
        def do_OPTIONS(self): self.serve()
        def serve(self):
            path, _, query = self.path.partition('?')
            allowed = ['/api/v1/'+x+'.php' for x in ('buscar_expedientes','consultar_expediente','consultar_diligencias')]
            allowed.append('/login.php')
            if path not in allowed: self.send_error(404); return
            child = env.copy()
            child.update(REDIRECT_STATUS='200', GATEWAY_INTERFACE='CGI/1.1', SERVER_PROTOCOL='HTTP/1.1', REQUEST_METHOD=self.command,
                         SCRIPT_FILENAME=str(root/path.lstrip('/')), SCRIPT_NAME=path, QUERY_STRING=query, REQUEST_URI=self.path,
                         DOCUMENT_ROOT=str(root), HTTPS='on', SERVER_NAME='localhost', SERVER_PORT=str(self.server.server_port), REMOTE_ADDR='127.0.0.1')
            for header in ('Cookie','Sec-Fetch-Site','X-Forwarded-Proto','X-Forwarded-For'):
                child['HTTP_'+header.upper().replace('-','_')] = self.headers.get(header,'')
            data=self.rfile.read(int(self.headers.get('Content-Length','0')))
            child.update(CONTENT_TYPE=self.headers.get('Content-Type',''),CONTENT_LENGTH=str(len(data)))
            response = subprocess.run([cgi],env=child,input=data,capture_output=True,timeout=10)
            raw_headers, sep, body = response.stdout.partition(b'\r\n\r\n')
            if not sep: self.send_error(500); return
            headers = email.parser.BytesParser().parsebytes(raw_headers+b'\r\n\r\n')
            self.send_response(int(headers.get('Status','200').split()[0]))
            for name,value in headers.items():
                if name.lower()!='status': self.send_header(name,value)
            self.end_headers(); self.wfile.write(body)
    server = None
    try:
        db_port=fixture('setup')
        env.update(DB_LOCAL_ONLY='1', DB_HOST='127.0.0.1', DB_PORT=db_port, DB_NAME=env['FIXTURE_DB'], DB_USER=env['FIXTURE_USER']+'_login', DB_PASS=env['FIXTURE_PASSWORD'])
        if args.prepare_local:
            # Persist only dedicated sandbox credentials; never administrator credentials.
            selected={k:v for k,v in env.items() if k.startswith(('UIAT_READ_API_', 'SESSION_')) or k in ('DB_LOCAL_ONLY','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS','APP_DEBUG')}
            selected['UIAT_READ_API_ENABLED']='0'
            selected['APP_DEBUG']='0'
            config=root/'.env.local'
            config.write_text(''.join(k+'='+v+'\n' for k,v in selected.items()))
            config.chmod(0o600)
            metadata={'database':env['FIXTURE_DB'],'reader':env['FIXTURE_USER'],'login_reader':env['FIXTURE_USER']+'_login','host':'127.0.0.1','port':db_port,'enabled':False,'fictional_only':True}
            (base/'sandbox.json').write_text(json.dumps(metadata,indent=2)+'\n')
            (base/'sandbox.json').chmod(0o600)
            # Offline demo login credentials, outside the web root and excluded from git.
            (base/'demo-login.json').write_text(json.dumps({'identifier':'90000010','email':'fictional@example.invalid','password':env['FIXTURE_LOGIN_PASSWORD']},indent=2)+'\n')
            (base/'demo-login.json').chmod(0o600)
            # Remove test-only helper scripts; their fixture manager has no admin secrets.
            manager.unlink(); seed.unlink()
            key.chmod(0o600)
            prepared=True
            print('Entorno ficticio preparado: conexión separada, cuentas mínimas, sesiones y auditoría privadas, certificado local. API deshabilitada; no se inició servidor HTTP.')
            raise SystemExit(0)
        server=http.server.HTTPServer(('127.0.0.1',0),Handler)
        tls=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);tls.load_cert_chain(cert,key)
        server.socket=tls.wrap_socket(server.socket,server_side=True)
        worker=threading.Thread(target=server.serve_forever,daemon=True);worker.start()
        client=ssl.create_default_context(cafile=str(cert))
        def request(endpoint, query='', sid=None, expected=200, method='GET', extra=None):
            global checks
            headers={'Cookie':'UIAT_FICTIONAL_TEST='+sid} if sid else {}
            headers.update(extra or {})
            req=urllib.request.Request(f'https://localhost:{server.server_port}/api/v1/{endpoint}.php'+('?' + query if query else ''),headers=headers,method=method)
            try: response=urllib.request.urlopen(req,context=client)
            except urllib.error.HTTPError as error: response=error
            body=json.loads(response.read())
            assert response.status==expected,(endpoint,response.status,expected)
            assert body['ok']==(expected==200)
            assert 'no-store' in response.headers['Cache-Control']
            assert response.headers['X-Request-ID']==body['solicitud']
            if expected!=200: assert 'datos' not in body
            if sid and expected==200:
                cookie=response.headers.get('Set-Cookie','')
                assert 'secure' in cookie.lower() and 'httponly' in cookie.lower() and 'samesite=strict' in cookie.lower()
            checks+=1
            return body
        chief=session()
        request('buscar_expedientes','q=FIC',expected=401)
        assert len(request('buscar_expedientes','q=FIC',chief)['datos'])==1
        request('consultar_expediente','expediente_id=1',chief)
        assert len(request('consultar_diligencias','expediente_id=1',chief)['datos'])==1
        for endpoint in ('consultar_expediente','consultar_diligencias'):
            request(endpoint,'expediente_id=2',chief,404)
        request('consultar_expediente','expediente_id=3',chief,404)
        request('consultar_expediente','expediente_id=999',chief,404)
        request('buscar_expedientes','q=FIC',session(13),403)
        request('buscar_expedientes','q=FIC',session(10,'idle'),401)
        request('buscar_expedientes','q=FIC',session(10,'absolute'),401)
        request('buscar_expedientes','q=FIC',session(10,'legacy'),401)
        adjunct=session(11)
        request('consultar_expediente','expediente_id=1',adjunct)
        fixture('revoke')
        request('consultar_expediente','expediente_id=1',adjunct,404)
        archive=session(12)
        request('consultar_expediente','expediente_id=2',archive)
        request('consultar_expediente','expediente_id=1',archive,404)
        request('buscar_expedientes','q=FIC',chief,405,method='POST')
        request('buscar_expedientes','q=FIC',chief,403,extra={'Sec-Fetch-Site':'cross-site'})
        request('buscar_expedientes','q=FIC&usuario_id=99',chief,422)
        fixture('disable'); request('buscar_expedientes','q=FIC',chief,401); fixture('enable')
        fixture('version'); request('buscar_expedientes','q=FIC',chief,401)
        # Limit shared between independently created sessions of the same user.
        (private/'rate-12.json').write_text(json.dumps({'window':__import__('time').time_ns()//60_000_000_000,'count':60}))
        request('buscar_expedientes','q=FIC',session(12),429)
        # Exercise the original login form and password verifier over TLS.
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self,*unused,**kwargs): return None
        opener=urllib.request.build_opener(urllib.request.HTTPSHandler(context=client),NoRedirect())
        def login_http(sid=None, fields=None):
            headers={'Cookie':'UIAT_FICTIONAL_TEST='+sid} if sid else {}
            data=urllib.parse.urlencode(fields).encode() if fields is not None else None
            req=urllib.request.Request(f'https://localhost:{server.server_port}/login.php',data=data,headers=headers)
            try: response=opener.open(req)
            except urllib.error.HTTPError as error: response=error
            text=response.read().decode()
            cookie=response.headers.get('Set-Cookie','')
            match=re.search(r'UIAT_FICTIONAL_TEST=([^;]+)',cookie)
            return response,text,match.group(1) if match else sid
        fixture('reset_login')
        response,html,anonymous=login_http()
        assert response.status==200 and 'id="loginForm"' in html
        csrf=re.search(r'name="_csrf" value="([^"]+)"',html).group(1)
        fields={'identifier':'90000010','password':env['FIXTURE_LOGIN_PASSWORD'],'_csrf':csrf}
        response,html,_=login_http(anonymous,dict(fields,_csrf='invalid'))
        assert response.status==200 and 'role="alert"' in html
        request('buscar_expedientes','q=FIC',anonymous,401)
        response,html,_=login_http(anonymous,dict(fields,password='FICTICIO_INCORRECTO'))
        assert response.status==200 and 'role="alert"' in html
        request('buscar_expedientes','q=FIC',anonymous,401)
        response,html,logged_in=login_http(anonymous,fields)
        assert response.status==302 and logged_in!=anonymous and response.headers['Location']=='index.php'
        request('consultar_expediente','expediente_id=1',logged_in)
        request('consultar_expediente','expediente_id=2',logged_in,404)
        request('buscar_expedientes','q=FIC',anonymous,401)
        # Email follows the same password validation path.
        response,html,email_sid=login_http()
        email_csrf=re.search(r'name="_csrf" value="([^"]+)"',html).group(1)
        response,html,email_sid=login_http(email_sid,dict(fields,identifier='fictional@example.invalid',_csrf=email_csrf))
        assert response.status==302
        request('consultar_diligencias','expediente_id=1',email_sid)
        print('Formulario real: CSRF y contraseña inválidos rechazados; login por CIP/correo, rotación de sesión y acceso API correctos.')
        # Anonymous admission is bounded before authentication and PDO.
        import hashlib,hmac,socket,time
        rate_key=hmac.new(env['FIXTURE_PASSWORD'].encode(),socket.inet_pton(socket.AF_INET,'127.0.0.1'),hashlib.sha256).hexdigest()
        ingress=private/'request-rate.json'
        ingress.write_text(json.dumps({'window':int(time.time())//60,'counts':{rate_key:300}}))
        request('buscar_expedientes','q=FIC',expected=429,extra={'X-Forwarded-For':'192.0.2.123'})
        ingress.write_text('INVALID')
        request('buscar_expedientes','q=FIC',expected=503)
        ingress.unlink()
        records=[json.loads(line) for line in (private/'audit.jsonl').read_text().splitlines()]
        assert len(records)==checks
        assert all(set(r)=={'fecha','solicitud','usuario_id','endpoint','estado'} for r in records)
        assert any(r['estado']==200 and r['usuario_id']==10 for r in records)
        # Broken audit storage must fail closed without any returned data.
        (private/'audit.jsonl').unlink(); (private/'audit.jsonl').mkdir()
        request('consultar_expediente','expediente_id=2',archive,503)
        print(f'{checks} peticiones HTTPS verificadas: sesiones, permisos, revocación, caducidad, límite y auditoría. Certificado local verificado.')
    finally:
        if server: server.shutdown();server.server_close()
        if not prepared:
            fixture('drop')
            print('Base, cuenta, sesiones y certificado ficticios eliminados; proyecto y datos reales sin activar.')
