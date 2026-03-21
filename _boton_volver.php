<?php
// _boton_volver.php
// Botón reutilizable para regresar a Dato_General_accidente.php
$accidente_id = isset($accidente_id) ? (int)$accidente_id : (int)($_GET['accidente_id'] ?? 0);
$btn_text = $btn_text ?? '⬅️ Volver a Datos Generales';
$target = "/uiatnorte/Dato_General_accidente.php?accidente_id=" . $accidente_id;
?>
<div class="volver-container">
  <a href="<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>" class="btn-volver">
    <?= htmlspecialchars($btn_text, ENT_QUOTES, 'UTF-8') ?>
  </a>
</div>

<style>
.volver-container{
  text-align:center;          /* ⬅️ Centra horizontalmente */
  margin:25px 0 30px 0;       /* Espaciado arriba y abajo */
}

.btn-volver{
  display:inline-block;
  padding:12px 28px;
  font-weight:700;
  border-radius:30px;
  background: linear-gradient(180deg,#f7c843,#eab531);
  color:#111;
  text-decoration:none;
  box-shadow:0 8px 18px rgba(234,181,49,0.25);
  border:none;
  transition:transform .12s ease, box-shadow .12s ease;
  font-family: "Helvetica Neue", Arial, sans-serif;
}
.btn-volver:hover{
  transform:scale(1.03);
  box-shadow:0 10px 20px rgba(0,0,0,0.1);
}
.btn-volver:active{
  transform:scale(0.98);
  box-shadow:0 5px 10px rgba(0,0,0,0.08);
}
</style>