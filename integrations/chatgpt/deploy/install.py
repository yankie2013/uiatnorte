#!/usr/bin/env python3
"""UIAT connector: prepare disabled installation only. Requires explicit production approval."""
import argparse, datetime, hashlib, json, os, pathlib, pwd, re, secrets, shutil, stat, subprocess, sys, tarfile, tempfile
P=pathlib.Path
HOST='uiatnorte.korkaystore.com'
PROJECT=P('/var/www/uiatnorte')
RELEASE=P('/opt/uiat-chatgpt-connector')
PRIVATE=P('/etc/uiat-chatgpt-connector')
RUNTIME=P('/var/lib/uiat-chatgpt-connector')
CONF=P('/etc/apache2/conf-available/uiat-chatgpt-connector.conf')
VHOST=P('/etc/apache2/sites-available/uiatnorte-le-ssl.conf')
BACKUPS=P('/var/backups/uiat-chatgpt-connector')
ACCOUNT='uiat_chatgpt_reader'
PACKAGE_SHA='__PACKAGE_SHA__'
GRANTS={
 'accidentes_activos':['id','sidpol','registro_sidpol','fecha_accidente','estado','responsable_id','lugar'],
 'diligencias_pendientes_activos':['id','accidente_id','tipo_diligencia_id','estado'],
 'usuarios':['id','rol','activo','must_change_password','auth_version'],
 'expediente_colaboradores':['accidente_id','usuario_id','revocado_en'],
 'expediente_transferencias':['accidente_id','destino_id','tipo','estado'],
 'involucrados_personas_activos':['id','accidente_id','persona_id','rol_id'],
 'personas':['id','nombres','apellido_paterno','apellido_materno'],
 'participacion_persona':['Id','Nombre'],'tipo_diligencia':['id','nombre'],
}
ROUTES=['/mcp-uiat','/oauth-uiat/authorize','/oauth-uiat/reauth','/oauth-uiat/token','/oauth-uiat/revoke',
 '/.well-known/oauth-protected-resource/mcp-uiat','/.well-known/oauth-authorization-server/oauth-uiat']
def run(args,data=None):
 r=subprocess.run(args,input=data,text=True,capture_output=True)
 if r.returncode:raise RuntimeError('Falló '+args[0]+'; salida omitida para proteger secretos.')
 return r.stdout.strip()
def sql(query):return run(['mariadb','--batch','--skip-column-names'],query)
def request(path,body=False):
 args=['curl','--silent','--show-error','--max-time','15','--max-redirs','0','--resolve',HOST+':443:127.0.0.1']
 if not body:args+=['--output','/dev/null']
 return run(args+['--write-out','\n%{http_code}','https://'+HOST+path])
def code(path):return request(path).splitlines()[-1]
def digest(path):return hashlib.sha256(path.read_bytes()).hexdigest()
def atomic(path,data,mode=0o640,uid=0,gid=0):
 fd,tmp=tempfile.mkstemp(prefix='.uiat-',dir=path.parent)
 try:
  with os.fdopen(fd,'wb') as f:f.write(data);f.flush();os.fsync(f.fileno())
  os.chmod(tmp,mode);os.chown(tmp,uid,gid);os.replace(tmp,path)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
def private_dir(path,uid=0,gid=0,mode=0o700):
 path.mkdir(mode=mode);os.chown(path,uid,gid);path.chmod(mode)
def rollback(backup,automatic=False):
 if backup.parent!=BACKUPS or backup.is_symlink() or backup.stat().st_uid!=0:raise RuntimeError('Respaldo no autorizado.')
 journal=json.loads((backup/'journal.json').read_text())
 if journal.get('restored'):raise RuntimeError('Respaldo ya revertido.')
 if not automatic:
  if digest(VHOST)!=journal.get('installed_vhost_sha'):raise RuntimeError('El virtualhost cambió; requiere revisión manual antes de revertir.')
  if journal.get('conf_sha') and (not CONF.is_file() or digest(CONF)!=journal['conf_sha']):raise RuntimeError('La configuración del conector cambió; requiere revisión manual.')
 attrs=journal['vhost_attrs']
 atomic(VHOST,(backup/'vhost-original.conf').read_bytes(),*attrs)
 run(['apache2ctl','configtest']);run(['systemctl','reload','apache2'])
 if journal.get('conf_created') and CONF.exists():CONF.unlink()
 if journal.get('account_created'):sql("DROP USER IF EXISTS '"+ACCOUNT+"'@'localhost';")
 # Preserve all evidence/keys/state under a root-only directory; never erase audit on rollback.
 for path in [RELEASE,PRIVATE,RUNTIME]:
  if journal.get('created',{}).get(str(path)) and path.exists():
   os.chown(path,0,0);path.chmod(0o700);path.rename(backup/('retirado-'+path.name+'-'+secrets.token_hex(2)))
 journal['restored']=True;atomic(backup/'journal.json',json.dumps(journal,indent=2).encode(),0o600)
 print('REVERTIDO: virtualhost restaurado, cuenta nueva retirada; archivos y auditoría preservados en el respaldo privado.')
def install():
 os.umask(0o077)
 here=P(__file__).resolve().parent;package=here/'uiat-conector-apagado.tar.gz'
 if digest(package)!=PACKAGE_SHA:raise RuntimeError('Paquete no coincide con SHA-256 esperado.')
 for path in [RELEASE,PRIVATE,RUNTIME,CONF]:
  if path.exists() or path.is_symlink():raise RuntimeError('Destino ya existe; no se sobrescribe: '+str(path))
 if VHOST.is_symlink() or not VHOST.is_file() or VHOST.stat().st_uid!=0:raise RuntimeError('Virtualhost inesperado.')
 enabled=P('/etc/apache2/sites-enabled/uiatnorte-le-ssl.conf')
 if enabled.resolve()!=VHOST:raise RuntimeError('Virtualhost habilitado distinto al previsto.')
 if PROJECT.is_symlink() or not PROJECT.is_dir():raise RuntimeError('Proyecto no coincide con el autorizado.')
 if run(['git','-C',str(PROJECT),'rev-parse','--short=8','HEAD'])!='567cccf1':raise RuntimeError('La revisión del proyecto cambió.')
 if digest(PROJECT/'app/Services/LoginService.php')!='9dafdea364b28526a3da65f2b8a5fd44392f740e620fe4fdb3b56b1f0f8f5ec6':raise RuntimeError('LoginService cambió; revisar compatibilidad.')
 modules=run(['apache2ctl','-M'])
 if 'php_module' not in modules or 'alias_module' not in modules:raise RuntimeError('Se requiere Apache mod_php y alias; no se instalan módulos automáticamente.')
 run(['php','-r',"foreach(['pdo_mysql','pdo_sqlite','openssl','mbstring'] as $e)if(!extension_loaded($e))exit(1);if(PHP_VERSION_ID<80300)exit(1);"])
 if sql("SELECT COUNT(*) FROM mysql.user WHERE User='"+ACCOUNT+"';")!='0':raise RuntimeError('Cuenta propuesta ya existe; no se modifica.')
 # Metadata only. No business rows, passwords, logs or original .env are read.
 for table,columns in GRANTS.items():
  actual=set(sql("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='uiatnorte' AND TABLE_NAME='"+table+"';").splitlines())
  if not set(columns)<=actual:raise RuntimeError('Esquema distinto al confirmado: '+table)
 old=VHOST.read_bytes();text=old.decode()
 if text.count('</VirtualHost>')!=1 or 'ServerName '+HOST not in text:raise RuntimeError('Estructura de virtualhost inesperada.')
 if not re.search(r'^\s*CustomLog\s+\S+\s+uiat_privado\s*$',text,re.M):raise RuntimeError('Logs privados de UIAT no confirmados.')
 baseline={p:code(p) for p in ['/login.php','/api/v1/buscar_expedientes.php','/.env','/.env.save','/.env.local','/mcp-demo']}
 if baseline['/login.php']!='200' or baseline['/api/v1/buscar_expedientes.php']!='401' or any(baseline[p]!='403' for p in ['/.env','/.env.save','/.env.local']):raise RuntimeError('Estado previo no confirmado.')
 for route in ROUTES:
  # Existing route or a catch-all 200/redirect must be reviewed, not overwritten.
  if code(route)!='404':raise RuntimeError('Ruta propuesta ya responde o es interceptada: '+route)
 www=pwd.getpwnam('www-data')
 run(['apache2ctl','configtest'])
 with tarfile.open(package) as t:
  entries=t.getmembers();names=[m.name for m in entries]
  if len(set(names))!=len(names):raise RuntimeError('Entradas duplicadas.')
  for m in entries:
   if not m.isfile() or P(m.name).is_absolute() or '..' in P(m.name).parts or not m.name.startswith('connector/'):raise RuntimeError('Archivo de paquete no admitido.')
  if not {'connector/public.php','connector/deploy/factory.php','connector/deploy/provision.php','connector/vendor/autoload.php'}<=set(names):raise RuntimeError('Paquete incompleto.')
  if not BACKUPS.exists():private_dir(BACKUPS)
  if BACKUPS.is_symlink() or BACKUPS.stat().st_uid!=0 or stat.S_IMODE(BACKUPS.stat().st_mode)!=0o700:raise RuntimeError('Respaldo privado inesperado.')
  stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-'+secrets.token_hex(3)
  backup=BACKUPS/stamp;private_dir(backup)
  attrs=[stat.S_IMODE(VHOST.stat().st_mode),VHOST.stat().st_uid,VHOST.stat().st_gid]
  atomic(backup/'vhost-original.conf',old,0o600)
  journal={'vhost_attrs':attrs,'created':{},'account_created':False,'conf_created':False,'baseline':baseline}
  def save():atomic(backup/'journal.json',json.dumps(journal,indent=2).encode(),0o600)
  save()
  try:
   private_dir(RELEASE,0,www.pw_gid,0o750);journal['created'][str(RELEASE)]=True;save()
   for m in entries:
    target=RELEASE/P(m.name).relative_to('connector')
    target.parent.mkdir(parents=True,exist_ok=True)
    atomic(target,t.extractfile(m).read(),0o640,0,www.pw_gid)
   for directory in RELEASE.rglob('*'):
    if directory.is_dir():os.chown(directory,0,www.pw_gid);directory.chmod(0o750)
   run(['php',str(RELEASE/'vendor/composer/platform_check.php')])
   for source in list((RELEASE/'src').glob('*.php'))+[RELEASE/'public.php',RELEASE/'deploy/factory.php',RELEASE/'deploy/provision.php']:run(['php','-l',str(source)])
   private_dir(PRIVATE,0,www.pw_gid,0o750);journal['created'][str(PRIVATE)]=True;save()
   private_dir(RUNTIME,www.pw_uid,www.pw_gid,0o700);journal['created'][str(RUNTIME)]=True;save()
   run(['php',str(RELEASE/'deploy/provision.php'),str(PRIVATE),str(RUNTIME)])
   for key in ['private.pem','public.pem']:os.chown(PRIVATE/key,0,www.pw_gid);(PRIVATE/key).chmod(0o640)
   os.chown(RUNTIME/'oauth.sqlite',www.pw_uid,www.pw_gid);(RUNTIME/'oauth.sqlite').chmod(0o600)
   atomic(RUNTIME/'audit.jsonl',b'',0o600,www.pw_uid,www.pw_gid)
   password=secrets.token_hex(32)
   sql("CREATE USER '"+ACCOUNT+"'@'localhost' IDENTIFIED BY '"+password+"';")
   journal['account_created']=True;save()
   for table,columns in GRANTS.items():sql('GRANT SELECT ('+','.join('`'+c+'`' for c in columns)+') ON `uiatnorte`.`'+table+"` TO '"+ACCOUNT+"'@'localhost';")
   origin='https://'+HOST
   settings={'private_dir':str(RUNTIME),'rate_key':secrets.token_hex(32),'dsn':'mysql:host=localhost;dbname=uiatnorte;charset=utf8mb4','db_user':ACCOUNT,'db_password':password,'issuer':origin+'/oauth-uiat','resource':origin+'/mcp-uiat','metadata':origin+'/.well-known/oauth-protected-resource/mcp-uiat','client_id':'uiat-chatgpt-readonly-v1','redirect':'https://chatgpt.com/connector_platform_oauth_redirect','encryption_key':secrets.token_hex(32)}
   atomic(PRIVATE/'settings.json',json.dumps(settings).encode(),0o640,0,www.pw_gid)
   atomic(PRIVATE/'factory.php',(RELEASE/'deploy/factory.php').read_bytes(),0o640,0,www.pw_gid)
   regex='^/(?:mcp-uiat|oauth-uiat/(?:authorize|reauth|token|revoke)|\\.well-known/(?:oauth-protected-resource/mcp-uiat|oauth-authorization-server/oauth-uiat))$'
   conf=('''# UIAT authenticated connector: installed DISABLED. Never enable without separate approval.
AliasMatch "REGEX" "/opt/uiat-chatgpt-connector/public.php"
SetEnvIf Request_URI "REGEX" UIAT_CONNECTOR_ENABLED=0 UIAT_CONNECTOR_PRIVATE_CONFIG=/etc/uiat-chatgpt-connector/factory.php
<Directory "/opt/uiat-chatgpt-connector">
    AllowOverride None
    Options -Indexes
    Require all denied
    <Files "public.php">
        Require all granted
        LimitRequestBody 16384
    </Files>
</Directory>
''').replace('REGEX',regex)
   atomic(CONF,conf.encode(),0o644);journal['conf_created']=True;journal['conf_sha']=digest(CONF);save()
   installed=text.replace('</VirtualHost>','    Include '+str(CONF)+'\n</VirtualHost>')
   atomic(VHOST,installed.encode(),*attrs);journal['installed_vhost_sha']=digest(VHOST);save()
   run(['apache2ctl','configtest']);run(['systemctl','reload','apache2'])
   for route in ROUTES:
    body=request(route,True);payload,status=body.rsplit('\n',1)
    if status!='503' or json.loads(payload)!={'error':'Conector deshabilitado.'}:raise RuntimeError('No se confirmó apagado: '+route)
   if any(code(p)!=v for p,v in baseline.items()):raise RuntimeError('Una ruta existente cambió de estado.')
   journal['completed']=True;save()
   print('INSTALADO Y APAGADO: siete rutas responden 503; demo, login y API conservan su estado previo.')
   print('Cuenta nueva de SELECT por columnas; claves, estado y auditoría privados. Sin consultas de expedientes ni personas.')
   print('Respaldo y reversión: '+str(backup))
   print('No activar todavía: faltan comprobación Apache/MariaDB con datos ficticios y conexión del cliente OAuth real.')
  except Exception:
   try:rollback(backup,automatic=True)
   except Exception:print('REVISIÓN MANUAL REQUERIDA. Respaldo: '+str(backup))
   raise

def main():
 parser=argparse.ArgumentParser(description=__doc__)
 group=parser.add_mutually_exclusive_group();group.add_argument('--instalar-apagado',action='store_true');group.add_argument('--revertir',type=P)
 args=parser.parse_args()
 if not args.instalar_apagado and not args.revertir:
  print('SOLO PLAN. No se ejecutó ninguna comprobación remota ni cambio.')
  print('Con aprobación: --instalar-apagado. Crea fuentes, cuenta SELECT y estado OAuth privados; añade rutas HTTPS deshabilitadas.')
  print('Reversión: --revertir /var/backups/uiat-chatgpt-connector/IDENTIFICADOR. No altera demo, API ni .env.')
  return
 if os.geteuid()!=0:raise RuntimeError('Ejecutar como root en el servidor autorizado, después de aprobación explícita.')
 os.umask(0o077)
 if args.revertir:rollback(args.revertir)
 else:install()
if __name__=='__main__':
 try:main()
 except Exception as e:print('DETENIDO: '+str(e));sys.exit(1)
