#!/usr/bin/env python3
"""Real HTTPS -> PHP CGI -> OAuth library -> MCP, only ephemeral SQLite fixtures."""
import email.parser,hashlib,http.cookies,http.server,json,os,pathlib,re,secrets,shutil,sqlite3,ssl,subprocess,tempfile,threading,urllib.error,urllib.parse,urllib.request
ROOT=pathlib.Path(__file__).resolve().parents[1]
PHP=os.environ.get('UIAT_TEST_PHP','/Applications/MAMP/bin/php/php8.3.30/bin/php')
CGI=os.environ.get('UIAT_TEST_PHP_CGI',PHP+'-cgi')
DRAFTS=os.environ.get('UIAT_TEST_CAMERA_FEATURE')=='1'
checks=0

def check(value,label):
 global checks
 if not value:raise RuntimeError(label)
 checks+=1

with tempfile.TemporaryDirectory(prefix='uiat-oauth-https-') as tmp:
 base=pathlib.Path(tmp);private=base/'private';private.mkdir(mode=0o700)
 web=base/'public';web.mkdir(mode=0o700)
 sessions=private/'sessions';sessions.mkdir(mode=0o700)
 draftsdir=private/'drafts';draftsdir.mkdir(mode=0o700)
 # Reuse only the literal synthetic SQL from the source tests; no app bootstrap or .env.
 src=(ROOT/'tests/security.php').read_text()
 fixture=re.search(r'\$data->exec\("(.*?)"\);',src,re.S).group(1)
 datafile=private/'fictional.sqlite'
 with sqlite3.connect(datafile) as db:db.executescript(fixture)
 # A minimal copy of the existing UIAT login code; never copy its .env or data.
 project=base/'uiat-app';project.mkdir(mode=0o700)
 source=ROOT.parents[1]
 for rel in ['auth.php','bootstrap/app.php','app/Support/Env.php','app/Support/Config.php','app/Support/helpers.php','app/Support/Auth.php','app/Support/PwaSupport.php','app/Support/Access.php','app/Database/Database.php','app/Services/LoginService.php','app/Repositories/UserRepository.php','config/app.php','config/session.php','config/database.php']:
  target=project/rel;target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(source/rel,target)
 password=secrets.token_urlsafe(24)
 password_env=os.environ.copy();password_env['UIAT_TEST_PASSWORD']=password
 password_hash=subprocess.check_output([PHP,'-r',"echo password_hash(getenv('UIAT_TEST_PASSWORD'),PASSWORD_DEFAULT);"],env=password_env).decode()
 with sqlite3.connect(datafile) as db:
  for column in ['email','nombre','cip','grado','unidad','pass_hash']:db.execute('ALTER TABLE usuarios ADD COLUMN '+column+' TEXT')
  db.execute("UPDATE usuarios SET email='ficticio10@example.invalid',nombre='Usuario ficticio',cip='90000010',grado='',unidad='',pass_hash=? WHERE id=10",(password_hash,))
 statefile=private/'oauth.sqlite'
 with sqlite3.connect(statefile) as db:db.executescript('CREATE TABLE auth_codes(id TEXT PRIMARY KEY,user_id INTEGER,version INTEGER,expires INTEGER,revoked INTEGER DEFAULT 0); CREATE TABLE access_tokens(id TEXT PRIMARY KEY,user_id INTEGER,version INTEGER,client TEXT,resource TEXT,expires INTEGER,family_expiry INTEGER,family_id TEXT,token_hash TEXT UNIQUE,revoked INTEGER DEFAULT 0); CREATE TABLE refresh_tokens(id TEXT PRIMARY KEY,access_id TEXT,expires INTEGER,token_hash TEXT UNIQUE,revoked INTEGER DEFAULT 0); CREATE TABLE budgets(bucket TEXT PRIMARY KEY,window INTEGER,count INTEGER);')
 key=private/'rsa.key';pub=private/'rsa.pub';cert=private/'cert.pem';tlskey=private/'tls.key'
 subprocess.run(['openssl','genpkey','-algorithm','RSA','-pkeyopt','rsa_keygen_bits:2048','-out',str(key)],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 subprocess.run(['openssl','pkey','-in',str(key),'-pubout','-out',str(pub)],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(tlskey),'-out',str(cert),'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 class Handler(http.server.BaseHTTPRequestHandler):
  def log_message(self,*unused):pass
  def do_GET(self):self.serve()
  def do_POST(self):self.serve()
  def serve(self):
   path,_,query=self.path.partition('?')
   if path not in ['/mcp-real','/oauth/authorize','/oauth/reauth','/oauth/borrador','/oauth/token','/oauth/revoke','/.well-known/oauth-authorization-server/oauth','/.well-known/oauth-protected-resource/mcp-real']:
    self.send_error(404);return
   length=int(self.headers.get('Content-Length','0'))
   if length>65536:self.send_error(413);return
   payload=self.rfile.read(length)
   env=os.environ.copy()
   env.update(SESSION_NAME='UIAT_OAUTH_FIXTURE',SESSION_SAVE_PATH=str(sessions),SESSION_SECURE_COOKIE='1',SESSION_SAME_SITE='Lax',APP_DEBUG='0',UIAT_CONNECTOR_ENABLED='1',UIAT_CONNECTOR_PRIVATE_CONFIG=str(private/'factory.php'),REDIRECT_STATUS='200',GATEWAY_INTERFACE='CGI/1.1',SERVER_PROTOCOL='HTTP/1.1',REQUEST_METHOD=self.command,SCRIPT_FILENAME=str(ROOT/'public.php'),SCRIPT_NAME='/public.php',REQUEST_URI=self.path,QUERY_STRING=query,DOCUMENT_ROOT=str(web),HTTPS='on',SERVER_NAME='localhost',SERVER_PORT=str(self.server.server_port),HTTP_HOST='localhost:'+str(self.server.server_port),REMOTE_ADDR='127.0.0.1',CONTENT_TYPE=self.headers.get('Content-Type',''),CONTENT_LENGTH=str(length))
   for name in ['Cookie','Authorization','Accept','MCP-Protocol-Version']:
    env['HTTP_'+name.upper().replace('-','_')]=self.headers.get(name,'')
   proc=subprocess.run([CGI],input=payload,env=env,capture_output=True,timeout=15)
   raw,sep,body=proc.stdout.partition(b'\r\n\r\n')
   if not sep:self.send_error(503);return
   headers=email.parser.BytesParser().parsebytes(raw+b'\r\n\r\n')
   self.send_response(int(headers.get('Status','200').split()[0]))
   for k,v in headers.items():
    if k.lower()!='status':self.send_header(k,v)
   self.end_headers();self.wfile.write(body)
 server=http.server.HTTPServer(('127.0.0.1',0),Handler)
 origin='https://localhost:'+str(server.server_port);issuer=origin+'/oauth';resource=origin+'/mcp-real';callback=origin+'/callback-fixture'
 # This factory exists only in the temporary fixture. Never deploy it.
 q=json.dumps
 factory='''<?php
return new Uiat\\Connector\\RuntimeDefinition(__ADMISSION__,__RATEKEY__,function() {
 $pdo=new PDO('sqlite:'.__DATA__,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema; CREATE TABLE information_schema.columns(table_schema TEXT,table_name TEXT,column_name TEXT)");
 $pdo->sqliteCreateFunction('DATABASE',fn()=>'fixture_test');
 foreach(['grado','cip','must_change_password','auth_version'] as $col){$s=$pdo->prepare("INSERT INTO information_schema.columns VALUES('fixture_test','usuarios',?)");$s->execute([$col]);}
 $pdo->exec('PRAGMA query_only=ON');
 if(!defined('UIAT_PWA_BOOTED'))define('UIAT_PWA_BOOTED',true);
 require_once __PROJECT__.'/bootstrap/app.php';
 (new ReflectionProperty(App\\Database\\Database::class,'connection'))->setValue(null,$pdo);
 $users=new Uiat\\Connector\\Users($pdo);
 $state=new Uiat\\Connector\\State(new PDO('sqlite:'.__STATE__,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]),$users);
 $oauth=new Uiat\\Connector\\OAuthCore($state,__ISSUER__,__RESOURCE__,'fixture-client',__CALLBACK__,file_get_contents(__KEY__),file_get_contents(__PUB__),__ENCRYPTION__);
 $queries=new Uiat\\Connector\\CaseQueries($pdo);
 $consent=new Uiat\\Connector\\Consent($oauth,$users,__ISSUER__.'/authorize');
 $audit=new Uiat\\Connector\\Audit(__AUDIT__);
 $drafts=__DRAFTS__ ? new Uiat\\Connector\\CameraDrafts($queries,$users,new Uiat\\Connector\\CameraDraftStore(__DRAFTSDIR__),new Uiat\\Connector\\CameraDraftRenderer(__TEMPLATE__,__TEMPLATESHA__),__ISSUER__.'/borrador') : null;
 $mcp=new Uiat\\Connector\\McpKernel($oauth,$queries,$audit,__METADATA__,$drafts);
 $start=function(){session_name('UIAT_OAUTH_FIXTURE');session_save_path(__SESSIONS__);ini_set('session.use_strict_mode','1');session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax']);session_start();};
 $bridge=Uiat\\Connector\\SessionBridge::forProject(__PROJECT__,$users,__ISSUER__,fn(int $id)=>$state->passwordAttempt($id));
 return new Uiat\\Connector\\HttpKernel($oauth,$consent,$mcp,__ISSUER__,__RESOURCE__,$start,$audit,$bridge,$drafts);
});
'''
 replacements={'DATA':str(datafile),'STATE':str(statefile),'ISSUER':issuer,'RESOURCE':resource,'CALLBACK':callback,'KEY':str(key),'PUB':str(pub),'ENCRYPTION':secrets.token_hex(32),'AUDIT':str(private/'audit.jsonl'),'METADATA':origin+'/.well-known/oauth-protected-resource/mcp-real','SESSIONS':str(sessions),'ADMISSION':str(private),'RATEKEY':secrets.token_hex(32),'PROJECT':str(project),'DRAFTS':DRAFTS,'DRAFTSDIR':str(draftsdir),'TEMPLATE':str(ROOT/'assets/camera-draft-template.docx'),'TEMPLATESHA':hashlib.sha256((ROOT/'assets/camera-draft-template.docx').read_bytes()).hexdigest()}
 for name,value in replacements.items():factory=factory.replace('__'+name+'__',q(value))
 (private/'factory.php').write_text(factory);(private/'factory.php').chmod(0o600)
 seed=base/'seed.php';seed.write_text("<?php session_name('UIAT_OAUTH_FIXTURE');session_save_path("+q(str(sessions))+");session_start();session_regenerate_id(true);$_SESSION=['user'=>['id'=>10,'auth_version'=>4,'email'=>'ficticio10@example.invalid'],'api_authenticated_at'=>time()-1000];echo session_id();session_write_close();")
 sid=subprocess.check_output([PHP,str(seed)]).decode().strip()
 tls=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);tls.load_cert_chain(cert,tlskey);server.socket=tls.wrap_socket(server.socket,server_side=True)
 thread=threading.Thread(target=server.serve_forever,daemon=True);thread.start()
 context=ssl.create_default_context(cafile=str(cert))
 class NoRedirect(urllib.request.HTTPRedirectHandler):
  def redirect_request(self,*unused,**kwargs):return None
 opener=urllib.request.build_opener(urllib.request.HTTPSHandler(context=context),NoRedirect())
 def request(path,data=None,headers=None):
  req=urllib.request.Request(origin+path,data=data,headers=headers or {})
  try:response=opener.open(req,timeout=20)
  except urllib.error.HTTPError as error:response=error
  return response,response.read()
 def rpc(name,args=None,token=None):
  headers={'Content-Type':'application/json','Accept':'application/json, text/event-stream'}
  if token:headers['Authorization']='Bearer '+token
  payload={'jsonrpc':'2.0','id':1,'method':name,'params':args or {}}
  response,raw=request('/mcp-real',json.dumps(payload).encode(),headers)
  return response,json.loads(raw)
 try:
  response,raw=request('/.well-known/oauth-protected-resource/mcp-real')
  check(response.code==200 and json.loads(raw)['resource']==resource,'resource discovery')
  response,raw=request('/.well-known/oauth-authorization-server/oauth')
  check(response.code==200 and json.loads(raw)['issuer']==issuer,'authorization discovery')
  response,body=rpc('initialize',{'protocolVersion':'2025-11-25','capabilities':{},'clientInfo':{'name':'fixture','version':'1.0'}})
  check(response.code==200,'MCP initialize')
  response,body=rpc('tools/list');check(len(body['result']['tools'])==(7 if DRAFTS else 6),'registered tools')
  response,body=rpc('tools/call',{'name':'consultar_participantes','arguments':{'expediente_id':1}})
  check(response.code==401 and 'resource_metadata=' in response.headers['WWW-Authenticate'],'anonymous challenge')
  proof=secrets.token_urlsafe(48);challenge=__import__('base64').urlsafe_b64encode(hashlib.sha256(proof.encode()).digest()).decode().rstrip('=')
  state=secrets.token_hex(24)
  params={'response_type':'code','client_id':'fixture-client','redirect_uri':callback,'scope':'uiat:read','state':state,'resource':resource,'code_challenge':challenge,'code_challenge_method':'S256'}
  cookie={'Cookie':'UIAT_OAUTH_FIXTURE='+sid}
  response,raw=request('/oauth/authorize?'+urllib.parse.urlencode(params),headers=cookie)
  check(response.code==200 and b'name="password"' in raw,'existing-session reauthentication form')
  reauth={name:re.search(rb'name="'+name.encode()+rb'" value="([^"]+)"',raw).group(1).decode() for name in ['ticket','csrf']}
  response,raw=request('/oauth/reauth',urllib.parse.urlencode(dict(reauth,password='INCORRECTO-FICTICIO')).encode(),cookie)
  check(response.code==400,'existing LoginService rejects incorrect password')
  response,raw=request('/oauth/authorize?'+urllib.parse.urlencode(params),headers=cookie)
  reauth={name:re.search(rb'name="'+name.encode()+rb'" value="([^"]+)"',raw).group(1).decode() for name in ['ticket','csrf']}
  response,raw=request('/oauth/reauth',urllib.parse.urlencode(dict(reauth,password=password)).encode(),cookie)
  check(response.code==303,'existing LoginService verifies password and renews session')
  renewed=http.cookies.SimpleCookie()
  for cookie_header in response.headers.get_all('Set-Cookie',[]):renewed.load(cookie_header)
  new_sid=renewed['UIAT_OAUTH_FIXTURE'].value
  check(new_sid!=sid,'existing LoginService rotates cookie')
  cookie={'Cookie':'UIAT_OAUTH_FIXTURE='+new_sid}
  response,raw=request('/oauth/authorize?'+urllib.parse.urlencode(params),headers=cookie)
  check(response.code==200 and b'Autorizar consulta' in raw,'consent page')
  form={name:re.search(rb'name="'+name.encode()+rb'" value="([^"]+)"',raw).group(1).decode() for name in ['ticket','csrf']}
  response,raw=request('/oauth/authorize',urllib.parse.urlencode(dict(form,csrf='INVALID',decision='autorizar')).encode(),cookie)
  check(response.code==400,'CSRF denied')
  response,raw=request('/oauth/authorize',urllib.parse.urlencode(dict(form,decision='autorizar')).encode(),cookie)
  check(response.code==302,'explicit consent redirect')
  values=urllib.parse.parse_qs(urllib.parse.urlsplit(response.headers['Location']).query)
  check(values['state']==[state] and values['iss']==[issuer],'state/issuer response')
  tokenbody={'grant_type':'authorization_code','client_id':'fixture-client','redirect_uri':callback,'resource':resource,'code':values['code'][0],'code_verifier':proof}
  response,raw=request('/oauth/token',urllib.parse.urlencode(tokenbody).encode())
  token=json.loads(raw)['access_token'];check(response.code==200 and response.headers['Cache-Control']=='no-store','token exchange')
  response,raw=request('/oauth/token',urllib.parse.urlencode(tokenbody).encode());check(response.code==400,'code replay rejected')
  for tool,args in [('buscar_expedientes',{'consulta':'FIC'}),('consultar_expediente',{'expediente_id':1}),('consultar_participantes',{'expediente_id':1}),('consultar_diligencias',{'expediente_id':1}),('revisar_datos_faltantes',{'expediente_id':1})]:
   response,body=rpc('tools/call',{'name':tool,'arguments':args},token)
   check(response.code==200 and not body['result']['isError'],'authorized '+tool)
   check('SECRETO-FICTICIO' not in json.dumps(body),'minimized '+tool)
  response,body=rpc('tools/call',{'name':'consultar_participantes','arguments':{'expediente_id':2}},token)
  check(response.code==200 and body['result']['isError'] and 'structuredContent' not in body['result'],'foreign case no records')
  response,body=rpc('tools/call',{'name':'consultar_participantes','arguments':{'expediente_id':1,'usuario_id':99}},token)
  check(body['result']['isError'],'identity injection rejected')
  response,raw=request('/oauth/revoke',urllib.parse.urlencode({'client_id':'fixture-client','token':token}).encode())
  check(response.code==200,'HTTPS revocation')
  response,body=rpc('tools/call',{'name':'consultar_expediente','arguments':{'expediente_id':1}},token)
  check(response.code==401,'revoked token denied over HTTPS')
  response,raw=request('/oauth/authorize?'+urllib.parse.urlencode(params),headers=cookie)
  check(response.code==200,'new connection consent')
  form={name:re.search(rb'name="'+name.encode()+rb'" value="([^"]+)"',raw).group(1).decode() for name in ['ticket','csrf']}
  response,raw=request('/oauth/authorize',urllib.parse.urlencode(dict(form,decision='autorizar')).encode(),cookie)
  values=urllib.parse.parse_qs(urllib.parse.urlsplit(response.headers['Location']).query)
  tokenbody['code']=values['code'][0]
  response,raw=request('/oauth/token',urllib.parse.urlencode(tokenbody).encode())
  check(response.code==200,'new connection after revocation')
  token=json.loads(raw)['access_token']
  if DRAFTS:
   response,body=rpc('tools/call',{'name':'preparar_borrador_camaras','arguments':{'expediente_id':1}},token)
   check(response.code==200 and body['result']['structuredContent']['datos']['estado']=='faltan_datos','draft asks missing fields over HTTPS')
   fields={'expediente_id':1,'nombre_oficial_ano':'Año ficticio de prueba 2026','numero_oficio':'018-B','destinatario_cargo':'Responsable de Videovigilancia','destinatario_nombre':'Destinatario de prueba','entidad_destino':'Entidad de Prueba','modalidad_confirmada':'colisión ficticia','consecuencia_confirmada':'daños materiales ficticios','referencia_confirmada':'frente al Parque de Prueba','fiscalia_confirmada':'la Fiscalía de Prueba','localidad_emision':'Ciudad de Prueba','fecha_emision':'2026-11-02','rango_desde':'09:30','rango_hasta':'10:30','lugar_aportado':'avenida Ensayo N.º 100'}
   response,body=rpc('tools/call',{'name':'preparar_borrador_camaras','arguments':fields},token)
   preview=body['result']['structuredContent']['datos']
   check(response.code==200 and preview['estado']=='pendiente_confirmacion','draft preview over HTTPS')
   check(not list(draftsdir.glob('*.docx')),'no file before explicit confirmation')
   confirmed={'expediente_id':1,'confirmacion_id':preview['confirmacion_id']}
   response,body=rpc('tools/call',{'name':'preparar_borrador_camaras','arguments':confirmed},token)
   draft=body['result']['structuredContent']['datos'];check(response.code==200 and draft['estado']=='borrador_generado','draft generated over HTTPS')
   relative=draft['descarga_url'].removeprefix(origin)
   response,raw=request(relative)
   check(response.code==302 and response.headers.get('Location')=='/login.php' and not raw.startswith(b'PK'),'download requires UIAT login')
   response,raw=request(relative,headers=cookie)
   check(response.code==200 and raw.startswith(b'PK') and 'attachment' in response.headers.get('Content-Disposition',''),'authenticated Word download over HTTPS')
   check(response.headers.get('Cache-Control')=='no-store, private' and response.headers.get('Referrer-Policy')=='no-referrer','private download headers')
   response,body=rpc('tools/call',{'name':'preparar_borrador_camaras','arguments':confirmed},token)
   check(body['result']['isError'],'draft confirmation replay denied over HTTPS')
   response,body=rpc('tools/call',{'name':'preparar_borrador_camaras','arguments':dict(fields,expediente_id=2)},token)
   check(body['result']['isError'],'foreign draft rejected over HTTPS')
   audittext=(private/'audit.jsonl').read_text()
   check(draft['borrador_id'] not in audittext and fields['entidad_destino'] not in audittext,'draft IDs and fields excluded from audit')
  with sqlite3.connect(datafile) as db:db.execute('UPDATE usuarios SET activo=0 WHERE id=10')
  response,body=rpc('tools/call',{'name':'consultar_expediente','arguments':{'expediente_id':1}},token)
  check(response.code==401,'identity revalidated every request')
  audit=(private/'audit.jsonl').read_text();check(token not in audit and 'SECRETO-FICTICIO' not in audit,'audit excludes tokens and content')
  print(f'{checks} comprobaciones HTTPS correctas: descubrimiento OAuth/MCP, consentimiento, PKCE, token, cinco consultas, minimización y rechazos. Solo datos ficticios.')
 finally:
  server.shutdown();server.server_close()
