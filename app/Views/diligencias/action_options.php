<?php
$documentOptions = \App\Support\DiligenciaDocumentLink::optionsForAction(trim((string)($row['accion'] ?? '')));
?>
<section class="resolve-panel" aria-label="Opciones para trabajar la diligencia">
  <h2 class="resolve-title">¿Qué deseas hacer con esta diligencia?</h2>
  <?php if ($documentOptions): ?>
  <p class="muted">Elige la opción correspondiente para crear un documento o vincular uno del expediente.</p>
  <div class="quick-actions">
    <?php foreach ($documentOptions as $documentMode=>$documentLabel):
      $documentRoute = $documentMode === 'actas' ? 'diligenciapendiente_actas.php' : 'diligenciapendiente_ver.php';
      $documentUrl = $documentRoute.'?'.http_build_query(['id'=>(int)$id,'embed'=>$embed?1:0,'mode'=>$documentMode,'return_to'=>$returnTo]);
    ?>
    <a class="btn primary" href="<?= h($documentUrl) ?>"><?= h($documentLabel) ?> →</a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="muted">Esta diligencia no tiene una acción definida. Indica si corresponde solicitar, remitir, citar o realizar para mostrar sus opciones.</p>
  <a class="btn" href="<?= h('diligenciapendiente_editar.php?'.http_build_query(['id'=>(int)$id,'embed'=>$embed?1:0,'return_to'=>$returnTo])) ?>">Definir acción de la diligencia</a>
  <?php endif; ?>
</section>
