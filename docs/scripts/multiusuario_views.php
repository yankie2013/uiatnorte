<?php
if(PHP_SAPI!=='cli' || !isset($p)){http_response_code(404);exit;}
$children=['abogados','accidente_analisis_imagenes','accidente_consecuencia','accidente_modalidad','citacion','diligencias_pendientes','documento_occiso','documento_rml','documentos_recibidos','familiar_fallecido','involucrados_personas','involucrados_vehiculos','itp','Manifestacion','oficios','policial_interviniente','propietario_vehiculo','actas','actas_visualizacion'];
foreach($children as $table) $p->exec("CREATE OR REPLACE VIEW `{$table}_activos` AS SELECT d.* FROM `$table` d LEFT JOIN accidentes a ON a.id=d.accidente_id WHERE d.accidente_id IS NULL OR a.eliminado_en IS NULL");
$p->exec('CREATE OR REPLACE VIEW documento_vehiculo_activos AS SELECT d.* FROM documento_vehiculo d LEFT JOIN involucrados_vehiculos iv ON iv.id=d.involucrado_vehiculo_id LEFT JOIN accidentes a ON a.id=iv.accidente_id WHERE a.eliminado_en IS NULL');
