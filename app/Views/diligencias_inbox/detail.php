<?php
if (!$selected): ?>
<div class="inbox-empty"><strong>Sin diligencia seleccionada</strong><p>Selecciona una diligencia para consultar su expediente.</p></div>
<?php return; endif;
// Recheck the assignment before rendering the shared card, including AJAX requests.
$task = $repository->find((int)$selected['id']);
if (!$task) { http_response_code(404); throw new RuntimeException('La diligencia ya no está disponible en tu bandeja.'); }
// The existing card expects $id to be the case ID; never accept it from the request.
$id = (int)$task['accidente_id'];
$inboxHue = (($id % 360) * 137) % 360;
echo '<div class="inbox-case-group" style="--inbox-case-hue:'.$inboxHue.'">';
require __DIR__.'/../expedientes/card.php';
echo '</div>';
