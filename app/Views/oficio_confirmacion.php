<link rel="stylesheet" href="assets/css/oficio-confirmacion.css?v=<?= filemtime(__DIR__.'/../../assets/css/oficio-confirmacion.css') ?>">
<dialog id="oficio-confirmacion" aria-labelledby="oficio-confirmacion-titulo" data-official-years="<?= h(json_encode(array_column($ctx['oficial_anos'],'nombre','id'), JSON_UNESCAPED_UNICODE)) ?>">
  <div class="oficio-review-panel">
    <header><h2 id="oficio-confirmacion-titulo">Revisar oficio</h2><button type="button" class="btn" data-review-cancel aria-label="Cerrar resumen">×</button></header>
    <p>Revisa los datos antes de guardar. Puedes cancelar para continuar modificándolos.</p>
    <dl id="oficio-review-summary"></dl>
    <footer>
      <button type="button" class="btn" data-review-cancel>Cancelar</button>
      <button type="button" class="btn" data-review-save="download">Guardar y descargar</button>
      <button type="button" class="btn primary" data-review-save="save">Guardar</button>
    </footer>
  </div>
</dialog>
<script src="assets/js/oficio-confirmacion.js?v=<?= filemtime(__DIR__.'/../../assets/js/oficio-confirmacion.js') ?>"></script>
