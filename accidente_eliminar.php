<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';
header('Content-Type: text/html; charset=utf-8');

if (!defined('APP_DEBUG')) define('APP_DEBUG', true);
if (APP_DEBUG) { ini_set('display_errors','1'); error_reporting(E_ALL); }

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

/* =========================
   OBTENER ID (POST o GET)
========================= */
$id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
if ($id <= 0) {
  header('Location: accidente_listar.php?err=sin_id'); exit;
}

try {
  $pdo->beginTransaction();

  /* ==========================================================
     0) BORRAR "NIETOS" QUE BLOQUEAN FK
        - documento_vehiculo cuelga de involucrados_vehiculos
        - (opcional) diligencias_conductor cuelga de involucrados_personas
  ========================================================== */
  // documento_vehiculo -> involucrados_vehiculos -> accidentes
  try {
    $pdo->prepare("
      DELETE dv
      FROM documento_vehiculo dv
      INNER JOIN involucrados_vehiculos iv ON iv.id = dv.involucrado_vehiculo_id
      WHERE iv.accidente_id = ?
    ")->execute([$id]);
  } catch (Throwable $e) {
    if (APP_DEBUG) { /* tabla podría no existir en algunos entornos */ }
  }

  // diligencias_conductor -> involucrados_personas -> accidentes (si existe)
  try {
    $pdo->prepare("
      DELETE dc
      FROM diligencias_conductor dc
      INNER JOIN involucrados_personas ip ON ip.id = dc.inv_per_id
      WHERE ip.accidente_id = ?
    ")->execute([$id]);
  } catch (Throwable $e) {
    if (APP_DEBUG) { /* tabla opcional */ }
  }

  /* ==========================================================
     1) BORRAR EN TODAS LAS TABLAS QUE REFERENCIEN accidentes.id
  ========================================================== */
  $sqlFK = "
    SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND REFERENCED_TABLE_NAME  = 'accidentes'
      AND REFERENCED_COLUMN_NAME = 'id'
  ";
  $fks = $pdo->query($sqlFK)->fetchAll(PDO::FETCH_ASSOC);

  foreach ($fks as $fk) {
    $tn = $fk['TABLE_NAME'];
    $cn = $fk['COLUMN_NAME'];
    // Eliminar filas hijas que apunten al accidente
    $stmt = $pdo->prepare("DELETE FROM `$tn` WHERE `$cn` = ?");
    $stmt->execute([$id]);
  }

  /* ==========================================================
     2) BORRADOS MANUALES "POSIBLES" (por si no hay FKs formales)
        No falla si no existen; útil como respaldo.
  ========================================================== */
  $posibles = [
    ['accidente_modalidad',    'accidente_id'],
    ['accidente_consecuencia', 'accidente_id'],
    ['accidente_unidad',       'accidente_id'],
    ['participacion_persona',  'accidente_id'], // si existiera
    ['conjunto_vehicular',     'accidente_id'],
    ['involucrados_personas',  'accidente_id'],
    ['involucrados_vehiculos', 'accidente_id'],
  ];
  foreach ($posibles as [$tabla,$col]) {
    try {
      $pdo->prepare("DELETE FROM `$tabla` WHERE `$col` = ?")->execute([$id]);
    } catch (Throwable $e) {
      // Silencioso: puede que la tabla/columna no exista en este esquema
    }
  }

  // Detalle dependiente de conjunto_vehicular (si existe)
  try {
    $pdo->prepare("
      DELETE cvd
      FROM conjunto_vehicular_detalle cvd
      INNER JOIN conjunto_vehicular cv ON cvd.conjunto_id = cv.id
      WHERE cv.accidente_id = ?
    ")->execute([$id]);
  } catch (Throwable $e) {}

  /* =========================
     3) BORRAR EL ACCIDENTE
  ========================= */
  $stmt = $pdo->prepare("DELETE FROM accidentes WHERE id = ?");
  $stmt->execute([$id]);

  $pdo->commit();
  header('Location: accidente_listar.php?ok=1'); exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  if (APP_DEBUG) {
    header('Location: accidente_listar.php?err='.rawurlencode($e->getMessage())); exit;
  }
  header('Location: accidente_listar.php?err=1'); exit;
}
