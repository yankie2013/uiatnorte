<?php
/* ===========================================================
   oficio_remitir_diligencia.php  (UIAT NORTE)
   - Genera y descarga el oficio 'resultado dosaje' en Word
   - Usa plantilla: /plantillas/resultado_dosaje.docx
   - Entrada:
       ?oficio_id=XX   (obligatorio)
   - Versión: incluye oficio_oficial_ano y oficio_persona_entidad
   =========================================================== */

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';
if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class) && file_exists(__DIR__ . '/PHPWord-1.4.0/vendor/autoload.php')) {
    require_once __DIR__ . '/PHPWord-1.4.0/vendor/autoload.php';
}

use PhpOffice\PhpWord\TemplateProcessor;

if (!class_exists(TemplateProcessor::class)) {
    http_response_code(500);
    exit('PhpWord no esta disponible para generar el DOCX.');
}

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

/* -------------------- Helpers -------------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fecha_abrev($f){
    if(!$f) return '';
    $mes = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
    $t = strtotime($f); if(!$t) return '';
    return strtoupper(date('d',$t).$mes[(int)date('n',$t)-1].date('Y',$t));
}
function has_col(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $st->execute([$table, $col]);
    return (bool)$st->fetchColumn();
}
function has_table(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
}

/* -------------------- Parámetro requerido -------------------- */
$oficio_id = isset($_GET['oficio_id']) ? (int)$_GET['oficio_id'] : 0;
if ($oficio_id <= 0) { http_response_code(400); exit('Falta oficio_id'); }

/* -------------------- Consultar oficio + joins (incluye persona entidad y oficial_ano) -------------------- */
$sql = "
SELECT 
  o.*,
  a.lugar AS acc_lugar, a.fecha_accidente, a.referencia AS acc_referencia, a.folder,
  c.nombre AS comisaria_nombre,
  e.nombre AS entidad_nombre, e.siglas AS entidad_siglas,
  s.nombre AS asunto_nombre, s.detalle AS asunto_detalle,
  gc.nombre AS grado_cargo_nombre, gc.abreviatura AS grado_cargo_abrev,
  -- persona destino desde oficio_persona_entidad (ppe)
  ppe.nombres AS ppe_nombres,
  ppe.apellido_paterno AS ppe_apep,
  ppe.apellido_materno AS ppe_apem,
  -- oficial año (nombre)
  oa.nombre AS nombre_oficial_ano,
  -- FISCALÍA
  f.nombre     AS fiscalia_nombre,
  f.direccion  AS fiscalia_direccion,
  f.telefono   AS fiscalia_telefono,
  f.correo     AS fiscalia_correo,
  -- FISCAL
  fi.nombres          AS fiscal_nombres,
  fi.apellido_paterno AS fiscal_apep,
  fi.apellido_materno AS fiscal_apem,
  fi.dni              AS fiscal_dni,
  fi.telefono         AS fiscal_telefono,
  fi.correo           AS fiscal_correo,
  fi.cargo            AS fiscal_cargo
FROM oficios o
LEFT JOIN accidentes a ON a.id = o.accidente_id
LEFT JOIN comisarias c ON c.id = a.comisaria_id
LEFT JOIN oficio_entidad e ON e.id = o.entidad_id_destino
LEFT JOIN oficio_asunto s ON s.id = o.asunto_id
LEFT JOIN grado_cargo gc ON gc.id = o.grado_cargo_id
LEFT JOIN oficio_persona_entidad ppe ON ppe.id = o.persona_destino_id
LEFT JOIN oficio_oficial_ano oa ON oa.id = o.oficial_ano_id
LEFT JOIN fiscalia f   ON f.id  = a.fiscalia_id
LEFT JOIN fiscales fi  ON fi.id = a.fiscal_id
WHERE o.id = ? LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([$oficio_id]);
$O = $st->fetch(PDO::FETCH_ASSOC);
if(!$O){ http_response_code(404); exit('Oficio no encontrado'); }

/* -------------------- Fiscal y Fiscalía (nombre completo del fiscal) -------------------- */
$fiscal_nombre = '';
if (!empty($O['fiscal_nombres']) || !empty($O['fiscal_apep']) || !empty($O['fiscal_apem'])) {
    $fiscal_nombre = trim(
        ($O['fiscal_nombres'] ?? '') . ' ' .
        ($O['fiscal_apep'] ?? '') . ' ' .
        ($O['fiscal_apem'] ?? '')
    );
}

/* -------------------- Modalidad (si existe) -------------------- */
$modalidad = '';
if (!empty($O['accidente_id'])) {
    if (has_table($pdo,'accidente_modalidad') && has_table($pdo,'modalidad_accidente')) {
        $q = $pdo->prepare("SELECT GROUP_CONCAT(DISTINCT m.nombre SEPARATOR '||') FROM accidente_modalidad am JOIN modalidad_accidente m ON m.id=am.modalidad_id WHERE am.accidente_id=?");
        $q->execute([(int)$O['accidente_id']]);
        $r = trim((string)$q->fetchColumn());
        if ($r !== '') $modalidad = str_replace('||', ', ', $r);
    } elseif (has_col($pdo,'accidentes','modalidad')) {
        $q = $pdo->prepare("SELECT modalidad FROM accidentes WHERE id=? LIMIT 1");
        $q->execute([(int)$O['accidente_id']]);
        $modalidad = trim((string)$q->fetchColumn());
    }
}

/* -------------------- Personas involucradas -------------------- */
$personas = [];
if (!empty($O['accidente_id'])) {
    $sqlp = "
      SELECT ip.*,
             COALESCE(p.nombres,'') AS nombres,
             CONCAT_WS(' ', COALESCE(p.apellido_paterno,''), COALESCE(p.apellido_materno,'')) AS apellidos,
             COALESCE(p.num_doc,'') AS dni
      FROM involucrados_personas ip
      LEFT JOIN personas p ON p.id = ip.persona_id
      WHERE ip.accidente_id = :acc
      ORDER BY CAST(ip.orden_persona AS UNSIGNED) ASC, ip.id ASC
    ";
    $s = $pdo->prepare($sqlp);
    $s->execute([':acc' => $O['accidente_id']]);
    $personas = $s->fetchAll(PDO::FETCH_ASSOC);
}

/* -------------------- Vehículos involucrados -------------------- */
$vehiculos = [];
if (!empty($O['accidente_id'])) {
    $placaSel = has_col($pdo,'vehiculos','placa') ? "COALESCE(v.placa,'') AS placa" : "'' AS placa";
    $marcaSel = has_col($pdo,'vehiculos','marca') ? "COALESCE(v.marca,'') AS marca" : "'' AS marca";
    $modeloSel = has_col($pdo,'vehiculos','modelo') ? "COALESCE(v.modelo,'') AS modelo" : "'' AS modelo";
    $tipoSel = has_col($pdo,'vehiculos','tipo') ? "COALESCE(v.tipo,'') AS tipo" : "'' AS tipo";
    $obsSel = has_col($pdo,'vehiculos','observaciones') ? "COALESCE(v.observaciones,'') AS observaciones" : "'' AS observaciones";

    $sqlv = "
      SELECT iv.*,
             {$placaSel},
             {$marcaSel},
             {$modeloSel},
             {$tipoSel},
             {$obsSel}
      FROM involucrados_vehiculos iv
      LEFT JOIN vehiculos v ON v.id = iv.vehiculo_id
      WHERE iv.accidente_id = :acc
      ORDER BY CAST(iv.orden_participacion AS UNSIGNED) ASC, iv.id ASC
    ";
    $s = $pdo->prepare($sqlv);
    $s->execute([':acc' => $O['accidente_id']]);
    $vehiculos = $s->fetchAll(PDO::FETCH_ASSOC);
}

/* -------------------- Persona destinatario (best-effort)
   - Primero usamos oficio_persona_entidad (ppe.*)
   - Si no hay, intentamos buscar en tabla personas
*/
$persona_destino = '';
if (!empty($O['ppe_nombres']) || !empty($O['ppe_apep']) || !empty($O['ppe_apem'])) {
    $persona_destino = trim(($O['ppe_nombres'] ?? '') . ' ' . ($O['ppe_apep'] ?? '') . ' ' . ($O['ppe_apem'] ?? ''));
    $persona_destino = trim($persona_destino);
} elseif (!empty($O['persona_destino_id'])) {
    try {
        $q = $pdo->prepare("SELECT nombres, apellido_paterno, apellido_materno, num_doc FROM personas WHERE id=? LIMIT 1");
        $q->execute([(int)$O['persona_destino_id']]);
        $pd = $q->fetch(PDO::FETCH_ASSOC);
        if ($pd) {
            $persona_destino = trim(($pd['nombres'] ?? '') . ' ' . ($pd['apellido_paterno'] ?? '') . ' ' . ($pd['apellido_materno'] ?? ''));
            if (!empty($pd['num_doc'])) $persona_destino .= ' - DNI: '.$pd['num_doc'];
        }
    } catch(Throwable $e){}
} else {
    if (!empty($O['persona_destino_text'])) $persona_destino = trim($O['persona_destino_text']);
}

/* -------------------- Preparar plantilla -------------------- */
$tplPath = __DIR__.'/plantillas/resultado_dosaje.docx';
if (!file_exists($tplPath)) { http_response_code(500); exit('No se encuentra la plantilla: plantillas/resultado_dosaje.docx'); }
$tpl = new TemplateProcessor($tplPath);

/* -------------------- Helper limpieza -------------------- */
$clean = function($v){
  if ($v === null) return '';
  $v = trim((string)$v);
  $v = str_replace(["\r\n","\r"], "\n", $v);
  return $v;
};

/* -------------------- Rellenar marcadores simples -------------------- */
$tpl->setValue('numero',        h($O['numero'] ?? ''));
$tpl->setValue('anio',          h($O['anio'] ?? ''));
$tpl->setValue('fecha_emision', h(!empty($O['fecha_emision']) ? date('d/m/Y', strtotime($O['fecha_emision'])) : ''));
$tpl->setValue('asunto',        h($O['asunto_texto'] ?? $O['asunto'] ?? $O['asunto_nombre'] ?? ''));
$tpl->setValue('motivo',        h($O['motivo'] ?? ''));

/* oficial año: expongo dos marcadores por compatibilidad */
$tpl->setValue('nombre_oficial_ano', h($O['nombre_oficial_ano'] ?? ''));
$tpl->setValue('oficial_ano', h($O['nombre_oficial_ano'] ?? ''));

$tpl->setValue('grado_cargo',   h($O['grado_cargo_nombre'] ?? ''));
$tpl->setValue('referencia_texto', h($O['referencia_texto'] ?? ''));

$entNombre = trim((string)($O['entidad_nombre'] ?? $O['entidad_siglas'] ?? ''));
$tpl->setValue('persona_destino',   h($persona_destino));
$tpl->setValue('entidad_destino',   h($entNombre));
$tpl->setValue('subentidad_destino', h($O['subentidad_nombre'] ?? ''));

/* Accidente */
$tpl->setValue('accidente_lugar',      h($O['acc_lugar'] ?? ''));
$tpl->setValue('accidente_fecha',      h(!empty($O['fecha_accidente']) ? date('d/m/Y H:i', strtotime($O['fecha_accidente'])) : ''));
$tpl->setValue('accidente_fecha_abrev', h(fecha_abrev($O['fecha_accidente'] ?? '')));

$horaHecho = '';
if (!empty($O['fecha_accidente'])) {
    $t = strtotime($O['fecha_accidente']);
    if ($t) $horaHecho = date('H:i', $t);
}
$tpl->setValue('accidente_hora', h($horaHecho));

$tpl->setValue('accidente_referencia', h($O['acc_referencia'] ?? ''));
$tpl->setValue('carpeta',              h($O['folder'] ?? $O['carpeta'] ?? ''));
$tpl->setValue('accidente_modalidad',  h($modalidad));
$tpl->setValue('comisaria_nombre',     h($O['comisaria_nombre'] ?? ''));

/* Fiscalía */
$tpl->setValue('fiscalia_nombre',    h($O['fiscalia_nombre']    ?? ''));
$tpl->setValue('fiscalia_direccion', h($O['fiscalia_direccion'] ?? ''));
$tpl->setValue('fiscalia_telefono',  h($O['fiscalia_telefono']  ?? ''));
$tpl->setValue('fiscalia_correo',    h($O['fiscalia_correo']    ?? ''));

/* Fiscal */
$tpl->setValue('fiscal_nombre',   h($fiscal_nombre));
$tpl->setValue('fiscal_dni',      h($O['fiscal_dni']      ?? ''));
$tpl->setValue('fiscal_cargo',    h($O['fiscal_cargo']    ?? ''));
$tpl->setValue('fiscal_telefono', h($O['fiscal_telefono'] ?? ''));
$tpl->setValue('fiscal_correo',   h($O['fiscal_correo']   ?? ''));

/* -------------------- PERSONAS (LISTA, sin cloneRow) -------------------- */
$plines = [];
foreach ($personas as $p) {
    $nombre = trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? ''));
    $dni = $p['dni'] ?? '';
    $role = $p['rol_id'] ?? $p['orden_persona'] ?? '';
    $lesion = $p['lesion'] ?? '';
    $obs = $p['observaciones'] ?? '';
    $parts = array_filter([$nombre . ($dni ? " (DNI: $dni)" : ''), $role ? "Rol: $role" : '', $lesion ? "Lesión: $lesion" : '', $obs ? "Obs: $obs" : '']);
    $plines[] = implode(' — ', $parts);
}
$tpl->setValue('person_list', h(count($plines) ? implode("\n", $plines) : 'No hay personas involucradas registradas'));

/* -------------------- VEHÍCULOS (LISTA, sin cloneRow) -------------------- */
$vlines = [];
foreach ($vehiculos as $v) {
    $parts = [];
    if (!empty($v['placa'])) $parts[] = 'Placa: '.$v['placa'];
    if (!empty($v['marca'])) $parts[] = 'Marca: '.$v['marca'];
    if (!empty($v['modelo'])) $parts[] = 'Modelo: '.$v['modelo'];
    if (!empty($v['tipo'])) $parts[] = 'Tipo: '.$v['tipo'];
    if (!empty($v['observaciones'])) $parts[] = 'Obs: '.$v['observaciones'];
    $vlines[] = implode(' — ', $parts);
}
$tpl->setValue('vehiculo_list', h(count($vlines) ? implode("\n", $vlines) : 'No hay vehículos involucrados registrados'));

/* -------------------- Descargar DOCX -------------------- */
$nombreFile = 'Resultado_Dosaje_'.($O['numero'] ?? $oficio_id).'-'.($O['anio'] ?? date('Y')).'.docx';
$nombreFile = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombreFile) ?: ('Resultado_Dosaje_' . $oficio_id . '.docx');
$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0775, true); }
$tmp = tempnam($tmpDir, 'docx_');
if ($tmp === false) {
    http_response_code(500);
    exit('No se pudo crear un archivo temporal para el DOCX.');
}
$tpl->saveAs($tmp);

while (ob_get_level()) { ob_end_clean(); }
if (headers_sent($fileSent, $lineSent)) {
    @unlink($tmp);
    http_response_code(500);
    exit('No se pudo iniciar la descarga del DOCX porque ya habia salida previa en ' . $fileSent . ':' . $lineSent);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.$nombreFile.'"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($tmp);
@unlink($tmp);
exit;
