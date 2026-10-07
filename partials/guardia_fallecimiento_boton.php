<?php
use App\Support\WorkspacePage as Page;
$st=$pdo->prepare('SELECT d.tipo,h.nombre hospital FROM guardia_persona_fallecimiento d LEFT JOIN guardia_hospitales h ON h.id=d.hospital_id WHERE d.involucrado_persona_id=?');$st->execute([$person['involucrado_id']]);$death=$st->fetch();
echo '<div class="guardia-document-actions">';
if($editable)echo '<a class="button guardia-document-open guardia-document-death" data-title="Datos de fallecimiento" href="guardia_fallecimiento.php?involucrado_id='.(int)$person['involucrado_id'].'&amp;embed=1"><span class="guardia-document-icon" aria-hidden="true">📍</span><span class="guardia-document-label">Lugar de fallecimiento</span><span class="guardia-document-arrow" aria-hidden="true">↗</span></a>';
$description=$death?($death['tipo']==='lugar_hechos'?'Falleció en el lugar de los hechos':'Falleció en '.$death['hospital']):'Lugar de fallecimiento pendiente';
echo '<span class="guardia-document-status'.($death?' guardia-document-complete':'').'">'.($death?'<span aria-hidden="true">✓</span> ':'').Page::escape($description).'</span></div>';
