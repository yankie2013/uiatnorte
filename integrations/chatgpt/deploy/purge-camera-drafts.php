<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||$argv!==[$argv[0],'--limpiar'])exit(1);
ini_set('display_errors','0');
try {
    require dirname(__DIR__).'/vendor/autoload.php';
    require dirname(__DIR__).'/src/OAuthCore.php';
    require dirname(__DIR__).'/src/CameraDrafts.php';
    (new Uiat\Connector\CameraDraftStore('/var/lib/uiat-chatgpt-connector/borradores'))->purgeExpired();
    echo "Limpieza de borradores caducados completada.\n";
} catch(Throwable $e){fwrite(STDERR,"Limpieza no completada; revisar permisos y almacenamiento privado.\n");exit(1);}
