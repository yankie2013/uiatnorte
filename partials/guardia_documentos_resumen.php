<?php
use App\Support\WorkspacePage as Page;
// Los datos generales se leen del accidente ya registrado.
$st=$pdo->prepare('SELECT * FROM accidentes WHERE id=?');$st->execute([$id]);$case=$st->fetch();
$st=$pdo->prepare('SELECT m.nombre FROM accidente_modalidad x JOIN modalidad_accidente m ON m.id=x.modalidad_id WHERE x.accidente_id=?');$st->execute([$id]);$modalities=$st->fetchAll(PDO::FETCH_COLUMN);
$st=$pdo->prepare('SELECT c.nombre FROM accidente_consecuencia x JOIN consecuencia_accidente c ON c.id=x.consecuencia_id WHERE x.accidente_id=?');$st->execute([$id]);$consequences=$st->fetchAll(PDO::FETCH_COLUMN);
$formatDate=static fn($date)=>$date?date('d/m/Y H:i',strtotime($date)):'Sin registrar';
$blocks=[
 ['🚘 Características del accidente','blue',['Modalidad'=>implode(', ',$modalities)?:'Sin registrar','Consecuencias'=>implode(', ',$consequences)?:'Sin registrar']],
 ['📅 Fechas y actuaciones','green',['Accidente'=>$formatDate($case['fecha_accidente']),'Comunicación'=>$formatDate($case['fecha_comunicacion']),'Intervención'=>$formatDate($case['fecha_intervencion'])]],
 ['📍 Ubicación y dirección','purple',['Lugar'=>$case['lugar']?:'Sin registrar','Referencia'=>$case['referencia']?:'Sin registrar','Registro SIDPOL'=>$case['registro_sidpol']?:'Sin registrar']]
];
echo '<div class="guardia-summary-highlights">';
foreach([['📁','Registro SIDPOL',$case['registro_sidpol']?:'Sin registrar'],['🚘','Vehículos',count($vehicles)],['👥','Participantes',count($people)]] as [$icon,$label,$value]){
 echo '<div class="guardia-summary-highlight"><span class="guardia-highlight-icon" aria-hidden="true">'.$icon.'</span><div><span>'.Page::escape($label).'</span><strong>'.Page::escape((string)$value).'</strong></div></div>';
}
echo '</div>';
$icons=['Modalidad'=>'🚘','Consecuencias'=>'📋','Accidente'=>'🗓️','Comunicación'=>'📞','Intervención'=>'🚓','Lugar'=>'📍','Referencia'=>'📌','Registro SIDPOL'=>'📁'];
echo '<div class="guardia-case-summary">';
foreach($blocks as [$title,$color,$fields]){
 echo '<article class="guardia-summary-block guardia-summary-'.$color.'"><h3>'.Page::escape($title).'</h3><dl>';
 foreach($fields as $label=>$value)echo '<div class="guardia-summary-field"><dt><span aria-hidden="true">'.$icons[$label].'</span> '.Page::escape($label).'</dt><dd>'.Page::escape($value).'</dd></div>';
 echo '</dl></article>';
}
echo '</div>';
