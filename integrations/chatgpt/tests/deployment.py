#!/usr/bin/env python3
"""Installer fault-injection checks in a temporary filesystem. No Apache/DB/network commands run."""
import contextlib,hashlib,importlib.util,io,json,os,pathlib,pwd,tarfile,tempfile,unittest
from unittest.mock import patch
P=pathlib.Path
SOURCE=P(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('deployment',SOURCE/'deploy/install.py')
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
class Deployment(unittest.TestCase):
 def fixture(self,base):
  for name in ['PROJECT','RELEASE','PRIVATE','RUNTIME','CONF','VHOST','BACKUPS']:
   setattr(m,name,base/name)
  m.PROJECT.mkdir();(m.PROJECT/'app/Services').mkdir(parents=True);(m.PROJECT/'app/Services/LoginService.php').write_text('fixture')
  original='''<VirtualHost *:443>
ServerName uiatnorte.korkaystore.com
CustomLog /tmp/fixture-access.log uiat_privado
</VirtualHost>
'''
  m.VHOST.write_text(original)
  # Installer resolves enabled virtualhost to its configured target; substitute only that lookup.
  original_resolve=P.resolve
  def resolve(p,*args,**kwargs):
   if str(p)=='/etc/apache2/sites-enabled/uiatnorte-le-ssl.conf':return m.VHOST
   return original_resolve(p,*args,**kwargs)
  package=base/'uiat-conector-apagado.tar.gz'
  with tarfile.open(package,'w:gz') as t:
   contents={'public.php':b'<?php // disabled','deploy/factory.php':b'<?php','deploy/provision.php':b'<?php','vendor/autoload.php':b'<?php','vendor/composer/platform_check.php':b'<?php','src/x.php':b'<?php'}
   for path,data in contents.items():
    info=tarfile.TarInfo('connector/'+path);info.size=len(data);t.addfile(info,io.BytesIO(data))
  m.PACKAGE_SHA=hashlib.sha256(package.read_bytes()).hexdigest()
  calls=[];fault={'enabled':False};created=set()
  def sql(q):
   calls.append(q)
   if 'information_schema.COLUMNS' in q:
    table=q.split("TABLE_NAME='")[1].split("'")[0];return '\n'.join(m.GRANTS[table])
   if 'mysql.user' in q:return '0'
   if q.startswith('CREATE USER'):created.add(m.ACCOUNT)
   if q.startswith('DROP USER'):created.discard(m.ACCOUNT)
   return ''
  def run(args,data=None):
   if args[0]=='git':return '567cccf1'
   if args[:2]==['apache2ctl','-M']:return 'php_module alias_module'
   if args[:2]==['apache2ctl','configtest'] and fault['enabled'] and m.CONF.exists():
    fault['enabled']=False;raise RuntimeError('Injected Apache rejection')
   if args[0]=='php' and len(args)>1 and args[1].endswith('/provision.php'):
    for key in ['private.pem','public.pem']:(m.PRIVATE/key).write_text('fake-key')
    (m.RUNTIME/'oauth.sqlite').touch()
   return ''
  def code(path):
   if path=='/login.php':return '200'
   if path.startswith('/api/'):return '401'
   if path.startswith('/.env'):return '403'
   if path=='/mcp-demo':return '405'
   return '404'
  actual_digest=m.digest
  def digest(p):
   if p==m.PROJECT/'app/Services/LoginService.php':return '9dafdea364b28526a3da65f2b8a5fd44392f740e620fe4fdb3b56b1f0f8f5ec6'
   return actual_digest(p)
  def request(path,body=False):return '{"error":"Conector deshabilitado."}\n503'
  return original,calls,fault,created,[patch.object(m,'__file__',str(base/'install.py')),patch.object(m,'sql',sql),patch.object(m,'run',run),patch.object(m,'code',code),patch.object(m,'digest',digest),patch.object(m,'request',request),patch.object(P,'resolve',resolve)]
 def perform(self,failure=False):
  with tempfile.TemporaryDirectory() as d,contextlib.ExitStack() as stack:
   base=P(d);original,calls,fault,created,mocks=self.fixture(base)
   for item in mocks:stack.enter_context(item)
   stack.enter_context(contextlib.redirect_stdout(io.StringIO()))
   fault['enabled']=failure
   if failure:
    with self.assertRaisesRegex(RuntimeError,'Injected Apache'):m.install()
    self.assertEqual(m.VHOST.read_text(),original);self.assertFalse(m.CONF.exists());self.assertFalse(created)
    self.assertTrue(any(m.BACKUPS.glob('*/retirado-*')))
   else:
    m.install();self.assertIn('UIAT_CONNECTOR_ENABLED=0',m.CONF.read_text())
    self.assertEqual((m.PRIVATE/'settings.json').stat().st_mode&0o777,0o640)
    self.assertEqual(m.RUNTIME.stat().st_mode&0o777,0o700)
    self.assertEqual((m.RELEASE/'public.php').stat().st_mode&0o777,0o640)
    self.assertTrue(created)
    backup=next(m.BACKUPS.iterdir());m.rollback(backup)
    self.assertEqual(m.VHOST.read_text(),original);self.assertFalse(created);self.assertFalse(m.CONF.exists())
   self.assertFalse(any('SELECT *' in q or 'pass_hash' in q for q in calls))
   self.assertEqual(sum(q.startswith('GRANT SELECT (') for q in calls),len(m.GRANTS))
 def test_disabled_install_and_explicit_rollback(self):self.perform()
 def test_apache_rejection_restores_original(self):self.perform(True)
 def test_default_is_plan_only(self):
  with patch('sys.argv',['install.py']),patch.object(m,'install',side_effect=AssertionError('must not run')),contextlib.redirect_stdout(io.StringIO()):m.main()
if __name__=='__main__':
 if os.geteuid()!=0:raise SystemExit('Run in disposable root container; tests use temporary paths only.')
 unittest.main()
