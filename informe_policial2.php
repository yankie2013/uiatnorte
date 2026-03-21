<?php
/* ===========================================================
   informe_policial2.php  (UIAT NORTE) — UT-1..UT-4 + A..D
   - Mismo esquema/consultas base que informe_policial.php
   - Entrada: ?accidente_id=ID  (acepta ?id)
   - Plantilla: /plantillas/informe_policial2.docx
   - Requiere: composer require phpoffice/phpword
   - Incluye modo debug visible en navegador (?debug=1)
   =========================================================== */

if (isset($_GET['debug']) && $_GET['debug']=='1') { @ob_start(); }
$DEBUG = isset($_GET['debug']) && $_GET['debug'] == '1';
if ($DEBUG) { ini_set('display_errors',1); error_reporting(E_ALL); }

function step($t){ global $DEBUG; if($DEBUG){ echo "<pre>STEP: $t</pre>"; @ob_flush(); @flush(); } }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function g($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function val($v,$def=''){ return ($v!==null && $v!=='') ? (string)$v : $def; }
function fecha_larga_es($dt){
  if(!$dt) return '';
  static $mes=[1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $p=preg_split('/[^\d]/', substr((string)$dt,0,10));
  if(count($p)<3) return (string)$dt;
  return ltrim($p[2],'0').' de '.$mes[(int)$p[1]].' de '.$p[0];
}
function fecha_abrev_es($dt){
  if(!$dt) return '';
  static $mes=[1=>'ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];
  $p=preg_split('/[^\d]/', substr((string)$dt,0,10));
  if(count($p)<3) return (string)$dt;
  return ltrim($p[2],'0').'/'.$mes[(int)$p[1]].'/'.$p[0];
}
function edad_desde($fnac){
  if(!$fnac) return '';
  $fn = substr($fnac,0,10);
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$fn)) return '';
  $n=new DateTime($fn); $h=new DateTime('today'); return (string)$n->diff($h)->y;
}

try {

  /* -------- Parámetro -------- */
  $acc_id = (int)g('accidente_id', (int)g('id',0));
  if($acc_id<=0){ http_response_code(400); echo "Falta el parámetro ?accidente_id"; exit; }
  step("accidente_id=$acc_id");

  /* -------- Cargar deps -------- */
  step("require auth/db");
  require __DIR__.'/auth.php';
require_login();
  require __DIR__.'/db.php';
  if(!isset($pdo) || !($pdo instanceof PDO)){ throw new Exception("PDO no instanciado"); }
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec("SET NAMES utf8mb4");

  /* -------- ACCIDENTE -------- */
  step("query ACC");
  $sqlAcc = "
  SELECT a.*,
         c.nombre AS comisaria_nombre, c.direccion AS comisaria_direccion,
         f.nombre AS fiscalia_nombre
  FROM accidentes a
  LEFT JOIN comisarias c ON c.id = a.comisaria_id
  LEFT JOIN fiscalia f   ON f.id = a.fiscalia_id
  WHERE a.id = :id";
  $st = $pdo->prepare($sqlAcc);
  $st->execute([':id'=>$acc_id]);
  $ACC = $st->fetch(PDO::FETCH_ASSOC);
  if(!$ACC){ http_response_code(404); echo "No existe accidente #$acc_id"; exit; }

  /* (opcional) Fiscal asignado */
  step("query FISCAL");
  $FISCAL=null;
  if(!empty($ACC['fiscalia_id'])){
    $st=$pdo->prepare("SELECT * FROM fiscales WHERE fiscalia_id=:fid ORDER BY id ASC LIMIT 1");
    $st->execute([':fid'=>$ACC['fiscalia_id']]);
    $FISCAL=$st->fetch(PDO::FETCH_ASSOC);
  }

  /* -------- VEHÍCULOS (orden_participacion) -------- */
  step("query VEH");
  $sqlVeh = "
SELECT
  iv.id AS iv_id, iv.accidente_id, iv.vehiculo_id, iv.orden_participacion, iv.tipo AS iv_tipo, iv.observaciones AS iv_obs,
  v.placa, v.serie_vin, v.nro_motor, v.anio, v.color,
  cat.codigo AS categoria_codigo,
  t.codigo AS tipo_codigo, t.nombre AS tipo_nombre,
  car.nombre AS carroceria_nombre,
  m.nombre AS marca_nombre,
  mo.nombre AS modelo_nombre
FROM involucrados_vehiculos iv
JOIN vehiculos v                ON v.id = iv.vehiculo_id
LEFT JOIN categoria_vehiculos  cat ON cat.id = v.categoria_id
LEFT JOIN tipos_vehiculo        t  ON t.id   = v.tipo_id
LEFT JOIN carroceria_vehiculo   car ON car.id = v.carroceria_id
LEFT JOIN marcas_vehiculo       m  ON m.id   = v.marca_id
LEFT JOIN modelos_vehiculo      mo ON mo.id  = v.modelo_id
WHERE iv.accidente_id = :id
ORDER BY iv.orden_participacion ASC, iv.id ASC
LIMIT 4";
  $st = $pdo->prepare($sqlVeh);
  $st->execute([':id'=>$acc_id]);
  $VEH = $st->fetchAll(PDO::FETCH_ASSOC);

  /* Documentos del vehículo por involucrado (iv_id) */
  step("query DOCV");
  $DOCV=[];
  if($VEH){
    $ivIds = array_column($VEH,'iv_id');
    $qs = implode(',', array_fill(0,count($ivIds),'?'));
    $st=$pdo->prepare("SELECT * FROM documento_vehiculo WHERE involucrado_vehiculo_id IN ($qs)");
    $st->execute($ivIds);
    while($r=$st->fetch(PDO::FETCH_ASSOC)){ $DOCV[(int)$r['involucrado_vehiculo_id']]=$r; }
  }

  /* Propietario por involucrado (vehiculo_inv_id) */
  step("query PROP");
  $PROP=[];
  if($VEH){
    $ivIds = array_column($VEH,'iv_id');
    $qs = implode(',', array_fill(0,count($ivIds),'?'));
    $params = array_merge([$acc_id], $ivIds);
    $st=$pdo->prepare("SELECT * FROM propietario_vehiculo WHERE accidente_id=? AND vehiculo_inv_id IN ($qs) ORDER BY id DESC");
    $st->execute($params);
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $r){
      if(($r['tipo_propietario'] ?? '')==='NATURAL' && !empty($r['propietario_persona_id'])){
        $ps=$pdo->prepare("SELECT * FROM personas WHERE id=?"); $ps->execute([$r['propietario_persona_id']]); $per=$ps->fetch(PDO::FETCH_ASSOC);
        if($per){
          $r['_propietario_nombre']=trim(($per['nombres']??'').' '.($per['apellido_paterno']??'').' '.($per['apellido_materno']??''));
          $r['_propietario_doc']=trim(($per['tipo_doc']??'').' '.($per['num_doc']??''));
          $r['_propietario_dom']=$per['domicilio']??'';
          $r['_propietario_cel']=$per['celular']??'';
        }
      }
      if(!empty($r['representante_persona_id'])){
        $ps=$pdo->prepare("SELECT * FROM personas WHERE id=?"); $ps->execute([$r['representante_persona_id']]); $rep=$ps->fetch(PDO::FETCH_ASSOC);
        if($rep){
          $r['_rep_nombre']=trim(($rep['nombres']??'').' '.($rep['apellido_paterno']??'').' '.($rep['apellido_materno']??''));
          $r['_rep_doc']=trim(($rep['tipo_doc']??'').' '.($rep['num_doc']??''));
        }
      }
      $PROP[(int)$r['vehiculo_inv_id']]=$r;
    }
  }

  /* -------- PERSONAS -------- */
  step("query PERSONAS");
  $sqlPer = "
  SELECT ip.id AS invp_id, ip.accidente_id, ip.persona_id, ip.rol_id, ip.orden_persona, ip.vehiculo_id, ip.lesion, ip.observaciones,
         p.*, rol.Nombre AS rol_nombre, rol.RequiereVehiculo
  FROM involucrados_personas ip
  JOIN personas p                  ON p.id = ip.persona_id
  LEFT JOIN participacion_persona rol ON rol.Id = ip.rol_id
  WHERE ip.accidente_id = :id
  ORDER BY ip.orden_persona ASC, ip.id ASC";
  $st=$pdo->prepare($sqlPer);
  $st->execute([':id'=>$acc_id]);
  $PER=$st->fetchAll(PDO::FETCH_ASSOC);

  /* Cargar docs persona clave (LC/RML/DOSAJE/OCCISO) */
  step("docs PERSONA");
  $mapPersonDocs = [
    'lc'     => "SELECT * FROM documento_lc WHERE persona_id = ? ORDER BY id DESC LIMIT 1",
    'rml'    => "SELECT * FROM documento_rml WHERE persona_id = ? AND accidente_id = ? ORDER BY id DESC LIMIT 1",
    'dosaje' => "SELECT * FROM documento_dosaje WHERE persona_id = ? ORDER BY id DESC LIMIT 1",
    'occiso' => "SELECT * FROM documento_occiso WHERE persona_id = ? AND accidente_id = ? ORDER BY id DESC LIMIT 1",
  ];
  function loadDocsPersona(PDO $pdo, $pid, $acc_id, $map){
    $out=[]; foreach($map as $k=>$sql){
      $st=$pdo->prepare($sql);
      if(strpos($sql,'accidente_id')!==false){ $st->execute([$pid,$acc_id]); }
      else{ $st->execute([$pid]); }
      $out[$k]=$st->fetch(PDO::FETCH_ASSOC) ?: null;
    } return $out;
  }
  $PER_EXT=[];
  foreach($PER as $p){
    $docs = loadDocsPersona($pdo, $p['persona_id'], $acc_id, $mapPersonDocs);
    $p['_docs']=$docs;
    $p['_nombre_completo']=trim(($p['nombres']??'').' '.($p['apellido_paterno']??'').' '.($p['apellido_materno']??''));
    $p['_doc_identidad']=trim(($p['tipo_doc']??'').' '.($p['num_doc']??''));
    $PER_EXT[(int)$p['invp_id']]=$p;
  }

  /* Map por vehículo y CONDUCTOR */
  step("map CONDUCTOR");
  $PER_BY_VEH=[];
  foreach($PER_EXT as $p){
    $vid=(int)($p['vehiculo_id'] ?: 0);
    if(!isset($PER_BY_VEH[$vid])) $PER_BY_VEH[$vid]=[];
    $PER_BY_VEH[$vid][]=$p;
  }
  $CONDUCTOR_BY_IV=[];
  $roleEq = function($p,$name){ return strcasecmp((string)$p['rol_nombre'],$name)===0; };
  foreach ($VEH as $v) {
    $vid = isset($v['vehiculo_id']) ? (int)$v['vehiculo_id'] : 0;
    $iv  = isset($v['iv_id'])       ? (int)$v['iv_id']       : 0;

    $lista = isset($PER_BY_VEH[$vid]) && is_array($PER_BY_VEH[$vid]) ? $PER_BY_VEH[$vid] : [];

    // Filtro conductor compatible 7.0+
    $cand = array_filter($lista, function ($x) use ($roleEq) {
        return $roleEq($x, 'Conductor');
    });

    if (!empty($cand)) {
        // Ordenar por orden_persona (nulos al final)
        usort($cand, function ($a, $b) {
            $ao = isset($a['orden_persona']) ? (int)$a['orden_persona'] : 999;
            $bo = isset($b['orden_persona']) ? (int)$b['orden_persona'] : 999;
            if ($ao === $bo) return 0;
            return ($ao < $bo) ? -1 : 1; // evita <=> por máxima compatibilidad
        });
        $cand = array_values($cand);
        $CONDUCTOR_BY_IV[$iv] = $cand[0];
    } else {
        $CONDUCTOR_BY_IV[$iv] = null;
    }
}

  /* -------- Fallecido y familiar -------- */
  step("fallecido/familiar");
  $FALLECIDO=null; $FAM=null;
  foreach($PER_EXT as $p){
    if(strcasecmp((string)$p['lesion'],'Fallecido')===0 || !empty($p['_docs']['occiso'])){ $FALLECIDO=$p; break; }
  }
  if($FALLECIDO){
    $st=$pdo->prepare("SELECT * FROM familiar_fallecido WHERE accidente_id=:acc AND fallecido_inv_id=:inv ORDER BY id ASC LIMIT 1");
    $st->execute([':acc'=>$acc_id, ':inv'=>$FALLECIDO['invp_id']]);
    $ff=$st->fetch(PDO::FETCH_ASSOC);
    if($ff && !empty($ff['familiar_persona_id'])){
      $ps=$pdo->prepare("SELECT * FROM personas WHERE id=?"); $ps->execute([$ff['familiar_persona_id']]);
      $per=$ps->fetch(PDO::FETCH_ASSOC);
      if($per){
        $FAM=[
          'parentesco'=>$ff['parentesco'] ?? '',
          'nombre'=>trim(($per['nombres']??'').' '.($per['apellido_paterno']??'').' '.($per['apellido_materno']??'')),
          'doc'=>trim(($per['tipo_doc']??'').' '.($per['num_doc']??'')),
          'cel'=>($per['celular']??''),
        ];
      }
    }
  }

  /* -------- Policiales intervinientes (hasta 4) -------- */
  step("policiales");
  $st=$pdo->prepare("SELECT * FROM policial_interviniente WHERE accidente_id=:id ORDER BY id ASC LIMIT 4");
  $st->execute([':id'=>$acc_id]);
  $PINT=$st->fetchAll(PDO::FETCH_ASSOC);
  $POL= [];
  $i=1;
  foreach($PINT as $pi){
    $nom='';
    if(!empty($pi['persona_id'])){
      $ps=$pdo->prepare("SELECT * FROM personas WHERE id=?"); $ps->execute([$pi['persona_id']]);
      $per=$ps->fetch(PDO::FETCH_ASSOC);
      if($per){ $nom=trim(($per['nombres']??'').' '.($per['apellido_paterno']??'').' '.($per['apellido_materno']??'')); }
    }
    $POL[$i]=[
      'nombre'=>$nom, 'grado'=>($pi['grado_policial']??''), 'cargo'=>($pi['rol_funcion']??''), 'cip'=>($pi['cip']??'')
    ]; $i++;
  }
  for(; $i<=4; $i++){ $POL[$i]=['nombre'=>'','grado'=>'','cargo'=>'','cip'=>'']; }

  /* -------- Armar marcadores -------- */
  step("armar marcadores");
  $vars=[];

  // Accidente / cabecera
  $vars['acc_id']            = (string)$ACC['id'];
  $vars['acc_sidpol']        = val($ACC['sidpol']);
  $vars['acc_fecha']         = substr((string)($ACC['fecha_accidente'] ?? $ACC['fecha']),0,10);
  $vars['acc_fecha_larga']   = fecha_larga_es($ACC['fecha_accidente'] ?? $ACC['fecha'] ?? '');
  $vars['acc_fecha_abrev']   = fecha_abrev_es($ACC['fecha_accidente'] ?? $ACC['fecha'] ?? '');
  $vars['acc_hora']          = substr((string)($ACC['fecha_accidente'] ?? ''),11,5) ?: ($ACC['hora'] ?? '');
  $vars['acc_lugar']         = val($ACC['lugar']);
  $vars['acc_direccion']     = val($ACC['direccion'] ?? '');
  $vars['acc_km']            = val($ACC['km'] ?? '');
  $vars['acc_referencia']    = val($ACC['referencia']);
  $vars['dep_nombre']        = val($ACC['cod_dep'] ?? '');
  $vars['prov_nombre']       = val($ACC['cod_prov'] ?? '');
  $vars['dist_nombre']       = val($ACC['cod_dist'] ?? '');
  $vars['modalidad_codigo']  = val($ACC['modalidad_codigo'] ?? '');
  $vars['modalidad_nombre']  = val($ACC['modalidad_nombre'] ?? '');
  $vars['consecuencia_codigo']= val($ACC['consecuencia_codigo'] ?? '');
  $vars['consecuencia_nombre']= val($ACC['consecuencia_nombre'] ?? '');
  $vars['comisaria_nombre']  = val($ACC['comisaria_nombre']);
  $vars['comisaria_direccion']=val($ACC['comisaria_direccion'] ?? '');
  $vars['comisaria_distrito']= val($ACC['cod_dist'] ?? '');
  $vars['fiscalia_nombre']   = val($ACC['fiscalia_nombre']);
  $vars['fiscal_nombre']     = $FISCAL ? trim(($FISCAL['nombres']??'').' '.($FISCAL['apellido_paterno']??'').' '.($FISCAL['apellido_materno']??'')) : '';
  $vars['fiscal_cargo']      = $FISCAL['cargo'] ?? '';

  // UT-1 .. UT-4
  for($i=0;$i<4;$i++){
    $n = $i+1; $pref = "UT{$n}_";
    $v = $VEH[$i] ?? null;
    if(!$v){
      foreach(['placa','marca','modelo','categoria','tipo','carroceria','color','anio','vin','motor',
               'propietario','prop_doc','prop_dir','prop_cel',
               'conductor','cond_doc','cond_edad','cond_licencia','cond_clase_cat',
               'soat_numero','soat_aseguradora','soat_vigencia','soat_vencimiento',
               'rev_numero','rev_certificadora','rev_vigencia','rev_vencimiento',
               'peritaje_numero','peritaje_fecha','peritaje_perito','peritaje_danos'] as $k){ $vars[$pref.$k]=''; }
      continue;
    }
    $iv = (int)$v['iv_id'];

    $vars[$pref.'placa']      = val($v['placa']);
    $vars[$pref.'marca']      = val($v['marca_nombre']);
    $vars[$pref.'modelo']     = val($v['modelo_nombre']);
    $vars[$pref.'categoria']  = val($v['categoria_codigo']); // solo código; no hay nombre en la tabla
    $vars[$pref.'tipo']       = trim(val($v['tipo_codigo']).' '.val($v['tipo_nombre']));
    $vars[$pref.'carroceria'] = val($v['carroceria_nombre']);
    $vars[$pref.'color']      = val($v['color']);
    $vars[$pref.'anio']       = val($v['anio']);
    $vars[$pref.'vin']        = val($v['serie_vin']);
    $vars[$pref.'motor']      = val($v['nro_motor']);

    $pp = $PROP[$iv] ?? [];
    $vars[$pref.'propietario']= val($pp['_propietario_nombre'] ?? $pp['rucrazon_social'] ?? '');
    $vars[$pref.'prop_doc']   = val($pp['_propietario_doc'] ?? '');
    $vars[$pref.'prop_dir']   = val($pp['_propietario_dom'] ?? $pp['domicilio_fiscal'] ?? '');
    $vars[$pref.'prop_cel']   = val($pp['_propietario_cel'] ?? '');

    $cond = $CONDUCTOR_BY_IV[$iv] ?? null;
    if($cond){
      $vars[$pref.'conductor']     = trim($cond['_nombre_completo']);
      $vars[$pref.'cond_doc']      = trim($cond['_doc_identidad']);
      $vars[$pref.'cond_edad']     = edad_desde($cond['fecha_nacimiento'] ?? '');
      $lc = $cond['_docs']['lc'] ?? null;
      $vars[$pref.'cond_licencia'] = val($lc['numero'] ?? '');
      $vars[$pref.'cond_clase_cat']= trim(val($lc['clase'] ?? '').' '.val($lc['categoria'] ?? ''));
    } else {
      $vars[$pref.'conductor']=$vars[$pref.'cond_doc']=$vars[$pref.'cond_edad']=$vars[$pref.'cond_licencia']=$vars[$pref.'cond_clase_cat']='';
    }

    $dv = $DOCV[$iv] ?? [];
    $vars[$pref.'soat_numero']      = val($dv['numero_soat'] ?? '');
    $vars[$pref.'soat_aseguradora'] = val($dv['aseguradora_soat'] ?? '');
    $vars[$pref.'soat_vigencia']    = val($dv['vigente_soat'] ?? '');
    $vars[$pref.'soat_vencimiento'] = val($dv['vencimiento_soat'] ?? '');

    $vars[$pref.'rev_numero']       = val($dv['numero_revision'] ?? '');
    $vars[$pref.'rev_certificadora']= val($dv['certificadora_revision'] ?? '');
    $vars[$pref.'rev_vigencia']     = val($dv['vigente_revision'] ?? '');
    $vars[$pref.'rev_vencimiento']  = val($dv['vencimiento_revision'] ?? '');

    $vars[$pref.'peritaje_numero']  = val($dv['numero_peritaje'] ?? '');
    $vars[$pref.'peritaje_fecha']   = val($dv['fecha_peritaje'] ?? '');
    $vars[$pref.'peritaje_perito']  = val($dv['perito_peritaje'] ?? '');
    $vars[$pref.'peritaje_danos']   = val($dv['danos_peritaje'] ?? '');
  }

  /* -------- Personas A..D por rol (global) -------- */
  step("listas A..D");
  $PAS=[]; $OCU=[]; $TES=[]; $PEA=[];
  foreach($PER_EXT as $p){
    $rol = strtolower((string)$p['rol_nombre']);
    if($rol==='pasajero') $PAS[]=$p;
    elseif($rol==='ocupante') $OCU[]=$p;
    elseif(strpos($rol,'testig')!==false) $TES[]=$p;
    elseif($rol==='peatón' || $rol==='peaton') $PEA[]=$p;
  }
  $fill = function(&$vars,$base,$rows){
    $letters=['A','B','C','D']; $i=0;
    foreach($letters as $L){
      $p = $rows[$i] ?? null;
      $vars["{$base}_{$L}_nombre"] = $p ? trim($p['_nombre_completo']) : '';
      $vars["{$base}_{$L}_doc"]    = $p ? trim($p['_doc_identidad'])   : '';
      $vars["{$base}_{$L}_edad"]   = $p ? edad_desde($p['fecha_nacimiento'] ?? '') : '';
      $vars["{$base}_{$L}_lesion"] = $p ? val($p['lesion'] ?? '') : '';
      $i++;
    }
    $list=[]; $i=0;
    foreach($letters as $L){ if(!empty($rows[$i])){
      $list[] = $L.') '.trim($rows[$i]['_nombre_completo']).' — '.trim($rows[$i]['_doc_identidad']);
    } $i++; }
    $vars['lista_'.strtolower($base)] = $list ? implode("; ", $list) : '';
  };
  $fill($vars,'PAS',$PAS);
  $fill($vars,'OCU',$OCU);
  $fill($vars,'TES',$TES);
  $fill($vars,'PEA',$PEA);

  /* -------- Fallecido + familiar -------- */
  step("marcadores occiso/fam");
  if($FALLECIDO){
    $vars['occiso_nombre'] = trim($FALLECIDO['_nombre_completo']);
    $vars['occiso_doc']    = trim($FALLECIDO['_doc_identidad']);
    $vars['occiso_edad']   = edad_desde($FALLECIDO['fecha_nacimiento'] ?? '');
    $vars['occiso_lesion'] = val($FALLECIDO['lesion'] ?? '');
  } else {
    $vars['occiso_nombre']=$vars['occiso_doc']=$vars['occiso_edad']=$vars['occiso_lesion']='';
  }
  if($FAM){
    $vars['fam_parentesco'] = val($FAM['parentesco']);
    $vars['fam_nombre']     = val($FAM['nombre']);
    $vars['fam_doc']        = val($FAM['doc']);
    $vars['fam_cel']        = val($FAM['cel']);
  } else {
    $vars['fam_parentesco']=$vars['fam_nombre']=$vars['fam_doc']=$vars['fam_cel']='';
  }

  /* -------- Docs personales (occiso) -------- */
  step("marcadores docs persona");
  $lc = $FALLECIDO ? ($FALLECIDO['_docs']['lc'] ?? null) : null;
  $rml= $FALLECIDO ? ($FALLECIDO['_docs']['rml'] ?? null) : null;
  $dos= $FALLECIDO ? ($FALLECIDO['_docs']['dosaje'] ?? null) : null;
  $occ= $FALLECIDO ? ($FALLECIDO['_docs']['occiso'] ?? null) : null;

  $vars['lc_numero']        = val($lc['numero'] ?? '');
  $vars['lc_clase_cat']     = trim(val($lc['clase'] ?? '').' '.val($lc['categoria'] ?? ''));
  $vars['lc_fecha_emision'] = val($lc['fecha_emision'] ?? '');
  $vars['lc_fecha_venc']    = val($lc['fecha_vencimiento'] ?? '');

  $vars['rml_numero']       = val($rml['numero'] ?? '');
  $vars['rml_fecha']        = val($rml['fecha'] ?? '');
  $vars['rml_resultado']    = val($rml['resultado'] ?? '');

  $vars['dosaje_numero']    = val($dos['numero'] ?? '');
  $vars['dosaje_fecha']     = val($dos['fecha'] ?? '');
  $vars['dosaje_resultado'] = val($dos['resultado'] ?? '');

  $vars['occiso_doc_numero']= val($occ['numero_documento'] ?? '');
  $vars['occiso_acta_numero']=val($occ['numero_acta'] ?? '');

  /* -------- Policiales intervinientes -------- */
  step("marcadores policiales");
  for($i=1;$i<=4;$i++){
    $vars["pol_{$i}_nombre"] = $POL[$i]['nombre'] ?? '';
    $vars["pol_{$i}_grado"]  = $POL[$i]['grado']  ?? '';
    $vars["pol_{$i}_cargo"]  = $POL[$i]['cargo']  ?? '';
    $vars["pol_{$i}_cip"]    = $POL[$i]['cip']    ?? '';
  }

  /* -------- Render DOCX -------- */
  step("autoload PhpWord");
  require_once __DIR__.'/vendor/autoload.php';
  if(!class_exists('\PhpOffice\PhpWord\TemplateProcessor')){
    throw new Exception("No se cargó PhpOffice\\PhpWord (vendor/autoload.php).");
  }
  step("abrir plantilla");
  $tplPath = __DIR__.'/plantillas/informe_policial2.docx';
  if(!file_exists($tplPath)){ http_response_code(500); echo "No se encontró la plantilla: plantillas/informe_policial2.docx"; exit; }

  $tpl = new \PhpOffice\PhpWord\TemplateProcessor($tplPath);
  foreach($vars as $k=>$v){ $tpl->setValue($k, $v); }

  $outName = 'Informe_Policial2_ACC_'.$ACC['id'].'_'.date('Ymd_His').'.docx';
  $tmpFile = sys_get_temp_dir().'/'.$outName;
  step("saveAs $tmpFile");
  $tpl->saveAs($tmpFile);

if (isset($_GET['debug']) && $_GET['debug']=='1') { @ob_end_clean(); }

  // Headers (recién ahora, para no romper el debug previo)
  header('Content-Description: File Transfer');
  header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
  header('Content-Disposition: attachment; filename="'.$outName.'"');
  header('Content-Length: '.filesize($tmpFile));
  readfile($tmpFile);
  @unlink($tmpFile);
  exit;

} catch (Throwable $e) {
  http_response_code(500);
  echo $DEBUG
    ? "<pre style='color:#c00;font-family:monospace'>ERROR: ".$e->getMessage()."\n".$e->getFile().":".$e->getLine()."\n\n".$e->getTraceAsString()."</pre>"
    : "Ocurrió un error al generar el informe.";
  exit;
}