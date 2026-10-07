<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/bootstrap/app.php';
$p=App\Database\Database::connection();
if(!in_array('--apply',$argv,true)){echo "Usar --apply para habilitar categoría opcional y añadir tipos generales.\n";exit;}
$p->exec('ALTER TABLE tipos_vehiculo MODIFY categoria_id INT NULL');
$p->exec('ALTER TABLE vehiculos MODIFY categoria_id INT NULL');
$names=['Automóvil','Station wagon','Camioneta rural','Camioneta SUV','Motocicleta','Camión','Microbús','Minibús','Ómnibus','Remolque','Semirremolque','Camioneta pickup','Trimoto de pasajeros','VMP','Bicicleta'];
try{
$p->exec('SET @rbac_migration=1');$p->beginTransaction();
$find=$p->prepare('SELECT id FROM tipos_vehiculo WHERE categoria_id IS NULL AND nombre=?');
$insert=$p->prepare('INSERT INTO tipos_vehiculo(categoria_id,codigo,nombre) VALUES(NULL,?,?)');
foreach($names as $i=>$name){$find->execute([$name]);if(!$find->fetchColumn())$insert->execute(['GENERAL-'.($i+1),$name]);}
$p->commit();echo "Tipos generales registrados; categorías opcionales habilitadas.\n";
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}finally{$p->exec('SET @rbac_migration=0');}
