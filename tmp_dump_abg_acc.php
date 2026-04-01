<?php
require __DIR__ . '/db.php';
$st = $pdo->prepare("SELECT a.persona_rep_nom, a.condicion_representado, a.domicilio_procesal, a.email, a.registro, a.colegiatura, ac.lugar, ac.referencia FROM abogado a JOIN accidentes ac ON ac.id = a.accidente_id WHERE a.id = 15 LIMIT 1");
$st->execute();
$row = $st->fetch(PDO::FETCH_ASSOC);
foreach ($row as $k => $v) {
  echo $k . ' => ' . $v . PHP_EOL;
  echo bin2hex((string)$v) . PHP_EOL;
}
