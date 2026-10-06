<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
$p=App\Database\Database::connection();
$r=new App\Repositories\OficioRepository($p);
$columns=$p->query('SHOW COLUMNS FROM oficios_activos')->fetchAll(PDO::FETCH_COLUMN);
$legacy=array_values(array_diff($columns,['gestion','encargado_id','comisaria_id']));
$select=implode(',',array_map(static fn($column)=>'`'.$column.'`',$legacy));
// Session-local temporary table shadows the view without altering persisted data.
$p->exec("CREATE TEMPORARY TABLE oficios_activos AS SELECT $select FROM oficios");
try {
    (new ReflectionProperty($r,'columnCache'))->setValue($r,['oficios_activos'=>$legacy]);
    $rows=$r->search([]);
    if (!$rows) throw new RuntimeException('Se requieren oficios para verificar.');
    $id=(int)$rows[0]['id'];
    if ($rows[0]['gestion']!==0 || $rows[0]['encargado_id']!==null) throw new RuntimeException('Valores de compatibilidad incorrectos.');
    if (!$r->find($id) || !$r->detail($id)) throw new RuntimeException('Falta detalle compatible.');
    $manager=(int)$p->query('SELECT responsable_id FROM accidentes WHERE responsable_id IS NOT NULL LIMIT 1')->fetchColumn();
    $r->search(['encargado_id'=>$manager]);
    echo "PASS: listado, filtro de encargado y detalles sin columnas de Gestión.\n";
} finally {
    $p->exec('DROP TEMPORARY TABLE oficios_activos');
}
