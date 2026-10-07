<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$options=getopt('', ['database:', 'apply']);
$expected=$options['database']??'';
if(!is_string($expected)||$expected===''){fwrite(STDERR,"Uso: php docs/scripts/desplegar_guardia_actualizaciones.php --database=NOMBRE [--apply]\n");exit(2);}
require __DIR__.'/../../bootstrap/app.php';
$pdo=App\Database\Database::connection();
$database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
if($expected!==$database){fwrite(STDERR,"Bloqueado: la base conectada no coincide con --database.\n");exit(2);}
function runGuardiaMigration(string $file,array $args):void{
 $process=proc_open(array_merge([PHP_BINARY,__DIR__.'/'.$file],$args),[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes,dirname(__DIR__,2));
 if(!is_resource($process)||proc_close($process)!==0)throw new RuntimeException('Migración detenida: '.$file);
}
try{
 echo 'Base de destino: '.$database."\n";
 foreach(['usuarios','accidentes','comunicaciones_guardia','expediente_transferencias','auditoria','documento_lc','documento_vehiculo','enlace_interes'] as $table)$pdo->query('SELECT 1 FROM `'.$table.'` LIMIT 1');
 $pdo->query('SHOW CREATE FUNCTION rbac_guardia_draft')->fetch();
 foreach(['rbac_documento_lc_insert','rbac_documento_lc_update','rbac_documento_vehiculo_insert','rbac_documento_vehiculo_update'] as $trigger)$pdo->query('SHOW CREATE TRIGGER `'.$trigger.'`')->fetch();
 // Comprueba todas las dependencias de las copias históricas sin escribir.
 runGuardiaMigration('migrar_persona_snapshots.php',['--database='.$database]);
 echo "Orden: copias históricas → esquema y auditoría → datos opcionales → documentos → plazo de edición → licencias propias → enlace MTC.\n";
 if(!isset($options['apply'])){echo "Comprobación terminada sin modificar datos. Tras un respaldo, repita con --apply.\n";exit;}
 runGuardiaMigration('migrar_persona_snapshots.php',['--database='.$database,'--apply']);
 runGuardiaMigration('migrar_multiusuario.php',['--schema-only','--apply']);
 $pdo->exec(file_get_contents(__DIR__.'/../sql/2026-10-06_personas_registro_manual.sql'));
 runGuardiaMigration('migrar_guardia_documentos.php',[]);
 runGuardiaMigration('migrar_guardia_plazo_edicion.php',[]);
 runGuardiaMigration('migrar_guardia_licencia_propia.php',[]);
 $pdo->beginTransaction();
 try{
  $pdo->exec('SET @rbac_migration=1');
  $pdo->prepare('UPDATE enlace_interes SET url=? WHERE nombre=?')->execute(['https://licencias.mtc.gob.pe/#/index','Licencias MTC']);
  $pdo->prepare("INSERT INTO enlace_interes(categoria,nombre,url,orden,activo) SELECT 'TRANSITO',?,?,0,1 WHERE NOT EXISTS(SELECT 1 FROM enlace_interes WHERE nombre=?)")->execute(['Licencias MTC','https://licencias.mtc.gob.pe/#/index','Licencias MTC']);
  $pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}finally{$pdo->exec('SET @rbac_migration=NULL');}
 foreach(['num_doc','sexo','fecha_nacimiento'] as $field){
  $column=$pdo->query('SHOW COLUMNS FROM personas LIKE '.$pdo->quote($field))->fetch(PDO::FETCH_ASSOC);
  if(($column['Null']??'')!=='YES')throw new RuntimeException('Columna aún obligatoria: '.$field);
 }
 echo "Actualización terminada. Verifique el registro manual, documentos, edición por 12 horas y cancelación de envíos pendientes.\n";
}catch(Throwable $e){fwrite(STDERR,"DETENIDO: ".$e->getMessage()."\nNo continúe con migraciones posteriores hasta resolver este error.\n");exit(1);}
