<?php
/* ===========================================================
   INFORME ATROPELLO - UIAT NORTE (DOCX con PhpWord)
   Requiere: composer require phpoffice/phpword
   Parametro: ?accidente_id=ID  (alias ?id)
   Plantilla: /plantillas/informe_atropello.docx
   =========================================================== */

require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';

$DEBUG = isset($_GET['debug']) && $_GET['debug']=='1';
if ($DEBUG) {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', 0);
  ini_set('display_startup_errors', 0);
}
function dbg($msg){ if(isset($_GET['debug']) && $_GET['debug']=='1'){ while (ob_get_level()) { @ob_end_clean(); } header('Content-Type:text/plain; charset=utf-8'); echo $msg; exit; } }
set_exception_handler(function($e){
  while (ob_get_level()) { @ob_end_clean(); }
  if (isset($_GET['debug']) && $_GET['debug']=='1') {
    header('Content-Type:text/plain; charset=utf-8');
    echo "[EXCEPCION] ".$e->getMessage()."\nEn: ".$e->getFile().":".$e->getLine()."\n";
    echo "Trace:\n".$e->getTraceAsString();
  } else {
    http_response_code(500);
    echo "Error interno.";
  }
  exit;
});

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

if (is_file(__DIR__.'/vendor/autoload.php')) { require __DIR__.'/vendor/autoload.php'; }
if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && is_file(__DIR__.'/PHPWord-1.4.0/vendor/autoload.php')) {
  require __DIR__.'/PHPWord-1.4.0/vendor/autoload.php';
}
use PhpOffice\PhpWord\TemplateProcessor;

if (!class_exists(TemplateProcessor::class)) {
  http_response_code(500);
  exit('PhpWord no esta disponible para generar el DOCX.');
}

/* ---------- Parametro ---------- */
$accidente_id = (int)($_GET['accidente_id'] ?? $_GET['id'] ?? 0);
if ($accidente_id <= 0) { http_response_code(400); echo "Falta ?accidente_id"; exit; }
/* ---------- Helpers ---------- */
function v($s){ return ($s!==null && $s!=='') ? $s : '—'; }
function mes3($ts){
  return strtoupper(strtr(date('M',$ts),[
    'Jan'=>'ENE','Feb'=>'FEB','Mar'=>'MAR','Apr'=>'ABR','May'=>'MAY','Jun'=>'JUN',
    'Jul'=>'JUL','Aug'=>'AGO','Sep'=>'SEP','Oct'=>'OCT','Nov'=>'NOV','Dec'=>'DIC'
  ]));
}
function ymd_pe($dt){ if(!$dt) return '—'; $ts=strtotime($dt); if(!$ts) return '—'; return date('d',$ts).mes3($ts).date('Y',$ts); }
function hora_pe($dt){ if(!$dt) return '—'; $ts=strtotime($dt); if(!$ts) return '—'; return date('H:i',$ts); }
function fecha_corta($dt){ if(!$dt) return '—'; $ts=strtotime($dt); if(!$ts) return '—'; return date('d/m/Y',$ts); }
function edad_from($fecha){ if(!$fecha) return '—'; $ts=strtotime($fecha); if(!$ts) return '—'; $hoy=new DateTime('today'); $n=DateTime::createFromFormat('Y-m-d',date('Y-m-d',$ts)); if(!$n) return '—'; return $n->diff($hoy)->y; }
function nombre_completo($n='',$apep='',$apem=''){ return trim(($apep?:'').' '.($apem?:'').' '.($n?:'')); }

/* Helpers documentos por persona */
function get_dosaje(PDO $pdo, $persona_id){
  if (!$persona_id) return [];
  $q=$pdo->prepare("SELECT * FROM documento_dosaje WHERE persona_id=:p ORDER BY id DESC LIMIT 1");
  $q->execute([':p'=>$persona_id]); return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}
function get_rml(PDO $pdo, $persona_id, $accidente_id){
  if (!$persona_id || !$accidente_id) return [];
  $q=$pdo->prepare("SELECT * FROM documento_rml WHERE persona_id=:p AND accidente_id=:a ORDER BY id DESC LIMIT 1");
  $q->execute([':p'=>$persona_id, ':a'=>$accidente_id]); return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}
function get_doc_occiso(PDO $pdo, $persona_id, $accidente_id){
  if (!$persona_id || !$accidente_id) return [];
  $q=$pdo->prepare("SELECT * FROM documento_occiso WHERE persona_id=:p AND accidente_id=:a ORDER BY id DESC LIMIT 1");
  $q->execute([':p'=>$persona_id, ':a'=>$accidente_id]); return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}

/* Abogados: intenta accidente+persona y si no hay, persona sola */
function get_abogado(PDO $pdo, $accidente_id, $persona_id){
  if(!$persona_id) return [];
  // Primero: abogado vinculado al accidente y persona
  $q = $pdo->prepare("SELECT * FROM abogados WHERE accidente_id=:a AND persona_id=:p ORDER BY id DESC LIMIT 1");
  $q->execute([':a'=>$accidente_id, ':p'=>$persona_id]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if($row) return $row;
  // Segundo: último abogado de la persona (sin filtrar por accidente)
  $q2 = $pdo->prepare("SELECT * FROM abogados WHERE persona_id=:p ORDER BY id DESC LIMIT 1");
  $q2->execute([':p'=>$persona_id]);
  return $q2->fetch(PDO::FETCH_ASSOC) ?: [];
}
/* ===========================================================
   1) ACCIDENTE
=========================================================== */
$sqlAcc = "
  SELECT a.*,
         d.nombre  AS dep_nom,
         p.nombre  AS prov_nom,
         t.nombre  AS distrito_nombre,
         c.nombre  AS comisaria_nombre,
         fa.nombre AS fiscalia_nombre,
         CONCAT(fi.nombres,' ',fi.apellido_paterno,' ',fi.apellido_materno) AS fiscal_nombre
  FROM accidentes a
  LEFT JOIN ubigeo_departamento d ON d.cod_dep=a.cod_dep
  LEFT JOIN ubigeo_provincia  p ON p.cod_dep=a.cod_dep AND p.cod_prov=a.cod_prov
  LEFT JOIN ubigeo_distrito   t ON t.cod_dep=a.cod_dep AND t.cod_prov=a.cod_prov AND t.cod_dist=a.cod_dist
  LEFT JOIN comisarias        c ON c.id=a.comisaria_id
  LEFT JOIN fiscalia          fa ON fa.id=a.fiscalia_id
  LEFT JOIN fiscales          fi ON fi.id=a.fiscal_id
  WHERE a.id=:id
";
$st=$pdo->prepare($sqlAcc);
$st->execute([':id'=>$accidente_id]);
$ACC = $st->fetch(PDO::FETCH_ASSOC);
if(!$ACC){ http_response_code(404); echo "Accidente no encontrado"; exit; }

/* Modalidades (pueden ser varias) */
$q = $pdo->prepare("
  SELECT m.nombre
    FROM accidente_modalidad am
    JOIN modalidad_accidente m ON m.id=am.modalidad_id
   WHERE am.accidente_id=:id
   ORDER BY m.nombre
");
$q->execute([':id'=>$accidente_id]);
$MODS = implode(', ', $q->fetchAll(PDO::FETCH_COLUMN)) ?: '—';

/* Consecuencias (si existe tabla relacional, sino usa campo del accidente) */
$CONS = '—';
try{
  $qc=$pdo->prepare("
    SELECT c.nombre
      FROM accidente_consecuencia ac
      JOIN consecuencia_accidente c ON c.id=ac.consecuencia_id
     WHERE ac.accidente_id=:id
     ORDER BY c.nombre
  ");
  $qc->execute([':id'=>$accidente_id]);
  $CONS = implode(', ', $qc->fetchAll(PDO::FETCH_COLUMN)) ?: v($ACC['consecuencia'] ?? $ACC['consecuencia_nombre'] ?? '—');
}catch(Throwable $e){
  $CONS = v($ACC['consecuencia'] ?? $ACC['consecuencia_nombre'] ?? '—');
}

/* ===========================================================
   2) CONDUCTOR + VEHÍCULO (con marca/modelo/carrocería/categoría)
=========================================================== */
/* ===========================================================
   2) CONDUCTOR + VEHÍCULO (con fallback)
=========================================================== */
try {
  // Consulta completa (usa marca/modelo/carrocería/categoría)
$sqlConductor = "
  SELECT
      ip.id AS inv_id, ip.*, pr.Nombre AS rol_nombre,
      p.id AS persona_id, p.*,

      v.id AS vehiculo_id, v.placa, v.color, v.anio,
      v.largo_mm, v.ancho_mm, v.alto_mm,

      -- Catálogos de vehículo
      tv.codigo  AS tipo_codigo,
      tv.nombre  AS tipo_nombre,
      cat.codigo AS categoria_codigo,
      cat.descripcion AS categoria_descripcion,
      car.nombre AS carroceria_nombre,
      mar.nombre AS veh_marca_nombre,
      modv.nombre AS veh_modelo_nombre

  FROM involucrados_personas ip
  JOIN personas               p    ON p.id = ip.persona_id
  LEFT JOIN vehiculos         v    ON v.id = ip.vehiculo_id

  -- JOINS de catálogos según tu estructura
  LEFT JOIN tipos_vehiculo        tv   ON tv.id   = v.tipo_id
  LEFT JOIN categoria_vehiculos   cat  ON cat.id  = v.categoria_id OR cat.id = tv.categoria_id
  LEFT JOIN carroceria_vehiculo   car  ON car.id  = v.carroceria_id
  LEFT JOIN marcas_vehiculo       mar  ON mar.id  = v.marca_id
  LEFT JOIN modelos_vehiculo      modv ON modv.id = v.modelo_id

  LEFT JOIN participacion_persona pr   ON pr.Id   = ip.rol_id

  WHERE ip.accidente_id = :id
    AND (pr.Nombre LIKE '%conduc%' OR (ip.vehiculo_id IS NOT NULL AND pr.Nombre IS NULL))
  ORDER BY ip.id ASC
  LIMIT 1
";
$st = $pdo->prepare($sqlConductor);
$st->execute([':id'=>$accidente_id]);
$COND = $st->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  // Si falla por nombre de tabla/columna, caemos a la versión “simple”
  if ($DEBUG) { dbg("[sqlConductor full] ".$e->getMessage()); }
  $sqlConductorSimple = "
    SELECT ip.id AS inv_id, ip.*, pr.Nombre AS rol_nombre,
           p.id AS persona_id, p.*,
           v.id AS vehiculo_id, v.placa, v.color, v.anio,
           v.largo_mm, v.ancho_mm, v.alto_mm,
           tv.nombre AS tipo_vehiculo,
           cv.descripcion AS cat_vehiculo
      FROM involucrados_personas ip
      JOIN personas p       ON p.id=ip.persona_id
      LEFT JOIN vehiculos v ON v.id=ip.vehiculo_id
      LEFT JOIN tipos_vehiculo tv ON tv.id=v.tipo_id
      LEFT JOIN categoria_vehiculos cv ON cv.id=v.categoria_id
      LEFT JOIN participacion_persona pr ON pr.Id=ip.rol_id
     WHERE ip.accidente_id=:id
       AND (pr.Nombre LIKE '%conduc%' OR (ip.vehiculo_id IS NOT NULL AND pr.Nombre IS NULL))
     ORDER BY ip.id ASC LIMIT 1";
  $st = $pdo->prepare($sqlConductorSimple);
  $st->execute([':id'=>$accidente_id]);
  $COND = $st->fetch(PDO::FETCH_ASSOC) ?: [];
  // Normaliza campos faltantes para que los marcadores no fallen
  $COND['categoria_codigo']      = $COND['categoria_codigo']      ?? '';
  $COND['categoria_descripcion'] = $COND['categoria_descripcion'] ?? ($COND['cat_vehiculo'] ?? '');
  $COND['veh_marca_nombre']      = $COND['veh_marca_nombre']      ?? '';
  $COND['veh_modelo_nombre']     = $COND['veh_modelo_nombre']     ?? '';
  $COND['carroceria_nombre']     = $COND['carroceria_nombre']     ?? '';
}

/* Propietario del vehículo (más reciente por accidente) */
$PROP = [];
try{
  $qp = $pdo->prepare("
    SELECT pv.*,
           p.id AS persona_id, p.nombres, p.apellido_paterno, p.apellido_materno,
           p.tipo_doc, p.num_doc, pv.ruc, pv.razon_social, pv.domicilio_fiscal, pv.tipo_propietario
      FROM propietario_vehiculo pv
      LEFT JOIN personas p ON p.id = pv.propietario_persona_id
     WHERE pv.accidente_id=:acc
     ORDER BY pv.id DESC
     LIMIT 1
  ");
  $qp->execute([':acc'=>$accidente_id]);
  $PROP = $qp->fetch(PDO::FETCH_ASSOC) ?: [];
}catch(Throwable $e){ /* sin propietario no rompe */ }

/* Documento del vehículo (intenta por vehiculo_id y, si no hay, por involucrado_vehiculo_id) */
$DOCV = [];

/* 1) Por vehiculo_id (camino directo) */
if (!empty($COND['vehiculo_id'])) {
    $qd = $pdo->prepare("SELECT * FROM documento_vehiculo WHERE vehiculo_id=:veh ORDER BY id DESC LIMIT 1");
    $qd->execute([':veh'=>$COND['vehiculo_id']]);
    $DOCV = $qd->fetch(PDO::FETCH_ASSOC) ?: [];
}

/* 2) Fallback: por involucrado_vehiculo_id (cuando documento_vehiculo no guarda vehiculo_id) */
if (!$DOCV && !empty($COND['vehiculo_id'])) {
    // buscamos el 'involucrado_vehiculos.id' del vehículo de este accidente
    $qiv = $pdo->prepare("
        SELECT iv.id
        FROM involucrados_vehiculos iv
        WHERE iv.accidente_id = :acc AND iv.vehiculo_id = :veh
        ORDER BY iv.id DESC
        LIMIT 1
    ");
    $qiv->execute([':acc'=>$accidente_id, ':veh'=>$COND['vehiculo_id']]);
    $iv = $qiv->fetch(PDO::FETCH_ASSOC);

    if (!empty($iv['id'])) {
        $qd2 = $pdo->prepare("
            SELECT * FROM documento_vehiculo
            WHERE involucrado_vehiculo_id = :iv
            ORDER BY id DESC
            LIMIT 1
        ");
        $qd2->execute([':iv'=>$iv['id']]);
        $DOCV = $qd2->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}

/* ===========================================================
   3) PEATÓN FALLECIDO + FAMILIAR
=========================================================== */
$sqlPeaton = "
  SELECT ip.id AS inv_id, ip.*, pr.Nombre AS rol_nombre,
         p.id AS persona_id, p.*
    FROM involucrados_personas ip
    JOIN personas p ON p.id=ip.persona_id
    LEFT JOIN participacion_persona pr ON pr.Id=ip.rol_id
   WHERE ip.accidente_id=:id
     AND pr.Nombre LIKE '%peat%'
     AND ip.lesion LIKE 'Falle%'
   ORDER BY ip.id ASC
   LIMIT 1
";
$st=$pdo->prepare($sqlPeaton);
$st->execute([':id'=>$accidente_id]);
$PEA = $st->fetch(PDO::FETCH_ASSOC) ?: [];

/* Familiar del fallecido (si existe) */
$FAM = [];
if (!empty($PEA['inv_id'])) {
  $sf=$pdo->prepare("
    SELECT ff.*, p.id AS persona_id, p.*
      FROM familiar_fallecido ff
      LEFT JOIN personas p ON p.id = ff.familiar_persona_id
     WHERE ff.accidente_id=:acc AND ff.fallecido_inv_id=:inv
     ORDER BY ff.id ASC LIMIT 1
  ");
  $sf->execute([':acc'=>$accidente_id, ':inv'=>$PEA['inv_id']]);
  $FAM=$sf->fetch(PDO::FETCH_ASSOC) ?: [];
}

/* Documento OCCISO (peatón fallecido) */
$DOC_OCCISO = get_doc_occiso($pdo, $PEA['persona_id'] ?? null, $accidente_id);

/* ===========================================================
   4) OCUPANTE / PASAJERO (principal por orden)
=========================================================== */
$OCU = [];
$stO=$pdo->prepare("
  SELECT ip.id AS inv_id, ip.*, pr.Nombre AS rol_nombre,
         p.id AS persona_id, p.*
    FROM involucrados_personas ip
    LEFT JOIN participacion_persona pr ON pr.Id=ip.rol_id
    JOIN personas p ON p.id=ip.persona_id
   WHERE ip.accidente_id=:id
     AND (pr.Nombre LIKE '%ocup%' OR pr.Nombre LIKE '%pasaj%')
   ORDER BY ip.id ASC
   LIMIT 1
");
$stO->execute([':id'=>$accidente_id]);
$OCU=$stO->fetch(PDO::FETCH_ASSOC) ?: [];

/* ===========================================================
   5) ABOGADOS (por persona, con fallback)
=========================================================== */
$ABOG_COND = get_abogado($pdo, $accidente_id, $COND['persona_id'] ?? null);
$ABOG_PEA  = get_abogado($pdo, $accidente_id, $PEA['persona_id']  ?? null);
$ABOG_FAM  = get_abogado($pdo, $accidente_id, $FAM['persona_id']  ?? null);
$ABOG_PROP = get_abogado($pdo, $accidente_id, $PROP['persona_id'] ?? null);

/* ===========================================================
   6) DOCUMENTOS DEL CONDUCTOR: Licencia, Dosaje, RML
=========================================================== */
$DOC_LC = [];
if (!empty($COND['persona_id'])) {
  $q=$pdo->prepare("SELECT * FROM documento_lc WHERE persona_id=:p ORDER BY id DESC LIMIT 1");
  $q->execute([':p'=>$COND['persona_id']]); $DOC_LC=$q->fetch(PDO::FETCH_ASSOC) ?: [];
}
$DOC_DOSAJE_COND = get_dosaje($pdo, $COND['persona_id'] ?? null);
$DOC_RML_COND    = get_rml($pdo, $COND['persona_id'] ?? null, $accidente_id);
$DOC_DOSAJE_PEA  = get_dosaje($pdo, $PEA['persona_id'] ?? null);
$DOC_RML_PEA     = get_rml($pdo, $PEA['persona_id'] ?? null, $accidente_id);
$DOC_DOSAJE_OCU  = get_dosaje($pdo, $OCU['persona_id'] ?? null);
$DOC_RML_OCU     = get_rml($pdo, $OCU['persona_id'] ?? null, $accidente_id);

/* ===========================================================
   ITP (Inspección Técnica de la Vía) — tabla `itp`
   Se extrae la fila más reciente por accidente_id (si existe)
=========================================================== */
$ITP = [];
try {
  $q = $pdo->prepare("SELECT * FROM itp WHERE accidente_id = :acc ORDER BY id DESC LIMIT 1");
  $q->execute([':acc'=>$accidente_id]);
  $ITP = $q->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  if ($DEBUG) { dbg("[ITP] ".$e->getMessage()); }
  $ITP = [];
}
/* helper corto para ITP */
function v_itp($arr, $k){
  if (!is_array($arr)) return '—';
  if (!isset($arr[$k]) || $arr[$k] === null || $arr[$k] === '') return '—';
  return $arr[$k];
}

/* ===========================================================
   7) PLANTILLA
=========================================================== */
$tplPath = __DIR__.'/plantillas/informe_atropello.docx';
if (!is_file($tplPath)) { http_response_code(500); echo "No existe la plantilla: $tplPath"; exit; }
$T = new TemplateProcessor($tplPath);

/* ====== ACCIDENTE ====== */
$T->setValue('acc_id',               v($ACC['id']));
$T->setValue('acc_sidpol',           v($ACC['sidpol']));
$T->setValue('acc_registro_sidpol',  v($ACC['registro_sidpol'] ?? $ACC['sidpol'] ?? ''));
$T->setValue('acc_lugar',            v($ACC['lugar']));
$T->setValue('distrito',             v($ACC['distrito_nombre']));
$T->setValue('acc_referencia',       v($ACC['referencia']));
$T->setValue('acc_estado',           v($ACC['estado']));
$T->setValue('acc_sentido',          v($ACC['sentido']));
$T->setValue('acc_secuencia',        v($ACC['secuencia']));
$T->setValue('comisaria',            v($ACC['comisaria_nombre']));
$T->setValue('modalidad',            v($MODS));
$T->setValue('consecuencia',         v($CONS));
$T->setValue('fiscalia',             v($ACC['fiscalia_nombre']));
$T->setValue('fiscal_nombre',        v($ACC['fiscal_nombre']));
$T->setValue('nro_informe_policial', v($ACC['nro_informe_policial']));

/* Fechas/Horas separadas */
$T->setValue('acc_fecha', ymd_pe($ACC['fecha_accidente']));
$T->setValue('acc_hora',  hora_pe($ACC['fecha_accidente']));
$T->setValue('com_fecha', ymd_pe($ACC['fecha_comunicacion']));
$T->setValue('com_hora',  hora_pe($ACC['fecha_comunicacion']));
$T->setValue('int_fecha', ymd_pe($ACC['fecha_intervencion']));
$T->setValue('int_hora',  hora_pe($ACC['fecha_intervencion']));

/* ====== CONDUCTOR ====== */
$T->setValue('cond_doc_tipo',     v($COND['tipo_doc'] ?? ''));
$T->setValue('cond_doc_num',      v($COND['num_doc'] ?? ''));
$T->setValue('cond_apep',         v($COND['apellido_paterno'] ?? ''));
$T->setValue('cond_apem',         v($COND['apellido_materno'] ?? ''));
$T->setValue('cond_nombres',      v($COND['nombres'] ?? ''));
$T->setValue('cond_sexo',         v($COND['sexo'] ?? ''));
$T->setValue('cond_nacim',        fecha_corta($COND['fecha_nacimiento'] ?? ''));
$T->setValue('cond_dep_nac',      v($COND['departamento_nac'] ?? ''));
$T->setValue('cond_grado_instr',  v($COND['grado_instruccion'] ?? ''));
$T->setValue('cond_edad',         v(($COND['edad']??'')!=='' ? $COND['edad'] : edad_from($COND['fecha_nacimiento'] ?? '')));
$T->setValue('cond_estado_civil', v($COND['estado_civil'] ?? ''));
$T->setValue('cond_nacionalidad', v($COND['nacionalidad'] ?? ''));
$T->setValue('cond_domicilio',    v($COND['domicilio'] ?? ''));
$T->setValue('cond_celular',      v($COND['celular'] ?? ''));
$T->setValue('cond_email',        v($COND['email'] ?? ''));
$T->setValue('cond_lesion',       v($COND['lesion'] ?? ''));
$T->setValue('cond_observ',       v($COND['observaciones'] ?? ''));

/* >>>>>>> ABOGADO DEL CONDUCTOR (NUEVO BLOQUE) <<<<<<< */
/* ====== ABOGADO DEL CONDUCTOR ====== */
$T->setValue('cond_abog_nombre',  v(nombre_completo($ABOG_COND['nombres'] ?? '', $ABOG_COND['apellido_paterno'] ?? '', $ABOG_COND['apellido_materno'] ?? '')));
$T->setValue('cond_abog_cond',    v($ABOG_COND['condicion'] ?? ''));
$T->setValue('cond_abog_coleg',   v($ABOG_COND['colegiatura'] ?? ''));
$T->setValue('cond_abog_reg',     v($ABOG_COND['registro'] ?? ''));
$T->setValue('cond_abog_casilla', v($ABOG_COND['casilla_electronica'] ?? ''));
$T->setValue('cond_abog_domproc', v($ABOG_COND['domicilio_procesal'] ?? ''));
$T->setValue('cond_abog_cel',     v($ABOG_COND['celular'] ?? ''));
$T->setValue('cond_abog_email',   v($ABOG_COND['email'] ?? ''));

/* ====== DOCUMENTOS DEL CONDUCTOR ====== */
$T->setValue('lc_clase',           v($DOC_LC['clase'] ?? ''));
$T->setValue('lc_categoria',       v($DOC_LC['categoria'] ?? ''));
$T->setValue('lc_numero',          v($DOC_LC['numero'] ?? ''));
$T->setValue('lc_expedido_por',    v($DOC_LC['expedido_por'] ?? ''));
$T->setValue('lc_vigente_desde',   ymd_pe($DOC_LC['vigente_desde'] ?? ''));
$T->setValue('lc_vigente_hasta',   ymd_pe($DOC_LC['vigente_hasta'] ?? ''));
$T->setValue('lc_restricciones',   v($DOC_LC['restricciones'] ?? ''));

/* Dosaje/RML (Conductor) – también nombres genéricos por compatibilidad */
$T->setValue('dosaje_cond_numero',          v($DOC_DOSAJE_COND['numero'] ?? ''));
$T->setValue('dosaje_cond_registro',        v($DOC_DOSAJE_COND['numero_registro'] ?? ''));
$T->setValue('dosaje_cond_fecha',           ymd_pe($DOC_DOSAJE_COND['fecha_extraccion'] ?? ''));
$T->setValue('dosaje_cond_resultado_cual',  v($DOC_DOSAJE_COND['resultado_cualitativo'] ?? ''));
$T->setValue('dosaje_cond_resultado_cuant', v($DOC_DOSAJE_COND['resultado_cuantitativo'] ?? ''));
$T->setValue('dosaje_cond_observ',          v($DOC_DOSAJE_COND['observaciones'] ?? ''));
$T->setValue('dosaje_numero',        v($DOC_DOSAJE_COND['numero'] ?? ''));
$T->setValue('dosaje_registro',      v($DOC_DOSAJE_COND['numero_registro'] ?? ''));
$T->setValue('dosaje_fecha',         ymd_pe($DOC_DOSAJE_COND['fecha_extraccion'] ?? ''));
$T->setValue('dosaje_resultado_cual',v($DOC_DOSAJE_COND['resultado_cualitativo'] ?? ''));
$T->setValue('dosaje_resultado_cuant',v($DOC_DOSAJE_COND['resultado_cuantitativo'] ?? ''));
$T->setValue('dosaje_observ',        v($DOC_DOSAJE_COND['observaciones'] ?? ''));

$T->setValue('rml_cond_numero',       v($DOC_RML_COND['numero'] ?? ''));
$T->setValue('rml_cond_fecha',        ymd_pe($DOC_RML_COND['fecha'] ?? ''));
$T->setValue('rml_cond_incapacidad',  v($DOC_RML_COND['incapacidad_medico'] ?? ''));
$T->setValue('rml_cond_atencion',     v($DOC_RML_COND['atencion_facultativo'] ?? ''));
$T->setValue('rml_cond_observ',       v($DOC_RML_COND['observaciones'] ?? ''));
$T->setValue('rml_numero',       v($DOC_RML_COND['numero'] ?? ''));
$T->setValue('rml_fecha',        ymd_pe($DOC_RML_COND['fecha'] ?? ''));
$T->setValue('rml_incapacidad',  v($DOC_RML_COND['incapacidad_medico'] ?? ''));
$T->setValue('rml_atencion',     v($DOC_RML_COND['atencion_facultativo'] ?? ''));
$T->setValue('rml_observ',       v($DOC_RML_COND['observaciones'] ?? ''));

/* ====== VEHÍCULO DEL CONDUCTOR ====== */
$T->setValue('veh_placa',           v($COND['placa'] ?? ''));
$T->setValue('veh_marca',           v($COND['veh_marca_nombre'] ?? ''));
$T->setValue('veh_modelo',          v($COND['veh_modelo_nombre'] ?? ''));
$T->setValue('veh_color',           v($COND['color'] ?? ''));
$T->setValue('veh_anio',            v($COND['anio'] ?? ''));
$T->setValue('veh_carroceria',      v($COND['carroceria_nombre'] ?? ''));
$T->setValue('veh_tipo',            v($COND['tipo_nombre'] ?? ''));
$T->setValue('veh_categoria_cod',   v($COND['categoria_codigo'] ?? ''));
$T->setValue('veh_categoria_desc',  v($COND['categoria_descripcion'] ?? ''));
$T->setValue('veh_largo',           v($COND['largo_mm'] ?? ''));
$T->setValue('veh_ancho',           v($COND['ancho_mm'] ?? ''));
$T->setValue('veh_alto',            v($COND['alto_mm'] ?? ''));

/* ====== PROPIETARIO DEL VEHÍCULO ====== */
$T->setValue('prop_tipo',      v($PROP['tipo_propietario'] ?? ''));
$T->setValue('prop_doc_tipo',  v($PROP['tipo_doc'] ?? ''));
$T->setValue('prop_doc_num',   v($PROP['num_doc'] ?? ($PROP['ruc'] ?? '')));
$T->setValue('prop_nombre',    v(($PROP['razon_social'] ?? '') ?: nombre_completo($PROP['nombres'] ?? '', $PROP['apellido_paterno'] ?? '', $PROP['apellido_materno'] ?? '')));
$T->setValue('prop_domicilio', v($PROP['domicilio_fiscal'] ?? ''));

/* ====== DOCUMENTOS DEL VEHÍCULO (Tarjeta, SOAT, Revisión) ====== */
/* Tarjeta de Propiedad */
$T->setValue('doc_num_propiedad',        v($DOCV['numero_propiedad'] ?? ''));
$T->setValue('doc_partida_propiedad',    v($DOCV['partida_propiedad'] ?? ''));
$T->setValue('doc_titulo_propiedad',     v($DOCV['titulo_propiedad'] ?? ''));
/* (opcional) si tu plantilla tuviera este marcador: ${doc_sede_propiedad} */
$T->setValue('doc_sede_propiedad',       v($DOCV['sede_propiedad'] ?? ''));

/* SOAT */
$T->setValue('doc_num_soat',             v($DOCV['numero_soat'] ?? ''));
$T->setValue('doc_aseguradora_soat',     v($DOCV['aseguradora_soat'] ?? ''));
/* Si prefieres fecha tipo “ddMESyyyy”, usa ymd_pe(...) en las dos de abajo */
$T->setValue('doc_vigente_soat',         v($DOCV['vigente_soat'] ?? ''));
$T->setValue('doc_vencimiento_soat',     ymd_pe($DOCV['vencimiento_soat'] ?? ''));

/* Revisión Técnica */
$T->setValue('doc_num_revision',         v($DOCV['numero_revision'] ?? ''));
$T->setValue('doc_certificado_revision', v(trim(($DOCV['certificado_revision'] ?? '') !== '' 
                                              ? $DOCV['certificado_revision'] 
                                              : ($DOCV['certificadora_revision'] ?? ''))));
$T->setValue('doc_vigente_revision',     v($DOCV['vigente_revision'] ?? ''));      // o ymd_pe(...)
$T->setValue('doc_vencimiento_revision', ymd_pe($DOCV['vencimiento_revision'] ?? ''));

/* ====== PERITAJE TÉCNICO DEL VEHÍCULO ====== */
$T->setValue('doc_num_peritaje',     v($DOCV['numero_peritaje'] ?? ''));
$T->setValue('doc_fecha_peritaje',   ymd_pe($DOCV['fecha_peritaje'] ?? ''));
$T->setValue('doc_perito_peritaje',  v($DOCV['perito_peritaje'] ?? ''));
$T->setValue('doc_danos_peritaje',   v($DOCV['danos_peritaje'] ?? ''));

/* Igual que el SOAT: puedes usar ymd_pe(...) si quieres formato “ddMESyyyy” */
$T->setValue('doc_vigente_revision',     v($DOCV['vigente_revision'] ?? ''));
$T->setValue('doc_vencimiento_revision', ymd_pe($DOCV['vencimiento_revision'] ?? ''));


/* Abogado del propietario */
$T->setValue('prop_abog_nombre',  v(nombre_completo($ABOG_PROP['nombres'] ?? '', $ABOG_PROP['apellido_paterno'] ?? '', $ABOG_PROP['apellido_materno'] ?? '')));
$T->setValue('prop_abog_cond',    v($ABOG_PROP['condicion'] ?? ''));
$T->setValue('prop_abog_coleg',   v($ABOG_PROP['colegiatura'] ?? ''));
$T->setValue('prop_abog_reg',     v($ABOG_PROP['registro'] ?? ''));
$T->setValue('prop_abog_casilla', v($ABOG_PROP['casilla_electronica'] ?? ''));
$T->setValue('prop_abog_domproc', v($ABOG_PROP['domicilio_procesal'] ?? ''));
$T->setValue('prop_abog_cel',     v($ABOG_PROP['celular'] ?? ''));
$T->setValue('prop_abog_email',   v($ABOG_PROP['email'] ?? ''));

/* ====== PEATÓN FALLECIDO ====== */
$T->setValue('peaton_doc_tipo',     v($PEA['tipo_doc'] ?? ''));
$T->setValue('peaton_doc_num',      v($PEA['num_doc'] ?? ''));
$T->setValue('peaton_apep',         v($PEA['apellido_paterno'] ?? ''));
$T->setValue('peaton_apem',         v($PEA['apellido_materno'] ?? ''));
$T->setValue('peaton_nombres',      v($PEA['nombres'] ?? ''));
$T->setValue('peaton_sexo',         v($PEA['sexo'] ?? ''));
$T->setValue('peaton_nacim',        fecha_corta($PEA['fecha_nacimiento'] ?? ''));
$T->setValue('peaton_dep_nac',      v($PEA['departamento_nac'] ?? ''));
$T->setValue('peaton_grado_instr',  v($PEA['grado_instruccion'] ?? ''));
$T->setValue('peaton_edad',         v(($PEA['edad']??'')!=='' ? $PEA['edad'] : edad_from($PEA['fecha_nacimiento'] ?? '')));
$T->setValue('peaton_estado_civil', v($PEA['estado_civil'] ?? ''));
$T->setValue('peaton_nacionalidad', v($PEA['nacionalidad'] ?? ''));
$T->setValue('peaton_domicilio',    v($PEA['domicilio'] ?? ''));
$T->setValue('peaton_observ',       v($PEA['observaciones'] ?? ''));

/* Abogado del peatón */
$T->setValue('peaton_abog_nombre',  v(nombre_completo($ABOG_PEA['nombres'] ?? '', $ABOG_PEA['apellido_paterno'] ?? '', $ABOG_PEA['apellido_materno'] ?? '')));
$T->setValue('peaton_abog_cond',    v($ABOG_PEA['condicion'] ?? ''));
$T->setValue('peaton_abog_coleg',   v($ABOG_PEA['colegiatura'] ?? ''));
$T->setValue('peaton_abog_reg',     v($ABOG_PEA['registro'] ?? ''));
$T->setValue('peaton_abog_casilla', v($ABOG_PEA['casilla_electronica'] ?? ''));
$T->setValue('peaton_abog_domproc', v($ABOG_PEA['domicilio_procesal'] ?? ''));
$T->setValue('peaton_abog_cel',     v($ABOG_PEA['celular'] ?? ''));
$T->setValue('peaton_abog_email',   v($ABOG_PEA['email'] ?? ''));

/* ====== DOCUMENTO OCCISO (PEATÓN FALLECIDO) ====== */
$T->setValue('occiso_fecha_lev',        ymd_pe($DOC_OCCISO['fecha_levantamiento'] ?? ''));
$T->setValue('occiso_hora_lev',         hora_pe($DOC_OCCISO['hora_levantamiento'] ?? ''));
$T->setValue('occiso_lugar_lev',        v($DOC_OCCISO['lugar_levantamiento'] ?? ''));
$T->setValue('occiso_posicion_cuerpo',  v($DOC_OCCISO['posicion_cuerpo_levantamiento'] ?? ''));
$T->setValue('occiso_lesiones_lev',     v($DOC_OCCISO['lesiones_levantamiento'] ?? ''));
$T->setValue('occiso_presuntivo_lev',   v($DOC_OCCISO['presuntivo_levantamiento'] ?? ''));
$T->setValue('occiso_legista',          v($DOC_OCCISO['legista_levantamiento'] ?? ''));
$T->setValue('occiso_cmp_legista',      v($DOC_OCCISO['cmp_legista'] ?? ''));
$T->setValue('occiso_obs_lev',          v($DOC_OCCISO['observaciones_levantamiento'] ?? ''));
$T->setValue('occiso_num_pericial',     v($DOC_OCCISO['numero_pericial'] ?? ''));
$T->setValue('occiso_fecha_pericial',   ymd_pe($DOC_OCCISO['fecha_pericial'] ?? ''));
$T->setValue('occiso_hora_pericial',    hora_pe($DOC_OCCISO['hora_pericial'] ?? ''));
$T->setValue('occiso_obs_pericial',     v($DOC_OCCISO['observaciones_pericial'] ?? ''));
$T->setValue('occiso_num_protocolo',    v($DOC_OCCISO['numero_protocolo'] ?? ''));
$T->setValue('occiso_fecha_protocolo',  ymd_pe($DOC_OCCISO['fecha_protocolo'] ?? ''));
$T->setValue('occiso_hora_protocolo',   hora_pe($DOC_OCCISO['hora_protocolo'] ?? ''));
$T->setValue('occiso_lesiones_prot',    v($DOC_OCCISO['lesiones_protocolo'] ?? ''));
$T->setValue('occiso_presuntivo_prot',  v($DOC_OCCISO['presuntivo_protocolo'] ?? ''));
$T->setValue('occiso_dosaje_prot',      v($DOC_OCCISO['dosaje_protocolo'] ?? ''));
$T->setValue('occiso_toxico_prot',      v($DOC_OCCISO['toxicologico_protocolo'] ?? ''));
$T->setValue('occiso_nosoc_epicrisis',  v($DOC_OCCISO['nosocomio_epicrisis'] ?? ''));
$T->setValue('occiso_num_hist_epic',    v($DOC_OCCISO['numero_historia_epicrisis'] ?? ''));
$T->setValue('occiso_tam_epic',         v($DOC_OCCISO['tamatologia_epicrisis'] ?? ''));
$T->setValue('occiso_fecha_alta',       ymd_pe($DOC_OCCISO['fecha_alta_epicrisis'] ?? ''));
$T->setValue('occiso_hora_alta',        hora_pe($DOC_OCCISO['hora_alta_epicrisis'] ?? ''));

/* ====== FAMILIAR DEL FALLECIDO ====== */
$T->setValue('fam_parentesco', v($FAM['parentesco'] ?? ''));
$T->setValue('fam_doc_tipo',   v($FAM['tipo_doc'] ?? ''));
$T->setValue('fam_doc_num',    v($FAM['num_doc'] ?? ''));
$T->setValue('fam_apep',       v($FAM['apellido_paterno'] ?? ''));
$T->setValue('fam_apem',       v($FAM['apellido_materno'] ?? ''));
$T->setValue('fam_nombres',    v($FAM['nombres'] ?? ''));
$T->setValue('fam_celular',    v($FAM['celular'] ?? ''));
$T->setValue('fam_email',      v($FAM['email'] ?? ''));
$T->setValue('fam_domicilio',  v($FAM['domicilio'] ?? ''));
$T->setValue('fam_dep_nac',    v($FAM['departamento_nac'] ?? ''));
$T->setValue('fam_grado_instr',v($FAM['grado_instruccion'] ?? ''));
$T->setValue('fam_observ',     v($FAM['observaciones'] ?? ''));

/* Abogado del familiar */
$T->setValue('fam_abog_nombre',  v(nombre_completo($ABOG_FAM['nombres'] ?? '', $ABOG_FAM['apellido_paterno'] ?? '', $ABOG_FAM['apellido_materno'] ?? '')));
$T->setValue('fam_abog_cond',    v($ABOG_FAM['condicion'] ?? ''));
$T->setValue('fam_abog_coleg',   v($ABOG_FAM['colegiatura'] ?? ''));
$T->setValue('fam_abog_reg',     v($ABOG_FAM['registro'] ?? ''));
$T->setValue('fam_abog_casilla', v($ABOG_FAM['casilla_electronica'] ?? ''));
$T->setValue('fam_abog_domproc', v($ABOG_FAM['domicilio_procesal'] ?? ''));
$T->setValue('fam_abog_cel',     v($ABOG_FAM['celular'] ?? ''));
$T->setValue('fam_abog_email',   v($ABOG_FAM['email'] ?? ''));

/* ====== DOCUMENTOS – PEATÓN/OCUPANTE ====== */
$T->setValue('dosaje_peat_numero',          v($DOC_DOSAJE_PEA['numero'] ?? ''));
$T->setValue('dosaje_peat_registro',        v($DOC_DOSAJE_PEA['numero_registro'] ?? ''));
$T->setValue('dosaje_peat_fecha',           ymd_pe($DOC_DOSAJE_PEA['fecha_extraccion'] ?? ''));
$T->setValue('dosaje_peat_resultado_cual',  v($DOC_DOSAJE_PEA['resultado_cualitativo'] ?? ''));
$T->setValue('dosaje_peat_resultado_cuant', v($DOC_DOSAJE_PEA['resultado_cuantitativo'] ?? ''));
$T->setValue('dosaje_peat_observ',          v($DOC_DOSAJE_PEA['observaciones'] ?? ''));

$T->setValue('rml_peat_numero',       v($DOC_RML_PEA['numero'] ?? ''));
$T->setValue('rml_peat_fecha',        ymd_pe($DOC_RML_PEA['fecha'] ?? ''));
$T->setValue('rml_peat_incapacidad',  v($DOC_RML_PEA['incapacidad_medico'] ?? ''));
$T->setValue('rml_peat_atencion',     v($DOC_RML_PEA['atencion_facultativo'] ?? ''));
$T->setValue('rml_peat_observ',       v($DOC_RML_PEA['observaciones'] ?? ''));

$T->setValue('ocu_nacim', fecha_corta($OCU['fecha_nacimiento'] ?? ''));
$T->setValue('ocu_edad',  v(($OCU['edad']??'')!=='' ? $OCU['edad'] : edad_from($OCU['fecha_nacimiento'] ?? '')));
$T->setValue('pas_nacim', fecha_corta($OCU['fecha_nacimiento'] ?? ''));
$T->setValue('pas_edad',  v(($OCU['edad']??'')!=='' ? $OCU['edad'] : edad_from($OCU['fecha_nacimiento'] ?? '')));

$T->setValue('dosaje_ocu_numero',          v($DOC_DOSAJE_OCU['numero'] ?? ''));
$T->setValue('dosaje_ocu_registro',        v($DOC_DOSAJE_OCU['numero_registro'] ?? ''));
$T->setValue('dosaje_ocu_fecha',           ymd_pe($DOC_DOSAJE_OCU['fecha_extraccion'] ?? ''));
$T->setValue('dosaje_ocu_resultado_cual',  v($DOC_DOSAJE_OCU['resultado_cualitativo'] ?? ''));
$T->setValue('dosaje_ocu_resultado_cuant', v($DOC_DOSAJE_OCU['resultado_cuantitativo'] ?? ''));
$T->setValue('dosaje_ocu_observ',          v($DOC_DOSAJE_OCU['observaciones'] ?? ''));

$T->setValue('rml_ocu_numero',       v($DOC_RML_OCU['numero'] ?? ''));
$T->setValue('rml_ocu_fecha',        ymd_pe($DOC_RML_OCU['fecha'] ?? ''));
$T->setValue('rml_ocu_incapacidad',  v($DOC_RML_OCU['incapacidad_medico'] ?? ''));
$T->setValue('rml_ocu_atencion',     v($DOC_RML_OCU['atencion_facultativo'] ?? ''));
$T->setValue('rml_ocu_observ',       v($DOC_RML_OCU['observaciones'] ?? ''));

/* ====== ITP — campos de la tabla `itp` ====== */
$T->setValue('itp_fecha_itp',          ($ITP['fecha_itp'] ?? '') ? ymd_pe($ITP['fecha_itp']) : '—');
$T->setValue('itp_hora_itp',           ($ITP['hora_itp'] ?? '') ? hora_pe($ITP['hora_itp']) : '—');
$T->setValue('itp_ocurrencia_policial', v_itp($ITP,'ocurrencia_policial'));
$T->setValue('itp_llegada_lugar',      v_itp($ITP,'llegada_lugar'));
$T->setValue('itp_localizacion_unidades', v_itp($ITP,'localizacion_unidades'));
$T->setValue('itp_forma_via',          v_itp($ITP,'forma_via'));
$T->setValue('itp_punto_referencia',   v_itp($ITP,'punto_referencia'));
$T->setValue('itp_ubicacion_gps',      v_itp($ITP,'ubicacion_gps'));

/* vía 1 */
$T->setValue('itp_descripcion_via1',   v_itp($ITP,'descripcion_via1'));
$T->setValue('itp_configuracion_via1', v_itp($ITP,'configuracion_via1'));
$T->setValue('itp_material_via1',      v_itp($ITP,'material_via1'));
$T->setValue('itp_señalizacion_via1',  v_itp($ITP,'señalizacion_via1'));
$T->setValue('itp_ordenamiento_via1',  v_itp($ITP,'ordenamiento_via1'));
$T->setValue('itp_iluminacion_via1',   v_itp($ITP,'iluminacion_via1'));
$T->setValue('itp_visibilidad_via1',   v_itp($ITP,'visibilidad_via1'));
$T->setValue('itp_intensidad_via1',    v_itp($ITP,'intensidad_via1'));
$T->setValue('itp_fluidez_via1',       v_itp($ITP,'fluidez_via1'));
$T->setValue('itp_medidas_via1',       v_itp($ITP,'medidas_via1'));
$T->setValue('itp_observaciones_via1', v_itp($ITP,'observaciones_via1'));

/* vía 2 */
$T->setValue('itp_descripcion_via2',   v_itp($ITP,'descripcion_via2'));
$T->setValue('itp_configuracion_via2', v_itp($ITP,'configuracion_via2'));
$T->setValue('itp_material_via2',      v_itp($ITP,'material_via2'));
$T->setValue('itp_señalizacion_via2',  v_itp($ITP,'señalizacion_via2'));
$T->setValue('itp_ordenamiento_via2',  v_itp($ITP,'ordenamiento_via2'));
$T->setValue('itp_iluminacion_via2',   v_itp($ITP,'iluminacion_via2'));
$T->setValue('itp_visibilidad_via2',   v_itp($ITP,'visibilidad_via2'));
$T->setValue('itp_intensidad_via2',    v_itp($ITP,'intensidad_via2'));
$T->setValue('itp_fluidez_via2',       v_itp($ITP,'fluidez_via2'));
$T->setValue('itp_medidas_via2',       v_itp($ITP,'medidas_via2'));
$T->setValue('itp_observaciones_via2', v_itp($ITP,'observaciones_via2'));

/* evidencias */
$T->setValue('itp_evidencia_biologica', v_itp($ITP,'evidencia_biologica'));
$T->setValue('itp_evidencia_fisica',    v_itp($ITP,'evidencia_fisica'));
$T->setValue('itp_evidencia_material',  v_itp($ITP,'evidencia_material'));

/* ====== DILIGENCIAS (si tienes tabla, reemplaza) ====== */
$T->setValue('diligencias', '—');

/* ---------- Depuración opcional ---------- */
if ($DEBUG) {
  header('Content-Type:text/plain; charset=utf-8');
  echo "=== VEHICULO ===\n";
  echo "MARCA: ".($COND['veh_marca_nombre']??'')."\n";
  echo "MODELO: ".($COND['veh_modelo_nombre']??'')."\n";
  echo "CAT COD: ".($COND['categoria_codigo']??'')."\n";
  echo "CAT DESC: ".($COND['categoria_descripcion']??'')."\n";
  echo "CARROCERIA: ".($COND['carroceria_nombre']??'')."\n\n";

  echo "=== ABOGADOS ===\n";
  echo "COND: ".nombre_completo($ABOG_COND['nombres']??'',$ABOG_COND['apellido_paterno']??'',$ABOG_COND['apellido_materno']??'')."\n";
  echo "PEAT: ".nombre_completo($ABOG_PEA['nombres']??'',$ABOG_PEA['apellido_paterno']??'',$ABOG_PEA['apellido_materno']??'')."\n";
  echo "FAM : ".nombre_completo($ABOG_FAM['nombres']??'',$ABOG_FAM['apellido_paterno']??'',$ABOG_FAM['apellido_materno']??'')."\n";
  echo "PROP: ".nombre_completo($ABOG_PROP['nombres']??'',$ABOG_PROP['apellido_paterno']??'',$ABOG_PROP['apellido_materno']??'')."\n";
  exit;
}

/* ---------- Salida ---------- */
$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0775, true); }
$tmp = tempnam($tmpDir, 'infatro_');
if ($tmp === false) {
  http_response_code(500);
  exit('No se pudo crear un archivo temporal para el DOCX.');
}
$T->saveAs($tmp);

/* -------------- NOMBRE DEL ARCHIVO: usar nro_informe_policial si existe -------------- */
$infpol_raw = trim((string)($ACC['nro_informe_policial'] ?? ''));
$infpol = $infpol_raw !== '' ? $infpol_raw : (string)($ACC['id'] ?? '0');
$infpol = preg_replace('/\s+/', '_', $infpol);
$infpol = preg_replace('/[^A-Za-z0-9_\-]/', '', $infpol);
$filename = 'INFORME_POLICIAL_'.$infpol.'_'.date('Ymd_His').'.docx';
/* ------------------------------------------------------------------------------- */

while (ob_get_level()) { @ob_end_clean(); }
if (headers_sent($fileSent, $lineSent)) {
  @unlink($tmp);
  http_response_code(500);
  exit('No se pudo iniciar la descarga del DOCX porque ya habia salida previa en ' . $fileSent . ':' . $lineSent);
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
