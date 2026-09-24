<?php
if(PHP_SAPI!=='cli' || !isset($p)){http_response_code(404);exit;}
foreach(['must_change_password'=>'TINYINT(1) NOT NULL DEFAULT 0','auth_version'=>'INT NOT NULL DEFAULT 0'] as $name=>$definition) {
    $st=$p->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'usuarios\' AND column_name=?');$st->execute([$name]);
    if(!$st->fetchColumn())$p->exec("ALTER TABLE usuarios ADD COLUMN `$name` $definition");
}
if (empty($schemaOnly)) $p->exec("UPDATE usuarios SET cip=NULL WHERE TRIM(COALESCE(cip,''))=''");
$duplicates=$p->query('SELECT cip FROM usuarios WHERE cip IS NOT NULL GROUP BY cip HAVING COUNT(*)>1')->fetchAll(PDO::FETCH_COLUMN);
if($duplicates)throw new RuntimeException('Hay CIP duplicados. Corríjalos antes de habilitar el acceso por CIP.');
$index=$p->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='usuarios' AND index_name='uq_usuarios_cip'")->fetchColumn();
if(!$index)$p->exec('ALTER TABLE usuarios ADD UNIQUE KEY uq_usuarios_cip(cip)');
