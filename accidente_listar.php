<?php
require __DIR__.'/auth.php';
require_login();
require __DIR__.'/db.php';
header('Content-Type: text/html; charset=utf-8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function lower_u(string $value): string {
  return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function normalizar_rol_resumen(?string $rol): ?string {
  $rol = trim((string)$rol);
  if ($rol === '') return null;
  $norm = str_replace('Ã³', 'o', lower_u($rol));
  if (str_contains($norm, 'conduc')) return 'Conductor';
  if (str_contains($norm, 'peaton')) return 'peaton';
  return null;
}

function normalizar_lesion_resumen(?string $lesion): string {
  $lesion = trim((string)$lesion);
  if ($lesion === '') return 'Ileso';
  $norm = str_replace('Ã³', 'o', lower_u($lesion));
  if (str_contains($norm, 'fallec')) return 'Fallecido';
  if ($norm === 'ileso' || $norm === 'sin lesion' || $norm === 'sin lesiones') return 'Ileso';
  return 'Herido';
}

function fecha_lista_corta(?string $fecha): string {
  $fecha = trim((string)$fecha);
  if ($fecha === '') return '-';
  $ts = strtotime($fecha);
  if ($ts === false) return $fecha;
  return date('d/m/Y H:i', $ts);
}

function chip_rol_class(?string $rol): string {
  return trim((string)$rol) === 'Conductor' ? 'chip-role-conductor' : 'chip-role-peaton';
}

function chip_lesion_class(?string $lesion): string {
  return match (trim((string)$lesion)) {
    'Fallecido' => 'chip-status-fallecido',
    'Ileso' => 'chip-status-ileso',
    default => 'chip-status-herido',
  };
}

function placa_visible(?string $placa): string {
  $placa = trim((string)$placa);
  if ($placa === '') return 'SIN PLACA';
  return str_starts_with($placa, 'SPLACA') ? 'SIN PLACA' : $placa;
}

function url_estado_accidente(string $estado): string {
  return 'accidente_listar.php?' . http_build_query(['estado' => $estado]);
}

function url_filtro_accidente(array $changes): string {
  $params = $_GET;
  foreach ($changes as $key => $value) {
    if ($value === null || $value === '') {
      unset($params[$key]);
      continue;
    }
    $params[$key] = $value;
  }
  return 'accidente_listar.php' . ($params !== [] ? ('?' . http_build_query($params)) : '');
}

function tipo_registro_label(?string $tipo): string {
  $tipo = trim((string)$tipo);
  if ($tipo === 'Intervencion') return 'Intervención';
  return $tipo;
}

function vehiculo_tipo_resumen(array $veh): string {
  foreach (['tipo_nombre', 'carroceria_nombre', 'vinculo_tipo'] as $key) {
    $value = trim((string)($veh[$key] ?? ''));
    if ($value !== '') return $value;
  }
  return 'vehiculo';
}

function involucrado_prioridad_resumen(?string $rol): int {
  $norm = lower_u(trim((string)$rol));
  $norm = str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $norm);
  if (str_contains($norm, 'conduc')) return 1;
  if (str_contains($norm, 'peaton')) return 2;
  foreach (['ocupante', 'pasajero', 'copiloto', 'acompanante'] as $tipo) {
    if (str_contains($norm, $tipo)) return 3;
  }
  return 4;
}

function involucrado_icono_resumen(?string $rol, ?string $tipoVehiculo): string {
  $rolNorm = lower_u(trim((string)$rol));
  $tipoNorm = lower_u(trim((string)$tipoVehiculo));
  $norm = str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $rolNorm.' '.$tipoNorm);
  if (str_contains($rolNorm, 'peat')) return '🚶';
  if (!str_contains($rolNorm, 'conduc')) return '👤';
  if (str_contains($norm, 'moto') && !str_contains($norm, 'trimoto') && !str_contains($norm, 'mototaxi')) return '🏍️';
  if (str_contains($norm, 'trimoto') || str_contains($norm, 'mototaxi')) return '🛺';
  if (str_contains($norm, 'camion') || str_contains($norm, 'remolc')) return '🚚';
  if (str_contains($norm, 'omnibus') || str_contains($norm, 'bus')) return '🚌';
  if (str_contains($norm, 'bicicleta')) return '🚲';
  return '🚘';
}

function occupied_folder_map(PDO $pdo): array {
  $sql = "SELECT id, folder
            FROM accidentes
           WHERE folder BETWEEN 1 AND 20
             AND COALESCE(NULLIF(TRIM(estado), ''), 'Pendiente') <> 'Resuelto'";
  $map = [];
  foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $folder = (int)($row['folder'] ?? 0);
    if ($folder >= 1 && $folder <= 20 && !isset($map[(string)$folder])) {
      $map[(string)$folder] = (int)$row['id'];
    }
  }
  return $map;
}

function render_folder_options(string $current, int $currentId, array $occupiedFolders): void {
  echo '<option value="" ' . ($current === '' ? 'selected' : '') . '>&mdash;</option>';
  for ($k = 1; $k <= 20; $k++) {
    $value = (string)$k;
    $occupiedBy = (int)($occupiedFolders[$value] ?? 0);
    if ($occupiedBy > 0 && $occupiedBy !== $currentId && $current !== $value) {
      continue;
    }
    echo '<option value="' . $k . '" ' . ($current === $value ? 'selected' : '') . '>' . $k . '</option>';
  }
}

/* ============================
   AJAX: cambiar estado (inline) / cambiar folder (inline) / priority
============================ */
if (($_POST['ajax'] ?? '') === 'estado') {
  $id     = (int)($_POST['id'] ?? 0);
  $estado = trim($_POST['estado'] ?? '');
  header('Content-Type: application/json; charset=utf-8');

  $permitidos = ['Pendiente','Resuelto','Con diligencias'];
  if ($id>0 && in_array($estado,$permitidos,true)) {
    $st = $estado === 'Resuelto'
      ? $pdo->prepare("UPDATE accidentes SET estado=?, folder=NULL WHERE id=?")
      : $pdo->prepare("UPDATE accidentes SET estado=? WHERE id=?");
    $st->execute([$estado,$id]);
    echo json_encode([
      'ok'=>true,
      'folder'=>($estado === 'Resuelto' ? null : false),
      'occupied_folders'=>occupied_folder_map($pdo),
    ]);
  } else {
    echo json_encode(['ok'=>false,'msg'=>'Estado no permitido']);
  }
  exit;
}

if (($_POST['ajax'] ?? '') === 'folder') {
  $id     = (int)($_POST['id'] ?? 0);
  $raw    = $_POST['folder'] ?? '';
  header('Content-Type: application/json; charset=utf-8');

  if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'ID invÃ¡lido']); exit; }

  if ($raw === '') {
    // Guardar como NULL
    $st = $pdo->prepare("UPDATE accidentes SET folder=NULL WHERE id=?");
    $st->execute([$id]);
    echo json_encode(['ok'=>true,'val'=>null,'occupied_folders'=>occupied_folder_map($pdo)]);
  } else {
    $n = (int)$raw;
    if ($n>=1 && $n<=20) {
      $currentStatus = $pdo->prepare("SELECT COALESCE(NULLIF(TRIM(estado), ''), 'Pendiente') FROM accidentes WHERE id = ? LIMIT 1");
      $currentStatus->execute([$id]);
      if ((string)$currentStatus->fetchColumn() === 'Resuelto') {
        echo json_encode(['ok'=>false,'msg'=>'No se asigna folder a accidentes resueltos.']);
        exit;
      }
      $occupied = $pdo->prepare("SELECT id
                                   FROM accidentes
                                  WHERE folder = ?
                                    AND id <> ?
                                    AND COALESCE(NULLIF(TRIM(estado), ''), 'Pendiente') <> 'Resuelto'
                                  LIMIT 1");
      $occupied->execute([$n, $id]);
      if ($occupied->fetchColumn()) {
        echo json_encode(['ok'=>false,'msg'=>'Ese folder ya esta ocupado por otro accidente pendiente o con diligencias.']);
        exit;
      }
      $st = $pdo->prepare("UPDATE accidentes SET folder=? WHERE id=?");
      $st->execute([$n,$id]);
      echo json_encode(['ok'=>true,'val'=>$n,'occupied_folders'=>occupied_folder_map($pdo)]);
    } else {
      echo json_encode(['ok'=>false,'msg'=>'Folder invÃ¡lido (vacÃ­o o 1..20)']);
    }
  }
  exit;
}

/* NEW: handler para prioridad (priority) */
if (($_POST['ajax'] ?? '') === 'priority') {
  $id = (int)($_POST['id'] ?? 0);
  $raw = $_POST['priority'] ?? '';
  header('Content-Type: application/json; charset=utf-8');

  if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'ID invÃ¡lido']); exit; }

  // Permitimos '1' o '0' o valores vacÃ­os (-> 0)
  $n = ($raw === '' ? 0 : ((int)$raw ? 1 : 0));
  $st = $pdo->prepare("UPDATE accidentes SET priority=? WHERE id=?");
  $st->execute([$n, $id]);

  echo json_encode(['ok'=>true,'val'=>$n]);
  exit;
}

/* ============================
   FILTROS
============================ */
$filterKeys = [
  'q',
  'desde',
  'hasta',
  'comisaria_id',
  'persona',
  'distrito',
  'vehiculo',
  'registro_sidpol',
  'nro_informe_policial',
  'tipo_registro',
  'estado',
  'orden',
  'favoritos',
  'ver_todos',
];

if (isset($_GET['limpiar_recuerdo'])) {
  unset($_SESSION['accidente_listar_ultimo_filtro'], $_SESSION['accidente_ultimo_abierto']);
  header('Location: accidente_listar.php');
  exit;
}

$hasIncomingFilters = false;
foreach ($filterKeys as $filterKey) {
  if (array_key_exists($filterKey, $_GET)) {
    $hasIncomingFilters = true;
    break;
  }
}

$restoredLastFilters = false;

$ultimoAccidenteAbiertoId = (int)($_SESSION['accidente_ultimo_abierto'] ?? 0);
$favoritos = trim((string)($_GET['favoritos'] ?? '')) === '1' ? '1' : '';
$verTodos = trim((string)($_GET['ver_todos'] ?? '')) === '1' ? '1' : '';

$q        = trim($_GET['q'] ?? '');
$desde    = trim($_GET['desde'] ?? '');
$hasta    = trim($_GET['hasta'] ?? '');
$comisaria_id = trim($_GET['comisaria_id'] ?? '');
$persona   = trim($_GET['persona']  ?? '');
$distrito  = trim($_GET['distrito'] ?? '');
$vehiculo  = trim($_GET['vehiculo'] ?? '');
$registro_sidpol = trim($_GET['registro_sidpol'] ?? ''); // <-- NUEVO
$nro_informe_policial = trim($_GET['nro_informe_policial'] ?? '');
$tipoRegistroOpciones = [
  '' => 'TODOS',
  'Carpeta' => 'CARPETA',
  'Intervencion' => 'INTERVENCIÓN',
];
$tipo_registro = trim($_GET['tipo_registro'] ?? '');
if (!array_key_exists($tipo_registro, $tipoRegistroOpciones)) {
  $tipo_registro = '';
}
$estadoOpciones = [
  'todos' => 'TODOS',
  'Pendiente' => 'PENDIENTE',
  'Resuelto' => 'RESUELTO',
  'Con diligencias' => 'CON DILIGENCIAS',
];
$estadoFiltro = trim($_GET['estado'] ?? 'Pendiente');
if (!array_key_exists($estadoFiltro, $estadoOpciones)) {
  $estadoFiltro = 'Pendiente';
}
$ordenOpciones = [
  'registro_desc' => 'RECIÉN REGISTRADO',
  'folder_asc' => 'FOLDER: MENOR A MAYOR',
  'fecha_desc' => 'FECHA ACCIDENTE: RECIENTE A ANTIGUA',
  'fecha_asc' => 'FECHA ACCIDENTE: ANTIGUA A RECIENTE',
];
$orden = trim($_GET['orden'] ?? 'registro_desc');
if (!array_key_exists($orden, $ordenOpciones)) {
  $orden = 'registro_desc';
}

$currentFilters = [
  'q' => $q,
  'desde' => $desde,
  'hasta' => $hasta,
  'comisaria_id' => $comisaria_id,
  'persona' => $persona,
  'distrito' => $distrito,
  'vehiculo' => $vehiculo,
  'registro_sidpol' => $registro_sidpol,
  'nro_informe_policial' => $nro_informe_policial,
  'tipo_registro' => $tipo_registro,
  'estado' => $estadoFiltro,
  'orden' => $orden,
  'favoritos' => $favoritos,
  'ver_todos' => $verTodos,
];

if ($hasIncomingFilters) {
  $_SESSION['accidente_listar_ultimo_filtro'] = array_filter(
    $currentFilters,
    static fn($value): bool => trim((string)$value) !== ''
  );
}

/* ============================
   LISTA DE ComisariaS
============================ */
$comisarias = $pdo->query("SELECT id, nombre FROM comisarias ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$comisariasPorDistrito = [];
$sqlComisariasDistrito = "SELECT c.id, c.nombre AS comisaria, d.nombre AS distrito, COUNT(a.id) AS accidentes_total
                            FROM comisarias c
                            JOIN comisaria_distrito cd ON cd.comisaria_id = c.id
                            JOIN ubigeo_distrito d
                              ON d.cod_dep = cd.cod_dep
                             AND d.cod_prov = cd.cod_prov
                             AND d.cod_dist = cd.cod_dist
                       LEFT JOIN accidentes a ON a.comisaria_id = c.id
                        GROUP BY c.id, c.nombre, d.nombre
                        ORDER BY COALESCE(d.nombre, 'Sin distrito asignado'), c.nombre";
foreach ($pdo->query($sqlComisariasDistrito)->fetchAll(PDO::FETCH_ASSOC) as $comisariaDistrito) {
  $distritoNombre = trim((string)($comisariaDistrito['distrito'] ?? ''));
  if ($distritoNombre === '') continue;
  $comisariasPorDistrito[$distritoNombre][] = $comisariaDistrito;
}
$districtSelected = false;
if ($distrito !== '') {
  foreach (array_keys($comisariasPorDistrito) as $availableDistrict) {
    if (lower_u((string)$availableDistrict) === lower_u($distrito)) {
      $distrito = (string)$availableDistrict;
      $districtSelected = true;
      break;
    }
  }
}
$stationSelected = false;
if ($districtSelected && $comisaria_id !== '') {
  foreach ($comisariasPorDistrito[$distrito] as $districtStation) {
    if ((string)($districtStation['id'] ?? '') === $comisaria_id) {
      $stationSelected = true;
      break;
    }
  }
}
$districtHues = [326, 266, 220, 190, 158, 42, 18, 350, 286, 205, 132, 62];
$selectedDistrictHue = null;
foreach (array_keys($comisariasPorDistrito) as $districtIndex => $districtName) {
  if ($distrito !== '' && lower_u($distrito) === lower_u((string)$districtName)) {
    $selectedDistrictHue = $districtHues[$districtIndex % count($districtHues)];
    break;
  }
}

/* ============================
   QUERY BASE
============================ */
// âžœ AÃ±adimos a.estado, a.folder y a.priority
$sql = "SELECT a.id,a.registro_sidpol,a.tipo_registro,a.nro_informe_policial,a.lugar,a.fecha_accidente,a.estado,a.folder,a.priority,a.latitud,a.longitud,c.nombre AS comisaria, ud.nombre AS distrito,
               fa.nombre AS fiscalia, TRIM(CONCAT_WS(' ', fi.nombres, fi.apellido_paterno, fi.apellido_materno)) AS fiscal,
               COALESCE(dpc.diligencias_pendientes, 0) AS diligencias_pendientes
        FROM accidentes a
        LEFT JOIN comisarias c ON c.id=a.comisaria_id
        LEFT JOIN ubigeo_distrito ud
               ON ud.cod_dep = a.cod_dep
              AND ud.cod_prov = a.cod_prov
              AND ud.cod_dist = a.cod_dist
        LEFT JOIN fiscalia fa ON fa.id = a.fiscalia_id
        LEFT JOIN fiscales fi ON fi.id = a.fiscal_id
        LEFT JOIN (
          SELECT accidente_id, COUNT(*) AS diligencias_pendientes
            FROM diligencias_pendientes
           WHERE COALESCE(NULLIF(TRIM(estado), ''), 'Pendiente') = 'Pendiente'
           GROUP BY accidente_id
        ) dpc ON dpc.accidente_id = a.id
        WHERE 1=1";
$params = [];

if($q!==''){
  $sql .= " AND (a.registro_sidpol LIKE ? OR a.lugar LIKE ?)";
  $params[]="%$q%"; $params[]="%$q%";
}

if($registro_sidpol !== ''){
  $sql .= " AND a.registro_sidpol LIKE ?";
  $params[] = "%$registro_sidpol%";
}

if($nro_informe_policial !== ''){
  $sql .= " AND a.nro_informe_policial LIKE ?";
  $params[] = "%$nro_informe_policial%";
}
if($tipo_registro !== ''){
  $sql .= " AND a.tipo_registro = ?";
  $params[] = $tipo_registro;
}


if($desde!==''){
  $sql .= " AND a.fecha_accidente>=?";
  $params[]=$desde;
}
if($hasta!==''){
  $sql .= " AND a.fecha_accidente<=?";
  $params[]=$hasta;
}
if($comisaria_id!==''){
  $sql .= " AND a.comisaria_id=?";
  $params[]=$comisaria_id;
}
if($estadoFiltro !== 'todos'){
  $sql .= " AND COALESCE(NULLIF(TRIM(a.estado), ''), 'Pendiente') = ?";
  $params[] = $estadoFiltro;
}
if($favoritos === '1'){
  $sql .= " AND COALESCE(a.priority, 0) = 1";
}

/* PERSONA: nombres o apellidos */
if($persona!==''){
  $sql .= " AND EXISTS (
              SELECT 1
                FROM involucrados_personas ip
                JOIN personas p ON p.id = ip.persona_id
               WHERE ip.accidente_id = a.id
                 AND (
                   CONCAT_WS(' ', p.nombres, p.apellido_paterno, p.apellido_materno) LIKE ?
                   OR p.nombres LIKE ?
                   OR p.apellido_paterno LIKE ?
                   OR p.apellido_materno LIKE ?
                 )
            )";
  $params[] = "%$persona%";
  $params[] = "%$persona%";
  $params[] = "%$persona%";
  $params[] = "%$persona%";
}

/* DISTRITO: por nombre del distrito del ubigeo del accidente */
if($distrito!==''){
  $sql .= " AND EXISTS (
              SELECT 1
                FROM ubigeo_distrito d
               WHERE d.cod_dep = a.cod_dep
                 AND d.cod_prov = a.cod_prov
                 AND d.cod_dist = a.cod_dist
                 AND d.nombre LIKE ?
            )";
  $params[] = "%$distrito%";
}

/* vehiculo: por placa */
if($vehiculo!==''){
  $sql .= " AND EXISTS (
              SELECT 1
                FROM involucrados_vehiculos iv
                JOIN vehiculos v ON v.id = iv.vehiculo_id
               WHERE iv.accidente_id = a.id
                 AND v.placa LIKE ?
            )";
  $params[] = "%$vehiculo%";
}

/* El orden por Folder deja los vacios al final. */
$manualFolderOrder = "CASE WHEN a.folder IS NULL THEN 1 ELSE 0 END ASC, a.folder ASC";
$orderBy = match ($orden) {
  'folder_asc' => $manualFolderOrder . ', a.id DESC',
  'fecha_desc' => 'a.fecha_accidente DESC, a.id DESC',
  'fecha_asc' => 'a.fecha_accidente ASC, a.id ASC',
  default => 'a.id DESC',
};
$lastOpenedOrder = ($estadoFiltro !== 'todos' && !$hasIncomingFilters && !$restoredLastFilters && $ultimoAccidenteAbiertoId > 0)
  ? "CASE WHEN a.id = $ultimoAccidenteAbiertoId THEN 1 ELSE 0 END DESC, "
  : '';
$folderOrder = $estadoFiltro === 'todos' && $orden !== 'folder_asc'
  ? $manualFolderOrder . ', '
  : '';
$sql .= " ORDER BY {$lastOpenedOrder}{$folderOrder}COALESCE(a.priority, 0) DESC, $orderBy LIMIT 200";
$rows = [];
if ($stationSelected || $favoritos === '1' || $verTodos === '1') {
  $st=$pdo->prepare($sql);
  $st->execute($params);
  $rows=$st->fetchAll(PDO::FETCH_ASSOC);
}
$occupiedFolders = occupied_folder_map($pdo);

$personasResumenPorAccidente = [];
$personasDetallePorAccidente = [];
$vehiculosResumenPorAccidente = [];
$modalidadesPorAccidente = [];
$accidenteIds = array_values(array_unique(array_map(static fn($row) => (int)($row['id'] ?? 0), $rows)));
if ($accidenteIds !== []) {
  $marks = implode(',', array_fill(0, count($accidenteIds), '?'));
  $sqlModalidades = "SELECT am.accidente_id, m.nombre
                       FROM accidente_modalidad am
                       JOIN modalidad_accidente m ON m.id = am.modalidad_id
                      WHERE am.accidente_id IN ($marks)
                      ORDER BY am.accidente_id ASC, m.nombre ASC";
  $stModalidades = $pdo->prepare($sqlModalidades);
  $stModalidades->execute($accidenteIds);
  while ($modalidadRow = $stModalidades->fetch(PDO::FETCH_ASSOC)) {
    $modalidadAccidenteId = (int)($modalidadRow['accidente_id'] ?? 0);
    $modalidadNombre = trim((string)($modalidadRow['nombre'] ?? ''));
    if ($modalidadAccidenteId > 0 && $modalidadNombre !== '') {
      $modalidadesPorAccidente[$modalidadAccidenteId][] = $modalidadNombre;
    }
  }

  $sqlInv = "SELECT ip.accidente_id,
                    ip.vehiculo_id,
                    p.nombres, p.apellido_paterno, p.apellido_materno,
                    ip.lesion,
                    rp.Nombre AS rol_nombre
               FROM involucrados_personas ip
               JOIN personas p ON p.id = ip.persona_id
               JOIN participacion_persona rp ON rp.Id = ip.rol_id
              WHERE ip.accidente_id IN ($marks)
              ORDER BY ip.accidente_id ASC, rp.Nombre ASC, p.apellido_paterno ASC, p.apellido_materno ASC, p.nombres ASC";
  $stInv = $pdo->prepare($sqlInv);
  $stInv->execute($accidenteIds);

  while ($inv = $stInv->fetch(PDO::FETCH_ASSOC)) {
    $accId = (int)($inv['accidente_id'] ?? 0);
    if ($accId <= 0) continue;

    $nombre = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
      (string)($inv['nombres'] ?? ''),
      (string)($inv['apellido_paterno'] ?? ''),
      (string)($inv['apellido_materno'] ?? ''),
    ], static fn($part) => trim((string)$part) !== ''))));

    if ($nombre === '') continue;

    $rolNombre = trim((string)($inv['rol_nombre'] ?? ''));
    $lesionUi = normalizar_lesion_resumen($inv['lesion'] ?? '');

    $personasDetallePorAccidente[$accId][] = [
      'nombre' => $nombre,
      'rol' => ($rolNombre !== '' ? $rolNombre : 'Persona'),
      'lesion' => $lesionUi,
      'vehiculo_id' => (int)($inv['vehiculo_id'] ?? 0),
    ];

    $rolUi = normalizar_rol_resumen($rolNombre);
    if ($rolUi === null) continue;

    $personasResumenPorAccidente[$accId][] = [
      'nombre' => $nombre,
      'rol' => $rolUi,
      'lesion' => $lesionUi,
    ];
  }
  $sqlVeh = "SELECT iv.accidente_id,
                    iv.vehiculo_id,
                    iv.orden_participacion,
                    iv.tipo AS vinculo_tipo,
                    v.placa,
                    tv.nombre AS tipo_nombre,
                    car.nombre AS carroceria_nombre,
                    m.nombre AS marca_nombre,
                    mo.nombre AS modelo_nombre
               FROM involucrados_vehiculos iv
               JOIN vehiculos v ON v.id = iv.vehiculo_id
               LEFT JOIN tipos_vehiculo tv ON tv.id = v.tipo_id
               LEFT JOIN carroceria_vehiculo car ON car.id = v.carroceria_id
               LEFT JOIN marcas_vehiculo m ON m.id = v.marca_id
               LEFT JOIN modelos_vehiculo mo ON mo.id = v.modelo_id
              WHERE iv.accidente_id IN ($marks)
              ORDER BY iv.accidente_id ASC, CAST(COALESCE(iv.orden_participacion,'0') AS UNSIGNED) ASC, iv.id ASC";
  $stVeh = $pdo->prepare($sqlVeh);
  $stVeh->execute($accidenteIds);

  while ($veh = $stVeh->fetch(PDO::FETCH_ASSOC)) {
    $accId = (int)($veh['accidente_id'] ?? 0);
    if ($accId <= 0) continue;

    $vehiculosResumenPorAccidente[$accId][] = [
      'vehiculo_id' => (int)($veh['vehiculo_id'] ?? 0),
      'orden' => trim((string)($veh['orden_participacion'] ?? '')),
      'placa' => placa_visible($veh['placa'] ?? ''),
      'tipo' => vehiculo_tipo_resumen($veh),
      'marca_modelo' => trim((string)implode(' ', array_filter([
        trim((string)($veh['marca_nombre'] ?? '')),
        trim((string)($veh['modelo_nombre'] ?? '')),
      ], static fn($part) => $part !== ''))),
    ];
  }

  foreach ($personasDetallePorAccidente as $accId => &$personas) {
    $vehiculosPorId = [];
    foreach ($vehiculosResumenPorAccidente[$accId] ?? [] as $vehiculo) {
      if (($vehiculo['vehiculo_id'] ?? 0) > 0) $vehiculosPorId[(int)$vehiculo['vehiculo_id']] = $vehiculo;
    }
    foreach ($personas as &$persona) {
      $persona['vehiculo'] = $vehiculosPorId[(int)($persona['vehiculo_id'] ?? 0)] ?? null;
    }
    unset($persona);
  }
  unset($personas);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Accidentes | UIAT Norte</title>
<style>
/* ===== Variables de esta vista, atadas al tema global ===== */
html{
  --tbl-head-bg:#eef2ff;
  --tbl-head-bd:#00000014;
  --tbl-row-bg:#ffffff;
  --tbl-row-alt:#fafafa;
  --tbl-row-hover:#f3f4f6;
  --tbl-bd:#00000014;
}
html[data-theme-resolved="dark"]{
  --tbl-head-bg:#0f1628;
  --tbl-head-bd:#ffffff1f;
  --tbl-row-bg:#0f1422;
  --tbl-row-alt:#11192b;
  --tbl-row-hover:#1b2236;
  --tbl-bd:#ffffff1f;
}

/* ===== Layout base ===== */
*{box-sizing:border-box}
body{margin:0;overflow-x:hidden;background:var(--bg);color:var(--fg);font:13px system-ui} /* ligera reducciÃ³n global */
.wrap{max-width:1480px;margin:20px auto;padding:14px}
.title{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:10px}
.toolbar{display:flex;gap:8px;flex-wrap:wrap}
.browse-panel{padding:20px}
.browse-heading{margin:0;font-size:22px;font-weight:900}
.browse-copy{margin:5px 0 18px;color:rgba(var(--muted-rgb),1);font-size:13px}
.browse-back{margin-bottom:16px}
.district-tree{display:grid;grid-template-columns:minmax(190px,260px) 70px minmax(0,1fr);align-items:center;gap:0;margin-top:24px;overflow:hidden}
.district-tree-root{
  position:relative;z-index:2;display:flex;align-items:center;justify-content:center;min-height:116px;padding:20px;
  border:2px solid hsl(var(--district-hue) 78% 53%);border-radius:20px;
  background:linear-gradient(135deg,hsl(var(--district-hue) 80% 55%),hsl(var(--district-hue) 86% 34%));
  color:#fff;font-size:19px;font-weight:950;text-align:center;text-transform:uppercase;
  box-shadow:0 16px 34px hsl(var(--district-hue) 60% 28% / .28),inset 0 1px 0 rgba(255,255,255,.35);
  animation:treeRootIn .32s ease-out both;
}
.district-tree-trunk{position:relative;align-self:stretch;min-height:130px}
.district-tree-trunk::before{content:"";position:absolute;left:0;top:50%;width:50%;height:3px;background:hsl(var(--district-hue) 72% 52% / .72);transform:translateY(-50%)}
.district-tree-trunk::after{content:"";position:absolute;right:0;top:10%;bottom:10%;width:3px;border-radius:99px;background:hsl(var(--district-hue) 72% 52% / .72)}
.district-tree-branches{display:grid;gap:13px;padding:6px 0}
.district-tree-branch{position:relative;padding-left:34px;animation:treeBranchIn .36s ease-out both}
.district-tree-branch::before{content:"";position:absolute;left:0;top:50%;width:34px;height:3px;background:hsl(var(--district-hue) 72% 52% / .72);transform:translateY(-50%)}
.district-tree .station-btn{min-height:68px;font-size:14px}
.district-tree-branch:nth-child(2){animation-delay:.05s}.district-tree-branch:nth-child(3){animation-delay:.10s}.district-tree-branch:nth-child(4){animation-delay:.15s}.district-tree-branch:nth-child(n+5){animation-delay:.20s}
@keyframes treeRootIn{from{opacity:0;transform:translateX(-18px) scale(.96)}to{opacity:1;transform:none}}
@keyframes treeBranchIn{from{opacity:0;transform:translateX(-24px)}to{opacity:1;transform:none}}

/* Selector de comisaría inspirado en un cuaderno abierto */
.station-browser{border:0;background:transparent;box-shadow:none;backdrop-filter:none;padding:8px 0 30px}
.station-browser-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:8px}
.outside-click-hint{color:rgba(var(--muted-rgb),1);font-size:12px;font-weight:750;white-space:nowrap}
.station-browser .district-tree{display:flex;align-items:center;justify-content:center;min-height:330px;margin:12px auto 0;padding:28px 24px;overflow:visible;isolation:isolate}
.station-browser .district-tree-root{
  position:relative;z-index:10;flex:0 0 270px;min-height:250px;padding:34px 28px 34px 45px;border:1px solid hsl(var(--district-hue) 88% 62%);
  border-radius:9px 22px 22px 9px;background:
    radial-gradient(circle at 82% 18%,rgba(255,255,255,.23),transparent 28%),
    linear-gradient(145deg,hsl(var(--district-hue) 84% 56%),hsl(var(--district-hue) 88% 33%));
  box-shadow:10px 16px 36px hsl(var(--district-hue) 60% 24% / .25),inset 12px 0 16px rgba(0,0,0,.14),inset 2px 0 rgba(255,255,255,.2);
  animation:notebookCoverIn .45s cubic-bezier(.2,.8,.2,1) both;
}
.station-browser .district-tree-root::before{content:"";position:absolute;left:19px;top:17px;bottom:17px;width:4px;border-radius:99px;background:rgba(255,255,255,.5);box-shadow:-7px 0 0 rgba(0,0,0,.12)}
.station-browser .district-tree-root::after{content:"";position:absolute;z-index:-1;left:8px;right:-9px;bottom:-9px;height:18px;border-radius:0 0 13px 6px;background:repeating-linear-gradient(0deg,#fff 0 2px,#dbe2ea 2px 3px);transform:skewX(-28deg);transform-origin:left top;box-shadow:0 7px 12px rgba(15,23,42,.12)}
.notebook-icon{display:block;margin-bottom:18px;font-size:30px;filter:drop-shadow(0 4px 6px rgba(0,0,0,.18))}
.notebook-kicker{display:block;margin-bottom:8px;font-size:10px;font-weight:900;letter-spacing:.18em;opacity:.72}
.station-browser .district-tree-root .district-title{font-size:23px;line-height:1.12;overflow-wrap:anywhere}
.station-browser .district-tree-trunk{display:none}
.station-browser .district-tree-branches{
  position:relative;z-index:4;display:grid;grid-template-columns:repeat(2,minmax(220px,300px));gap:14px 16px;
  width:auto;margin-left:-14px;padding:18px 0 18px 0;
}
.station-browser .district-tree-branches::before,.station-browser .district-tree-branches::after{
  content:"";position:absolute;z-index:-2;left:-7px;right:16px;height:82%;top:9%;border-radius:4px 17px 17px 4px;background:#f8fafc;border:1px solid #dce3ec;box-shadow:7px 10px 22px rgba(15,23,42,.10);transform:rotate(-1.4deg)
}
.station-browser .district-tree-branches::after{z-index:-1;left:-2px;right:7px;background:#fff;transform:rotate(.9deg)}
.station-browser .district-tree-branch{padding:0;animation:notebookPageOut .52s cubic-bezier(.18,.86,.28,1) both;transform-origin:left center}
.station-browser .district-tree-branch::before{display:none}
.station-browser .district-tree-branch:nth-child(2){animation-delay:.07s}.station-browser .district-tree-branch:nth-child(3){animation-delay:.14s}.station-browser .district-tree-branch:nth-child(4){animation-delay:.21s}.station-browser .district-tree-branch:nth-child(n+5){animation-delay:.28s}
.station-browser .station-btn{
  min-height:76px;width:100%;padding:14px 16px 14px 22px;border:1px solid hsl(var(--district-hue) 60% 67% / .55);border-radius:5px 15px 15px 5px;
  background:linear-gradient(100deg,#fff,hsl(var(--district-hue) 100% 98%));color:hsl(var(--district-hue) 62% 23%);font-size:13px;
  box-shadow:5px 7px 16px rgba(15,23,42,.09),inset 4px 0 hsl(var(--district-hue) 80% 55% / .25);transform:none;
}
.station-browser .station-btn:hover,.station-browser .station-btn:focus-visible{transform:translateX(7px) rotate(.35deg);box-shadow:9px 12px 24px hsl(var(--district-hue) 50% 28% / .16);background:#fff}
.station-symbol{flex:0 0 auto;display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:hsl(var(--district-hue) 82% 54% / .12);font-size:17px}
.station-label{flex:1;min-width:0}
.station-label small{display:block;margin-top:3px;color:#7b8794;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.08em}
.station-browser .station-count{min-width:31px;padding:5px 8px;background:hsl(var(--district-hue) 82% 54% / .12)}
@keyframes notebookCoverIn{from{opacity:0;transform:translateX(-25px) rotateY(-16deg)}to{opacity:1;transform:none}}
@keyframes notebookPageOut{from{opacity:0;transform:translateX(-110px) scaleX(.72) rotate(-3deg)}to{opacity:1;transform:none}}
html[data-theme-resolved="dark"] .station-browser .district-tree-branches::before{background:#111827;border-color:#334155}
html[data-theme-resolved="dark"] .station-browser .district-tree-branches::after{background:#172033;border-color:#334155}
html[data-theme-resolved="dark"] .station-browser .station-btn{background:linear-gradient(100deg,#172033,hsl(var(--district-hue) 35% 17%));color:hsl(var(--district-hue) 78% 86%);border-color:hsl(var(--district-hue) 48% 52% / .5)}
.district-accident-layout{display:block}
.district-sidebar{
  position:relative;padding:0;border:0;border-radius:18px;
  background:linear-gradient(155deg,rgba(255,255,255,.96),rgba(237,242,255,.82));
  box-shadow:none;
  overflow:hidden;
}
.district-sidebar::before{display:none}
.district-sidebar-title{margin:0 0 14px;color:#475569;font-size:11px;font-weight:950;letter-spacing:.16em;text-transform:uppercase}
.district-buttons{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}
.district-group{display:grid;gap:5px;min-width:0}
.district-main{min-width:0}
.station-clear{
  display:flex;align-items:center;justify-content:center;min-height:34px;margin-top:10px;padding:7px 10px;
  border:1px solid rgba(100,116,139,.35);border-radius:11px;background:linear-gradient(145deg,#fff,#edf2f7);color:#334155;
  font-size:10px;font-weight:900;text-decoration:none;text-transform:uppercase;letter-spacing:.04em;box-shadow:0 6px 14px rgba(15,23,42,.10),inset 0 1px 0 #fff;
}
.station-clear:hover{border-color:#6366f1;color:#4338ca;box-shadow:0 0 16px rgba(99,102,241,.22)}
.station-clear.active{background:linear-gradient(135deg,#111827,#334155);border-color:#64748b;color:#fff;box-shadow:0 0 18px rgba(99,102,241,.28)}
.station-favorites{border-color:rgba(212,175,55,.46);color:#8a6300;background:linear-gradient(135deg,#fff7db,#fff)}
.station-favorites::before{content:"\2605";margin-right:7px;color:#d4af37}
.station-favorites:hover{border-color:#d4af37;color:#6f4b00;box-shadow:0 0 16px rgba(212,175,55,.24)}
.station-favorites.active{background:linear-gradient(135deg,#d4af37,#facc15);border-color:#facc15;color:#111827;box-shadow:0 0 18px rgba(212,175,55,.32)}
.district-btn{
  position:relative;isolation:isolate;width:100%;display:flex;align-items:center;justify-content:center;min-height:116px;padding:22px;border-radius:28px;
  border:1px solid hsl(var(--district-hue) 75% 68% / .48);
  background:
    radial-gradient(circle at 18% 5%,rgba(255,255,255,.96),transparent 28%),
    radial-gradient(circle at 88% 100%,hsl(var(--district-hue) 92% 72% / .23),transparent 44%),
    linear-gradient(145deg,rgba(255,255,255,.74),hsl(var(--district-hue) 100% 97% / .52));
  color:hsl(var(--district-hue) 68% 23%);font-size:18px;font-weight:900;text-align:center;text-transform:uppercase;letter-spacing:.015em;
  text-decoration:none;box-shadow:inset 0 1px 1px rgba(255,255,255,.98),inset 0 -12px 28px hsl(var(--district-hue) 65% 55% / .07),0 12px 26px rgba(15,23,42,.09);cursor:pointer;
  overflow:hidden;backdrop-filter:blur(20px) saturate(155%);-webkit-backdrop-filter:blur(20px) saturate(155%);
  transition:transform .24s cubic-bezier(.2,.75,.25,1),box-shadow .24s ease,border-color .24s ease,background .24s ease;
}
.district-btn::before{content:"";position:absolute;z-index:-1;left:7%;right:7%;top:5px;height:43%;border-radius:24px 24px 50% 50%;background:linear-gradient(180deg,rgba(255,255,255,.68),rgba(255,255,255,.08));filter:blur(.2px);pointer-events:none}
.district-btn::after{content:"";position:absolute;z-index:2;top:-115%;left:-35%;width:32%;height:330%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.82),transparent);transform:rotate(24deg);opacity:.7;transition:left .55s ease;pointer-events:none}
.district-btn:hover,.district-btn:focus-visible{
  transform:translateY(-5px) scale(1.012);outline:none;border-color:hsl(var(--district-hue) 82% 60% / .68);
  background:radial-gradient(circle at 18% 5%,#fff,transparent 30%),linear-gradient(145deg,rgba(255,255,255,.9),hsl(var(--district-hue) 100% 96% / .72));
  box-shadow:inset 0 1px 1px #fff,inset 0 -12px 28px hsl(var(--district-hue) 70% 55% / .09),0 18px 34px hsl(var(--district-hue) 52% 30% / .17),0 0 25px hsl(var(--district-hue) 82% 58% / .10);
}
.district-btn:hover::after,.district-btn:focus-visible::after{left:118%}
.district-btn.active{
  border-color:hsl(var(--district-hue) 92% 63%);
  background:linear-gradient(135deg,hsl(var(--district-hue) 82% 54%),hsl(var(--district-hue) 88% 35%));
  color:#fff;box-shadow:0 10px 24px hsl(var(--district-hue) 68% 30% / .38),0 0 22px hsl(var(--district-hue) 90% 55% / .32),inset 0 1px 0 rgba(255,255,255,.35);
}
.district-btn.active::before{background:linear-gradient(180deg,rgba(255,255,255,.58),rgba(255,255,255,.08));box-shadow:none}
.district-name{position:relative;z-index:1;min-width:0;width:100%;overflow-wrap:anywhere;text-align:center}
.district-chevron{flex:0 0 auto;font-size:13px;line-height:1;opacity:.58;transition:transform .18s ease,opacity .18s ease}
.district-btn:hover .district-chevron{opacity:1;transform:translateX(2px)}
.district-btn.active .district-chevron{opacity:1;transform:rotate(90deg)}
.district-substations{
  display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:0;padding:0;
  border:0;border-radius:0;background:transparent;
  animation:districtSubstationsIn .2s ease-out both;
}
@keyframes districtSubstationsIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}
.station-btn{
  position:relative;display:flex;align-items:center;justify-content:space-between;gap:14px;min-height:100px;padding:18px 20px;border-radius:16px;
  border:1px solid hsl(var(--district-hue) 70% 58% / .42);background:linear-gradient(145deg,rgba(255,255,255,.96),hsl(var(--district-hue) 100% 96%));
  color:hsl(var(--district-hue) 72% 22%);font-size:16px;font-weight:900;line-height:1.25;text-decoration:none;
  box-shadow:0 7px 16px hsl(var(--district-hue) 50% 28% / .12),inset 0 1px 0 #fff;overflow:hidden;transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease;
}
.station-btn::before{content:"";position:absolute;left:0;bottom:0;width:100%;height:2px;background:linear-gradient(90deg,transparent,hsl(var(--district-hue) 90% 55%),transparent);opacity:.45}
.station-count{flex:0 0 auto;min-width:25px;padding:3px 7px;border:1px solid hsl(var(--district-hue) 75% 55% / .24);border-radius:999px;background:hsl(var(--district-hue) 80% 50% / .14);color:inherit;font-size:10px;font-weight:950;text-align:center}
.station-btn.active .station-count{background:rgba(255,255,255,.2)}
.station-btn:hover,.station-btn:focus-visible{transform:translateX(3px);outline:none;border-color:hsl(var(--district-hue) 88% 54%);box-shadow:0 8px 18px hsl(var(--district-hue) 58% 28% / .20),0 0 14px hsl(var(--district-hue) 85% 52% / .14)}
.station-btn.active{background:linear-gradient(135deg,hsl(var(--district-hue) 82% 54%),hsl(var(--district-hue) 88% 35%));border-color:hsl(var(--district-hue) 92% 63%);color:#fff;box-shadow:0 10px 24px hsl(var(--district-hue) 68% 30% / .32),0 0 22px hsl(var(--district-hue) 90% 55% / .25)}
html[data-theme-resolved="dark"] .district-sidebar{background:linear-gradient(160deg,rgba(8,15,31,.98),rgba(17,25,43,.94));border-color:rgba(99,102,241,.38);box-shadow:0 18px 46px rgba(0,0,0,.34),inset 0 1px 0 rgba(148,163,184,.12)}
html[data-theme-resolved="dark"] .district-sidebar-title{color:#9fb0c6}
html[data-theme-resolved="dark"] .station-clear{background:#172033;border-color:#334155;color:#dbe7f5}
html[data-theme-resolved="dark"] .station-clear.active{background:#e2e8f0;border-color:#e2e8f0;color:#0f172a}
html[data-theme-resolved="dark"] .station-favorites{background:rgba(212,175,55,.12);border-color:rgba(212,175,55,.46);color:#facc15}
html[data-theme-resolved="dark"] .station-favorites.active{background:linear-gradient(135deg,#e2c96c,#facc15);border-color:#facc15;color:#111827}
html[data-theme-resolved="dark"] .district-btn{background:radial-gradient(circle at 18% 5%,rgba(255,255,255,.14),transparent 28%),linear-gradient(145deg,rgba(30,41,59,.74),hsl(var(--district-hue) 40% 18% / .64));color:hsl(var(--district-hue) 82% 88%);border-color:hsl(var(--district-hue) 58% 58% / .42);box-shadow:inset 0 1px rgba(255,255,255,.18),0 12px 28px rgba(0,0,0,.24)}
html[data-theme-resolved="dark"] .district-btn.active{color:#fff;background:linear-gradient(135deg,hsl(var(--district-hue) 76% 48%),hsl(var(--district-hue) 80% 35%))}
html[data-theme-resolved="dark"] .district-substations{background:linear-gradient(90deg,hsl(var(--district-hue) 70% 48% / .13),transparent);border-left-color:hsl(var(--district-hue) 72% 60% / .7)}
html[data-theme-resolved="dark"] .station-btn{background:linear-gradient(145deg,rgba(15,23,42,.9),hsl(var(--district-hue) 38% 19% / .72));color:hsl(var(--district-hue) 78% 84%);border-color:hsl(var(--district-hue) 52% 55% / .48);box-shadow:0 8px 18px rgba(0,0,0,.24),0 0 14px hsl(var(--district-hue) 80% 50% / .07)}
html[data-theme-resolved="dark"] .station-btn.active{background:linear-gradient(135deg,hsl(var(--district-hue) 76% 48%),hsl(var(--district-hue) 80% 35%));color:#fff}
@media(max-width:850px){
  .district-accident-layout{grid-template-columns:1fr}
  .district-sidebar{position:static}
  .district-buttons{grid-template-columns:repeat(2,minmax(0,1fr))}
  .district-substations{grid-template-columns:repeat(2,minmax(0,1fr))}
  .district-tree{grid-template-columns:minmax(150px,210px) 46px minmax(0,1fr)}
  .district-tree-branch{padding-left:22px}.district-tree-branch::before{width:22px}
  .station-browser .district-tree{align-items:flex-start;padding-inline:4px}
  .station-browser .district-tree-root{flex-basis:220px;min-height:225px}
  .station-browser .district-tree-branches{grid-template-columns:minmax(220px,310px)}
}
@media(max-width:560px){
  .browse-panel{padding:14px}
  .district-buttons,.district-substations{grid-template-columns:1fr}
  .district-btn,.station-btn{min-height:88px;font-size:15px}
  .district-tree{display:flex;flex-direction:column;align-items:stretch;overflow:visible}
  .district-tree-root{min-height:82px}
  .district-tree-trunk{width:3px;min-height:34px;align-self:center;background:hsl(var(--district-hue) 72% 52% / .72)}
  .district-tree-trunk::before,.district-tree-trunk::after{display:none}
  .district-tree-branches{gap:12px}
  .district-tree-branch{padding:16px 0 0}
  .district-tree-branch::before{left:50%;top:0;width:3px;height:16px;transform:translateX(-50%)}
  .station-browser-head{display:block}.outside-click-hint{display:block;margin-top:8px;white-space:normal}
  .station-browser .district-tree{display:flex;align-items:stretch;padding:10px 2px}
  .station-browser .district-tree-root{flex-basis:auto;min-height:150px;padding:28px 24px 28px 42px}
  .station-browser .district-tree-branches{grid-template-columns:1fr;width:calc(100% - 20px);margin:-9px auto 0;padding:26px 12px 14px}
  .station-browser .district-tree-branch{padding:0}
}

/* Selector Liquid Glass: piezas independientes, sin anchos rígidos ni solapamientos */
.station-browser{width:100%;min-width:0;padding:8px 0 28px}
.station-browser .district-tree{
  position:relative;display:grid;grid-template-columns:minmax(190px,240px) minmax(0,680px);gap:clamp(42px,6vw,86px);
  align-items:center;justify-content:center;width:100%;max-width:1040px;min-height:260px;margin:10px auto 0;padding:28px 18px;overflow:visible;
}
.station-browser .district-tree-root{
  position:relative;z-index:3;display:flex;min-width:0;min-height:150px;padding:26px 22px;border:1px solid rgba(255,255,255,.62);border-radius:34px;
  background:
    radial-gradient(circle at 23% 16%,rgba(255,255,255,.7),transparent 20%),
    radial-gradient(circle at 80% 85%,hsl(var(--district-hue) 92% 68% / .58),transparent 44%),
    linear-gradient(145deg,hsl(var(--district-hue) 88% 61% / .92),hsl(var(--district-hue) 88% 43% / .88));
  color:#fff;box-shadow:inset 0 1px 1px rgba(255,255,255,.75),inset 0 -12px 28px rgba(15,23,42,.12),0 18px 35px hsl(var(--district-hue) 60% 30% / .23);
  backdrop-filter:blur(20px) saturate(145%);-webkit-backdrop-filter:blur(20px) saturate(145%);
  animation:liquidDistrictIn .42s cubic-bezier(.16,.8,.22,1) both;
}
.station-browser .district-tree-root::before{content:"";position:absolute;inset:6px;border:1px solid rgba(255,255,255,.28);border-radius:28px;box-shadow:inset 0 0 18px rgba(255,255,255,.15);pointer-events:none}
.station-browser .district-tree-root::after{content:"";position:absolute;left:auto;right:-52px;top:50%;bottom:auto;width:55px;height:22px;border:0;border-radius:999px;background:linear-gradient(90deg,hsl(var(--district-hue) 80% 56% / .72),transparent);box-shadow:none;filter:blur(8px);transform:translateY(-50%);pointer-events:none}
.station-browser .district-tree-root>span{position:relative;z-index:1;width:100%}
.station-browser .notebook-icon{margin:0 0 12px;font-size:25px;filter:drop-shadow(0 4px 8px rgba(0,0,0,.12))}
.station-browser .notebook-kicker{margin-bottom:7px;font-size:9px;letter-spacing:.2em}
.station-browser .district-tree-root .district-title{display:block;font-size:21px;line-height:1.15}
.station-browser .district-tree-root::after,.station-browser .district-tree-root::before{box-sizing:border-box}
.station-browser .district-tree-trunk{display:none}
.station-browser .district-tree-branches{
  position:relative;z-index:2;display:grid;grid-template-columns:repeat(2,minmax(190px,1fr));gap:14px;width:100%;min-width:0;margin:0;padding:0;
}
.station-browser .district-tree-branches::before,.station-browser .district-tree-branches::after{display:none}
.station-browser .district-tree-branch{min-width:0;padding:0;transform-origin:-65px 50%;animation:aladdinRelease .62s cubic-bezier(.16,.84,.25,1) both}
.station-browser .district-tree-branch::before{display:none}
.station-browser .district-tree-branch:nth-child(2){animation-delay:.08s}.station-browser .district-tree-branch:nth-child(3){animation-delay:.16s}.station-browser .district-tree-branch:nth-child(4){animation-delay:.24s}.station-browser .district-tree-branch:nth-child(n+5){animation-delay:.30s}
.station-browser .station-btn{
  position:relative;isolation:isolate;width:100%;min-width:0;min-height:78px;padding:13px 14px;border:1px solid hsl(var(--district-hue) 70% 67% / .48);border-radius:24px;
  background:linear-gradient(135deg,rgba(255,255,255,.76),hsl(var(--district-hue) 100% 96% / .6));color:hsl(var(--district-hue) 64% 23%);
  box-shadow:inset 0 1px 1px rgba(255,255,255,.95),inset 0 -8px 18px hsl(var(--district-hue) 70% 55% / .06),0 10px 24px rgba(15,23,42,.10);
  backdrop-filter:blur(18px) saturate(150%);-webkit-backdrop-filter:blur(18px) saturate(150%);overflow:hidden;transform:none;
}
.station-browser .station-btn::after{content:"";position:absolute;z-index:-1;left:-20%;top:-80%;width:70%;height:210%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.75),transparent);transform:rotate(24deg);transition:left .42s ease}
.station-browser .station-btn:hover::after,.station-browser .station-btn:focus-visible::after{left:105%}
.station-browser .station-btn:hover,.station-browser .station-btn:focus-visible{transform:translateY(-4px) scale(1.018);border-color:hsl(var(--district-hue) 80% 58% / .68);background:linear-gradient(135deg,rgba(255,255,255,.92),hsl(var(--district-hue) 100% 96% / .76));box-shadow:inset 0 1px 1px #fff,0 16px 30px hsl(var(--district-hue) 48% 30% / .17)}
.station-browser .station-symbol{background:hsl(var(--district-hue) 80% 56% / .13);border:1px solid rgba(255,255,255,.6);box-shadow:inset 0 1px rgba(255,255,255,.8)}
.station-browser .station-label{overflow-wrap:anywhere}
@keyframes liquidDistrictIn{from{opacity:0;transform:scale(.82);filter:blur(10px)}to{opacity:1;transform:scale(1);filter:blur(0)}}
@keyframes aladdinRelease{
  0%{opacity:0;transform:translateX(-145px) translateY(26px) scale(.08,.32) rotate(-9deg);filter:blur(18px)}
  55%{opacity:.78;transform:translateX(10px) translateY(-8px) scale(1.04,.92) rotate(1.5deg);filter:blur(2px)}
  100%{opacity:1;transform:none;filter:blur(0)}
}
html[data-theme-resolved="dark"] .station-browser .station-btn{background:linear-gradient(135deg,rgba(30,41,59,.8),hsl(var(--district-hue) 38% 18% / .72));color:hsl(var(--district-hue) 78% 88%);border-color:hsl(var(--district-hue) 55% 58% / .48)}
@media(max-width:850px){
  .station-browser .district-tree{grid-template-columns:minmax(160px,210px) minmax(0,1fr);gap:36px;padding-inline:8px}
  .station-browser .district-tree-branches{grid-template-columns:1fr}
}
@media(max-width:560px){
  .station-browser .district-tree{display:grid;grid-template-columns:1fr;gap:30px;padding:10px 0}
  .station-browser .district-tree-root{width:min(100%,280px);min-height:125px;justify-self:center}
  .station-browser .district-tree-root::after{right:50%;top:auto;bottom:-30px;width:22px;height:34px;transform:translateX(50%) rotate(90deg)}
  .station-browser .district-tree-branches{grid-template-columns:1fr}
  .station-browser .district-tree-branch{transform-origin:50% -30px}
  @keyframes aladdinRelease{0%{opacity:0;transform:translateY(-90px) scale(.1,.28);filter:blur(16px)}60%{opacity:.8;transform:translateY(8px) scale(1.03,.94);filter:blur(2px)}100%{opacity:1;transform:none;filter:blur(0)}}
}

/* Navegador curvo maestro-detalle de distritos y comisarías */
.district-browser-stage{display:grid;grid-template-columns:minmax(310px,400px) minmax(380px,680px);gap:clamp(34px,6vw,86px);align-items:center;justify-content:center;min-height:540px;padding:8px 10px 24px}
.district-wheel-wrap{position:relative;min-width:0}
.district-wheel-wrap::before,.district-wheel-wrap::after{content:"";position:absolute;z-index:4;left:0;right:0;height:76px;pointer-events:none}
.district-wheel-wrap::before{top:0;background:linear-gradient(var(--bg),transparent)}
.district-wheel-wrap::after{bottom:0;background:linear-gradient(transparent,var(--bg))}
.district-wheel{height:500px;overflow-y:auto;overflow-x:hidden;padding:199px 34px 199px 10px;scroll-snap-type:y mandatory;scroll-behavior:smooth;scrollbar-width:none;overscroll-behavior:contain}
.district-wheel::-webkit-scrollbar{display:none}
.district-wheel-item{display:flex;align-items:center;justify-content:center;width:calc(100% - 44px);min-height:88px;margin:11px auto;padding:15px 20px;border:1px solid hsl(var(--wheel-hue) 65% 66% / .34);border-radius:28px;background:linear-gradient(145deg,rgba(255,255,255,.58),hsl(var(--wheel-hue) 100% 96% / .34));color:hsl(var(--wheel-hue) 56% 29%);font-size:14px;font-weight:900;line-height:1.15;text-align:center;text-decoration:none;text-transform:uppercase;box-shadow:inset 0 1px rgba(255,255,255,.9),0 8px 18px rgba(15,23,42,.07);backdrop-filter:blur(18px) saturate(145%);scroll-snap-align:center;cursor:pointer;opacity:var(--wheel-opacity,.35);filter:saturate(var(--wheel-saturation,.45)) blur(var(--wheel-blur,0));transform:translateX(var(--wheel-x,-22px)) scale(var(--wheel-scale,.78));transition:transform .2s ease,opacity .2s ease,filter .2s ease,box-shadow .2s ease}
.district-wheel-item.is-active{min-height:106px;border-color:hsl(var(--wheel-hue) 78% 61% / .62);background:radial-gradient(circle at 18% 5%,rgba(255,255,255,.92),transparent 30%),linear-gradient(145deg,hsl(var(--wheel-hue) 87% 63% / .94),hsl(var(--wheel-hue) 80% 43% / .9));color:#fff;font-size:18px;opacity:1;filter:none;transform:translateX(18px) scale(1);box-shadow:inset 0 1px rgba(255,255,255,.85),0 18px 34px hsl(var(--wheel-hue) 55% 28% / .24),0 0 26px hsl(var(--wheel-hue) 80% 58% / .13)}
.district-wheel-item:focus-visible{outline:3px solid hsl(var(--wheel-hue) 80% 58% / .3);outline-offset:2px}
.district-detail{min-width:0}
.district-detail-head{margin-bottom:16px}
.district-detail-kicker{font-size:9px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;color:rgba(var(--muted-rgb),1)}
.district-detail-name{margin:4px 0 0;font-size:22px;font-weight:950;color:var(--fg)}
.district-station-panel{display:none;gap:12px}
.district-station-panel.is-active{display:grid;animation:stationPanelReveal .34s ease-out both}
.district-station-panel .station-btn{min-height:72px;font-size:13px}
.district-station-empty{padding:25px;border:1px dashed var(--field-bd);border-radius:22px;color:rgba(var(--muted-rgb),1);text-align:center}
@keyframes stationPanelReveal{from{opacity:0;transform:translateX(25px);filter:blur(7px)}to{opacity:1;transform:none;filter:none}}
html[data-theme-resolved="dark"] .district-wheel-item{background:linear-gradient(145deg,rgba(30,41,59,.68),hsl(var(--wheel-hue) 35% 18% / .46));color:hsl(var(--wheel-hue) 48% 78%)}
@media(max-width:760px){
  .district-browser-stage{grid-template-columns:1fr;gap:8px;min-height:0;padding-inline:0}
  .district-wheel{height:300px;padding:101px 18px}
  .district-wheel-wrap::before,.district-wheel-wrap::after{height:52px}
  .district-detail{padding:0 8px}.district-detail-head{text-align:center}
}
.card{background:var(--panel-bg);border:1px solid var(--panel-bd);border-radius:14px;padding:14px;backdrop-filter:blur(8px)}
.filter-card{margin-bottom:14px}
.filter-card.filter-glass{position:relative;isolation:isolate;overflow:hidden;padding:18px 20px;border:1px solid rgba(255,255,255,.55);border-radius:26px;background:linear-gradient(145deg,rgba(255,255,255,.68),rgba(255,255,255,.34));box-shadow:inset 0 1px rgba(255,255,255,.95),0 16px 34px rgba(15,23,42,.08);backdrop-filter:blur(24px) saturate(155%);-webkit-backdrop-filter:blur(24px) saturate(155%)}
.filter-card.filter-glass::before{content:"";position:absolute;z-index:-1;left:-80px;top:-100px;width:300px;height:210px;border-radius:50%;background:radial-gradient(circle,rgba(99,102,241,.12),transparent 70%);pointer-events:none}
.filter-card.filter-glass::after{content:"";position:absolute;z-index:-1;right:-80px;bottom:-120px;width:330px;height:230px;border-radius:50%;background:radial-gradient(circle,rgba(6,182,212,.1),transparent 70%);pointer-events:none}
.filter-glass-head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:15px}
.filter-glass-title{display:flex;align-items:center;gap:10px;margin:0;font-size:15px;font-weight:900}
.filter-glass-icon{display:grid;place-items:center;width:34px;height:34px;border:1px solid rgba(255,255,255,.72);border-radius:12px;background:linear-gradient(145deg,rgba(255,255,255,.85),rgba(238,242,255,.55));box-shadow:inset 0 1px #fff,0 7px 15px rgba(15,23,42,.08)}
.filter-mode-chip{padding:6px 11px;border:1px solid rgba(99,102,241,.18);border-radius:999px;background:rgba(99,102,241,.08);color:#4f46e5;font-size:9px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
.filter-glass .filters{margin-bottom:0}
.filter-glass label{margin-bottom:7px;color:#59677a;font-size:10px;font-weight:850;letter-spacing:.055em;text-transform:uppercase}
.filter-glass input,.filter-glass select{min-height:44px;border:1px solid rgba(148,163,184,.28);border-radius:15px;background:linear-gradient(145deg,rgba(255,255,255,.8),rgba(248,250,252,.58));box-shadow:inset 0 1px 2px rgba(255,255,255,.9),0 5px 13px rgba(15,23,42,.045);transition:border-color .18s ease,box-shadow .18s ease,background .18s ease}
.filter-glass input:focus,.filter-glass select:focus{outline:none;border-color:rgba(99,102,241,.5);background:rgba(255,255,255,.92);box-shadow:0 0 0 4px rgba(99,102,241,.1),inset 0 1px #fff}
.filter-glass .filter-actions{padding-top:3px}
.filter-glass .filter-toggle{min-height:38px;padding:7px 13px;border-color:rgba(148,163,184,.28);border-radius:13px;background:rgba(255,255,255,.48);box-shadow:inset 0 1px rgba(255,255,255,.85),0 5px 12px rgba(15,23,42,.05)}
.filter-submit{min-height:39px!important;padding:8px 18px!important;border:1px solid rgba(255,255,255,.5)!important;border-radius:14px!important;background:linear-gradient(135deg,#6366f1,#06b6d4)!important;color:#fff!important;box-shadow:inset 0 1px rgba(255,255,255,.35),0 9px 19px rgba(79,70,229,.2);transition:transform .16s ease,box-shadow .16s ease}
.filter-submit:hover{transform:translateY(-2px);box-shadow:inset 0 1px rgba(255,255,255,.4),0 13px 24px rgba(79,70,229,.27)}
.filter-glass .filter-advanced{margin-top:4px;padding:16px;border:1px solid rgba(148,163,184,.18);border-radius:20px;background:rgba(255,255,255,.28);box-shadow:inset 0 1px rgba(255,255,255,.6)}
html[data-theme-resolved="dark"] .filter-card.filter-glass{border-color:rgba(148,163,184,.2);background:linear-gradient(145deg,rgba(30,41,59,.72),rgba(15,23,42,.55));box-shadow:inset 0 1px rgba(255,255,255,.09),0 16px 36px rgba(0,0,0,.22)}
html[data-theme-resolved="dark"] .filter-glass-icon,html[data-theme-resolved="dark"] .filter-glass input,html[data-theme-resolved="dark"] .filter-glass select{background:linear-gradient(145deg,rgba(51,65,85,.78),rgba(30,41,59,.65));border-color:rgba(148,163,184,.22)}
html[data-theme-resolved="dark"] .filter-glass label{color:#9fb0c6}
@media(max-width:620px){.filter-card.filter-glass{padding:15px;border-radius:21px}.filter-glass-head{align-items:flex-start}.filter-mode-chip{display:none}.filter-glass .filter-actions{align-items:stretch}.filter-glass .filter-action-buttons{margin-left:auto}}
.card.station-browser{border:0;background:transparent;box-shadow:none;backdrop-filter:none;padding:4px 0 24px}
.card.district-browser-home{border:0;background:transparent;box-shadow:none;backdrop-filter:none;padding:28px 22px 38px}
.district-browser-home .district-sidebar{overflow:visible;background:transparent;box-shadow:none}
.district-browser-home .district-group{position:relative}
.district-browser-home .district-group:hover,.district-browser-home .district-group:focus-within{z-index:10}
@media(max-width:560px){.card.district-browser-home{padding:18px 10px 28px}}

/* Selector curvo de comisarías, inspirado en Stage Manager */
.station-stage-layout{display:grid;grid-template-columns:190px minmax(0,1fr);gap:22px;align-items:start}
.station-stage-layout.no-orbit{grid-template-columns:minmax(0,1fr)}
.station-stage-content{min-width:0}
.station-stage-content .acc-card-list{grid-template-columns:repeat(3,minmax(0,1fr))}
.station-orbit{position:sticky;top:18px;display:flex;flex-direction:column;gap:10px;min-width:0;padding:16px 12px 18px;border:1px solid var(--panel-bd);border-radius:32px;background:linear-gradient(160deg,rgba(255,255,255,.48),rgba(255,255,255,.18));box-shadow:inset 0 1px rgba(255,255,255,.75),0 16px 32px rgba(15,23,42,.08);backdrop-filter:blur(20px) saturate(140%);overflow:hidden}
.station-orbit::before{content:"";position:absolute;inset:12% auto 12% -78px;width:150px;border:1px solid hsl(var(--district-hue) 65% 58% / .2);border-radius:50%;pointer-events:none}
.station-orbit-title{position:relative;z-index:1;margin:0 0 4px;color:rgba(var(--muted-rgb),1);font-size:9px;font-weight:900;letter-spacing:.14em;text-align:center;text-transform:uppercase}
.station-orbit-btn{position:relative;z-index:1;display:flex;align-items:center;justify-content:center;width:100%;min-height:58px;padding:10px 11px;border:1px solid hsl(var(--district-hue) 55% 65% / .3);border-radius:20px;background:linear-gradient(145deg,rgba(255,255,255,.62),hsl(var(--district-hue) 100% 97% / .4));color:hsl(var(--district-hue) 55% 28%);font-size:10px;font-weight:850;line-height:1.2;text-align:center;text-decoration:none;opacity:var(--orbit-opacity,.55);filter:saturate(var(--orbit-saturation,.55));transform:translateX(var(--orbit-x,0)) scale(var(--orbit-scale,.9));box-shadow:inset 0 1px rgba(255,255,255,.9),0 7px 15px rgba(15,23,42,.07);transition:transform .22s ease,opacity .22s ease,filter .22s ease,box-shadow .22s ease}
.station-orbit-btn:hover,.station-orbit-btn:focus-visible{opacity:.9;filter:saturate(.9);transform:translateX(10px) scale(.97);outline:none;box-shadow:0 11px 22px rgba(15,23,42,.12)}
.station-orbit-btn.active{min-height:68px;border-color:hsl(var(--district-hue) 76% 58% / .65);background:radial-gradient(circle at 18% 5%,rgba(255,255,255,.9),transparent 32%),linear-gradient(145deg,hsl(var(--district-hue) 86% 62% / .9),hsl(var(--district-hue) 78% 43% / .86));color:#fff;font-size:11px;opacity:1;filter:none;transform:translateX(15px) scale(1);box-shadow:inset 0 1px rgba(255,255,255,.8),0 14px 28px hsl(var(--district-hue) 55% 30% / .23)}
.station-orbit-count{position:absolute;right:7px;bottom:6px;min-width:20px;padding:2px 5px;border-radius:999px;background:rgba(255,255,255,.2);font-size:8px;text-align:center}
html[data-theme-resolved="dark"] .station-orbit{background:linear-gradient(160deg,rgba(30,41,59,.72),rgba(15,23,42,.5))}
html[data-theme-resolved="dark"] .station-orbit-btn:not(.active){background:linear-gradient(145deg,rgba(30,41,59,.7),hsl(var(--district-hue) 34% 17% / .5));color:hsl(var(--district-hue) 45% 78%)}
@media(max-width:1150px){
  .station-stage-content .acc-card-list{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:850px){
  .station-stage-layout{grid-template-columns:1fr;gap:14px}
  .station-orbit{position:static;display:flex;flex-direction:row;gap:9px;padding:11px;overflow-x:auto;border-radius:24px;scroll-snap-type:x proximity}
  .station-orbit::before{display:none}.station-orbit-title{display:none}
  .station-orbit-btn,.station-orbit-btn.active{flex:0 0 155px;min-height:56px;transform:none;opacity:var(--orbit-opacity,.55);scroll-snap-align:center}
  .station-orbit-btn.active{opacity:1}
  .station-orbit-btn:hover,.station-orbit-btn:focus-visible{transform:translateY(-2px)}
  .station-stage-content .acc-card-list{grid-template-columns:minmax(0,1fr)}
}

label{display:block;font-weight:700;margin-bottom:6px;font-size:13px}
input,select{width:100%;padding:8px 10px;border:1px solid var(--field-bd);border-radius:10px;background:var(--field-bg);color:var(--fg)}
.rowflex{display:flex;gap:8px;align-items:center;flex-wrap:wrap}

.btn{display:inline-flex;gap:8px;padding:8px 12px;border:1px solid var(--field-bd);border-radius:10px;background:var(--pill-bg);color:var(--fg);text-decoration:none;font-size:13px}
.btn.primary{background:linear-gradient(90deg,#4f46e5,#06b6d4);border:none;color:#fff}
.btn.small{padding:6px 10px;border-radius:10px;font-weight:700;font-size:12px}
.btn.danger{background:var(--danger);border:none;color:#fff}

/* ===== Badges ===== */
.badge{font-size:12px;padding:3px 8px;border-radius:999px;border:1px solid var(--badge-bd);background:var(--badge-bg)}

/* ===== Caja de filtros ===== */
.filters{display:grid;grid-template-columns:repeat(12,1fr);gap:10px;margin-bottom:10px}
.col-12{grid-column:span 12}.col-6{grid-column:span 6}.col-4{grid-column:span 4}.col-3{grid-column:span 3}.col-2{grid-column:span 2}
.filter-primary{grid-column:span 12;display:grid;grid-template-columns:repeat(12,1fr);gap:10px}
.filter-advanced{grid-column:span 12;display:none;grid-template-columns:repeat(12,1fr);gap:10px;padding-top:10px;border-top:1px solid var(--panel-bd)}
.filter-advanced.open{display:grid}
.filter-actions{grid-column:span 12;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.filter-action-buttons{display:flex;gap:6px;flex-wrap:wrap}
.filter-toggle{
  display:inline-flex;align-items:center;gap:7px;min-height:32px;padding:6px 10px;border:1px solid #cbd5e1;
  border-radius:10px;background:var(--pill-bg);color:var(--fg);font-size:11px;font-weight:850;cursor:pointer;
}
.filter-toggle-icon{font-size:14px;line-height:1;transition:transform .15s ease}
.filter-toggle[aria-expanded="true"] .filter-toggle-icon{transform:rotate(180deg)}
html[data-theme-resolved="dark"] .filter-toggle{border-color:#334155}
.memory-note{
  margin:0 0 10px;
  padding:9px 12px;
  border:1px solid rgba(212,175,55,.38);
  border-radius:12px;
  background:rgba(212,175,55,.10);
  color:#7c5a08;
  font-weight:800;
  font-size:12px;
}
html[data-theme-resolved="dark"] .memory-note{
  background:rgba(212,175,55,.14);
  color:#facc15;
}
@media(max-width:1000px){.col-6,.col-4,.col-3,.col-2{grid-column:span 12}}
@media(max-width:1000px){.filter-primary .col-6{grid-column:span 12}}

/* ===== Tabla ===== */
.table-wrap{overflow:auto;border:1px solid var(--tbl-bd);border-radius:12px}
table{width:100%;border-collapse:separate;border-spacing:0;background:transparent}
thead th{
  position:sticky;top:0;z-index:1;
  background:var(--tbl-head-bg); color:var(--fg);
  text-align:left; font-weight:800; padding:10px; border-bottom:1px solid var(--tbl-head-bd); font-size:13px;
}
tbody td{padding:8px 10px;border-bottom:1px solid var(--tbl-bd); font-size:13px}
tbody tr:nth-child(odd){background:var(--tbl-row-bg)}
tbody tr:nth-child(even){background:var(--tbl-row-alt)}
tbody tr:hover{background:var(--tbl-row-hover)}
tbody tr.last-opened-row{
  box-shadow:inset 4px 0 0 #d4af37;
  background:rgba(212,175,55,.10);
}
th:first-child, td:first-child{padding-left:14px}
th:last-child, td:last-child{padding-right:14px}
.td-actions{white-space:nowrap}
.empty{padding:18px;text-align:center;color:rgba(var(--muted-rgb),1)}
.badge.sidpol-reg { background:transparent; border-color:#d4af37; color:#facc15; font-weight:800; font-size:12px; }
.sidpol-link{ display:inline-block; text-decoration:none; }
.sidpol-link:hover .sidpol-reg,
.sidpol-link:focus-visible .sidpol-reg{ box-shadow:0 0 0 2px rgba(212,175,55,.18); transform:translateY(-1px); }
.inv-people{display:flex;flex-direction:column;gap:4px;min-width:250px}
.inv-person{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.inv-name{font-weight:700}
.inv-meta{display:inline-flex;align-items:center;gap:4px;font-size:11px;padding:2px 8px;border-radius:999px;background:rgba(148,163,184,.16);color:var(--fg)}

/* Refinamiento visual de la lista */
body{font:13px/1.45 Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
thead th{
  padding:11px 12px;
  font-size:12px;
}
tbody td{
  padding:12px;
  vertical-align:top;
}
tbody tr:hover{box-shadow:inset 0 0 0 1px rgba(148,163,184,.08)}
.badge.sidpol-reg{
  background:#fff7db;
  border-color:#f1cc5d;
  color:#b78103;
  font-size:13px;
  padding:5px 10px;
}
.cell-stack{display:flex;flex-direction:column;gap:4px}
.cell-primary{font-size:13px;font-weight:600;color:#14213d;line-height:1.35}
.cell-secondary{font-size:12px;font-weight:500;color:#5c6b7a;line-height:1.35}
.cell-place{max-width:340px}
.cell-date{white-space:nowrap}
.cell-comisaria{max-width:240px}
.inv-people{display:flex;flex-direction:column;gap:8px;min-width:290px;max-width:380px}
.inv-person{display:flex;flex-direction:column;gap:4px}
.inv-name{font-size:13px;line-height:1.35;color:#14213d}
.inv-chips{display:flex;flex-wrap:wrap;gap:6px}
.chip{display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700;line-height:1}
.chip-role-conductor{background:#e8f1ff;color:#1d4ed8}
.chip-role-peaton{background:#f3e8ff;color:#7c3aed}
.chip-status-ileso{background:#e8f7ee;color:#15803d}
.chip-status-herido{background:#fff4e5;color:#b45309}
.chip-status-fallecido{background:#fee2e2;color:#b91c1c}
.chip-more{background:rgba(148,163,184,.16);color:#475569}
.th-people{min-width:320px}
html[data-theme-resolved="dark"] .cell-primary,
html[data-theme-resolved="dark"] .inv-name{color:#e5edf8}
html[data-theme-resolved="dark"] .cell-secondary{color:#9fb0c6}
html[data-theme-resolved="dark"] .badge.sidpol-reg{
  background:rgba(212,175,55,.12);
  border-color:#d4af37;
  color:#facc15;
}
html[data-theme-resolved="dark"] .chip-more{
  background:rgba(148,163,184,.2);
  color:#cbd5e1;
}

/* Compactar aÃºn mÃ¡s la tabla */
table.compact thead th{ padding:6px 8px !important; font-size:12px !important; }
table.compact tbody td{ padding:6px 8px !important; font-size:12px !important; }
table.compact tbody tr{ height:42px; }

/* BotÃ³n eliminar solo con â€œXâ€ */
/* Ajuste fino de tabla */
table.compact thead th{ padding:11px 10px !important; font-size:12px !important; }
table.compact tbody td{ padding:11px 10px !important; font-size:13px !important; }
table.compact tbody tr{ height:auto; }

.btn-x{
  width:34px; height:34px;
  display:inline-flex; align-items:center; justify-content:center;
  font-weight:800;
  padding:0 !important;
  font-size:16px;
}

/* Chip Oficios */
.btn-oficios{
  background:#eef6ff; border:1px solid #cfe3ff;
}
html[data-theme-resolved="dark"]{
  .btn-oficios{ background:#172036; border-color:#263a66; }
}

/* ===== Estado editable ===== */
.estado-badge{
  cursor:pointer; user-select:none; font-weight:700;
  padding:6px 10px; border-radius:999px; border:1px solid var(--badge-bd);
  display:inline-block; font-size:12px;
}
.estado-pendiente{ background:#f8d7da; color:#842029; }   /* rojo claro */
.estado-resuelto{  background:#d1e7dd; color:#0f5132; }   /* verde */
.estado-dilig{     background:#fff3cd; color:#664d03; }   /* naranja/Ã¡mbar */

html[data-theme-resolved="dark"]{
  div.estado-popup{ background:#1e293b; border-color:#334155; color:#f8fafc; }
}

/* MenÃº flotante para cambiar estado (look igual a los badges) */
.estado-menu{
  position: fixed;
  z-index: 99999;
  display: flex; gap: 8px; align-items: center;
  padding: 8px;
  border-radius: 12px;
  background: var(--panel-bg);
  border: 1px solid var(--panel-bd);
  backdrop-filter: blur(8px);
  box-shadow: 0 8px 20px rgba(0,0,0,.15);
}
.estado-menu .opt{
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 12px; font-weight: 700;
  border: 1px solid var(--badge-bd);
  cursor: pointer; user-select: none;
  transition: transform .08s ease, box-shadow .08s ease, filter .08s ease;
}
.estado-menu .opt:hover{ transform: translateY(-1px); filter: brightness(1.05); }
.opt-pendiente{ background:#f8d7da; color:#842029; }
.opt-resuelto { background:#d1e7dd; color:#0f5132; }
.opt-dilig    { background:#fff3cd; color:#664d03; }

/* ===== Columnas Folder + Star ===== */
.col-folder { display:flex; align-items:center; gap:12px; min-width:120px; }
.col-folder .prio-btn { background:transparent; border:1px solid rgba(255,255,255,0.04); padding:6px 8px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; }
.col-folder .prio-btn[aria-pressed="true"] { box-shadow: 0 6px 18px rgba(0,0,0,.18); }

.star { font-size:16px; line-height:1; display:inline-block; }
.star-on { color:#ffd54f; text-shadow:0 1px 0 rgba(0,0,0,.25); }
.star-off { color:rgba(15,23,42,0.35); }

.select-folder{
  padding:6px 10px;
  border-radius:12px;
  font-weight:800;
  font-size:13px;
  background:linear-gradient(180deg, rgba(255,255,255,.92), rgba(255,255,255,.75));
  border:1.5px solid #d4af37;
  color:var(--fg);
  min-width:68px;
  text-align:center;
}
html[data-theme-resolved="dark"]{
  .select-folder {
    background: rgba(212,175,55,0.12);
    color: #f6f6f6;
    border-color: #e2c96c;
    box-shadow: 0 0 6px rgba(212,175,55,0.12) inset;
  }
  .star-off { color: rgba(255,255,255,0.35); }
}

/* ===== Cards de accidentes ===== */
.acc-card-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;align-items:stretch}
.table-wrap{display:none}
.acc-card{
  border:1px solid rgba(148,163,184,.38);
  border-radius:14px;
  background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(248,250,252,.96));
  box-shadow:0 10px 24px rgba(15,23,42,.07), inset 4px 0 0 rgba(37,99,235,.22);
  overflow:visible;min-width:0;height:100%;
  transition:border-color .16s ease,box-shadow .16s ease,transform .16s ease;
}
.acc-card .acc-card-main{cursor:pointer}
.acc-card:focus-visible{
  outline:3px solid rgba(37,99,235,.38);
  outline-offset:3px;
}
.acc-card:hover{
  border-color:rgba(37,99,235,.38);
  box-shadow:0 14px 30px rgba(15,23,42,.10), inset 4px 0 0 rgba(37,99,235,.42);
}
.acc-card[data-priority="1"]{
  border-color:rgba(212,175,55,.62);
  box-shadow:0 12px 28px rgba(212,175,55,.14), inset 4px 0 0 #d4af37;
}
.acc-card.last-opened{
  border-color:rgba(212,175,55,.70);
  box-shadow:0 14px 32px rgba(212,175,55,.18), inset 4px 0 0 #d4af37;
}
.acc-card .acc-card-main:hover{background:rgba(37,99,235,.035)}
.acc-card-list.has-district-color .acc-card{
  border-color:hsl(var(--district-hue) 72% 58% / .52);
  box-shadow:0 10px 25px hsl(var(--district-hue) 48% 28% / .12),inset 4px 0 0 hsl(var(--district-hue) 88% 54% / .72);
}
.acc-card-list.has-district-color .acc-card:hover{
  border-color:hsl(var(--district-hue) 88% 52% / .78);
  box-shadow:0 15px 34px hsl(var(--district-hue) 58% 28% / .20),0 0 20px hsl(var(--district-hue) 88% 54% / .12),inset 4px 0 0 hsl(var(--district-hue) 92% 55%);
}
.acc-card-list.has-district-color .acc-card[data-priority="1"]{
  border-color:hsl(var(--district-hue) 88% 52% / .78);
  box-shadow:0 13px 30px hsl(var(--district-hue) 58% 28% / .18),0 0 18px hsl(var(--district-hue) 88% 54% / .12),inset 4px 0 0 hsl(var(--district-hue) 92% 55%);
}
.acc-card-list.has-district-color .acc-card .acc-card-main:hover{background:hsl(var(--district-hue) 90% 55% / .035)}
.acc-card button,
.acc-card select,
.acc-card a,
.acc-card .estado-badge{cursor:pointer}
.acc-card-main{
  position:relative;display:grid;
  grid-template-columns:minmax(0,1fr);
  grid-template-areas:"left" "center";
  gap:16px;padding:18px;align-items:start;height:100%;
}
.acc-card-left{grid-area:left;display:flex;flex-direction:column;gap:12px;min-width:0}
.acc-head{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding-right:40px}
.acc-head-priority{display:inline-flex;align-items:center;min-width:auto}
.acc-report{
  display:inline-flex;
  align-items:center;
  padding:4px 10px;
  border-radius:999px;
  font-size:12px;
  font-weight:700;
  color:#475569;
  background:#eef2f7;
}
.acc-folder-select{
  width:auto;min-width:58px;height:26px;padding:2px 22px 2px 8px;
  border-radius:999px;border:1px solid #f2c94c;background:#fff8dc;
  color:#7c5a00;font-size:12px;font-weight:800;cursor:pointer;
}
.tipo-reg-chip{
  display:inline-flex;
  align-items:center;
  padding:4px 10px;
  border-radius:999px;
  font-size:12px;
  font-weight:800;
  color:#0f766e;
  background:#ccfbf1;
  border:1px solid #99f6e4;
}
.tipo-reg-carpeta{color:#92400e;background:#fef3c7;border-color:#fde68a}
.tipo-reg-intervencion{color:#155e75;background:#cffafe;border-color:#a5f3fc}
.acc-place{display:flex;align-items:flex-start;gap:6px;font-size:15px;font-weight:700;line-height:1.35;color:#14213d}
.acc-place-icon,.acc-meta-icon{flex:0 0 auto;line-height:1.2}
.acc-place-icon{font-size:15px;margin-top:1px}
.acc-place-district{color:#64748b;font-weight:750}
html[data-theme-resolved="dark"] .acc-place-district{color:#a8b6c9}
.acc-modality{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.acc-modality-label{font-size:10px;font-weight:850;color:#7b8794;text-transform:uppercase;letter-spacing:.04em}
.acc-modality-chip{display:inline-flex;align-items:center;min-height:23px;padding:3px 9px;border:1px solid rgba(99,102,241,.2);border-radius:999px;background:rgba(99,102,241,.08);color:#4338ca;font-size:10px;font-weight:800;line-height:1.15}
html[data-theme-resolved="dark"] .acc-modality-chip{border-color:rgba(129,140,248,.3);background:rgba(99,102,241,.16);color:#c7d2fe}
.acc-meta{display:flex;flex-wrap:wrap;gap:10px 14px}
.acc-meta-item{display:flex;flex-direction:column;gap:2px;min-width:110px}
.acc-meta-label{display:flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:#7b8794;text-transform:uppercase}
.acc-meta-icon{font-size:12px}
.acc-meta-value{font-size:13px;font-weight:600;color:#334155}
.acc-card-center{grid-area:center;display:flex;flex-direction:column;gap:8px;padding-top:14px;border-top:1px solid rgba(148,163,184,.25)}
.acc-involved{display:flex;flex-direction:column;gap:7px;min-width:0}
.acc-involved-title{font-size:11px;font-weight:800;color:#7b8794;text-transform:uppercase;letter-spacing:.04em}
.acc-involved-row{display:grid;grid-template-columns:22px minmax(0,1fr);gap:6px;align-items:start;min-width:0}
.acc-involved-icon{font-size:16px;line-height:1.15;text-align:center}
.acc-involved-body{min-width:0}
.acc-involved-name{font-size:12px;font-weight:800;line-height:1.25;color:#24324a;overflow-wrap:anywhere}
.acc-involved-name.is-deceased{color:#b42318}
.acc-involved-name.is-injured{color:#a16207}
.acc-involved-meta{font-size:10px;font-weight:650;line-height:1.35;color:#64748b;overflow-wrap:anywhere}
.acc-involved-meta .vehicle-kind{font-weight:850;color:#334155}
.acc-involved-meta .vehicle-plate-summary{font-weight:850;color:#475569}
.acc-involved-more{align-self:flex-start;color:#64748b;font-size:11px;font-weight:800}
.acc-involved-empty{font-size:12px;font-weight:650;color:#64748b}
.acc-prosecution{display:grid;gap:7px;margin-top:5px;padding-top:12px;border-top:1px dashed rgba(148,163,184,.3)}
.acc-prosecution-row{display:grid;grid-template-columns:18px minmax(0,1fr);gap:7px;align-items:start}
.acc-prosecution-icon{font-size:13px;line-height:1.3;text-align:center}
.acc-prosecution-label{display:block;margin-bottom:1px;color:#7b8794;font-size:9px;font-weight:850;letter-spacing:.06em;text-transform:uppercase}
.acc-prosecution-value{display:block;color:#334155;font-size:11px;font-weight:750;line-height:1.3;overflow-wrap:anywhere}
html[data-theme-resolved="dark"] .acc-prosecution-value{color:#dbe5f2}
.acc-summary-block{display:flex;flex-direction:column;gap:6px}
.acc-summary-title{font-size:11px;font-weight:700;color:#7b8794;text-transform:uppercase}
.acc-summary-line{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.acc-hint{font-size:12px;font-weight:600;color:#64748b}
.acc-card-right{position:absolute;z-index:5;top:14px;right:14px;display:flex;align-items:flex-end}
.acc-top-actions{position:relative;display:flex;align-items:center;justify-content:flex-end}
.acc-actions-trigger{
  width:36px;height:34px;padding:0;border:1px solid var(--field-bd);border-radius:10px;
  background:var(--pill-bg);color:var(--fg);font-size:20px;font-weight:900;line-height:1;
  display:inline-flex;align-items:center;justify-content:center;cursor:pointer;
  box-shadow:0 7px 16px rgba(15,23,42,.09);transition:transform .14s ease,border-color .14s ease,box-shadow .14s ease;
}
.acc-actions-trigger:hover,.acc-actions-trigger[aria-expanded="true"]{transform:translateY(-1px);border-color:#93c5fd;color:#1d4ed8;box-shadow:0 9px 20px rgba(37,99,235,.16)}
.acc-actions-menu{
  position:absolute;z-index:30;top:calc(100% + 7px);right:0;width:190px;padding:6px;
  border:1px solid rgba(148,163,184,.34);border-radius:12px;background:var(--panel-bg);
  box-shadow:0 18px 40px rgba(15,23,42,.20);animation:accActionsIn .14s ease-out both;
}
.acc-actions-menu[hidden]{display:none}
@keyframes accActionsIn{from{opacity:0;transform:translateY(-4px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
.acc-actions-item{
  width:100%;min-height:34px;padding:7px 9px;border:0;border-radius:8px;background:transparent;color:var(--fg);
  display:flex;align-items:center;gap:8px;text-align:left;text-decoration:none;font-size:11px;font-weight:800;cursor:pointer;
}
.acc-actions-item:hover{background:#eff6ff;color:#1d4ed8}
.acc-actions-item.is-gps:hover{background:#ecfdf3;color:#166534}
.acc-actions-item.is-danger{color:#b42318}
.acc-actions-item.is-danger:hover{background:#fef2f2;color:#991b1b}
.acc-actions-divider{height:1px;margin:5px 4px;background:rgba(148,163,184,.28)}
.acc-actions-form{display:block;margin:0}
.acc-card .col-folder{min-width:auto}

html[data-theme-resolved="dark"] .acc-card{
  background:linear-gradient(180deg,rgba(15,20,34,.96),rgba(17,25,43,.96));
  border-color:rgba(148,163,184,.24);
  box-shadow:0 10px 24px rgba(0,0,0,.24), inset 4px 0 0 rgba(96,165,250,.34);
}
html[data-theme-resolved="dark"] .acc-card:hover{
  border-color:rgba(96,165,250,.5);
  box-shadow:0 14px 30px rgba(0,0,0,.32), inset 4px 0 0 rgba(96,165,250,.58);
}
html[data-theme-resolved="dark"] .acc-card[data-priority="1"]{
  border-color:rgba(226,201,108,.68);
  box-shadow:0 12px 28px rgba(212,175,55,.16), inset 4px 0 0 #e2c96c;
}
html[data-theme-resolved="dark"] .acc-card-list.has-district-color .acc-card,
html[data-theme-resolved="dark"] .acc-card-list.has-district-color .acc-card[data-priority="1"]{
  border-color:hsl(var(--district-hue) 72% 58% / .52);
  box-shadow:0 11px 28px rgba(0,0,0,.30),0 0 18px hsl(var(--district-hue) 88% 54% / .10),inset 4px 0 0 hsl(var(--district-hue) 88% 58% / .82);
}
html[data-theme-resolved="dark"] .acc-card-list.has-district-color .acc-card:hover{
  border-color:hsl(var(--district-hue) 88% 64% / .76);
  box-shadow:0 15px 34px rgba(0,0,0,.36),0 0 24px hsl(var(--district-hue) 88% 54% / .18),inset 4px 0 0 hsl(var(--district-hue) 92% 65%);
}
html[data-theme-resolved="dark"] .acc-place,
html[data-theme-resolved="dark"] .vehicle-plate{color:#e5edf8}
html[data-theme-resolved="dark"] .acc-report{background:#1e293b;color:#cbd5e1}
html[data-theme-resolved="dark"] .acc-folder-select{background:#3b2f0b;color:#fde68a;border-color:#a16207}
html[data-theme-resolved="dark"] .tipo-reg-carpeta{background:rgba(245,158,11,.18);border-color:rgba(245,158,11,.34);color:#fcd34d}
html[data-theme-resolved="dark"] .tipo-reg-intervencion{background:rgba(6,182,212,.16);border-color:rgba(6,182,212,.34);color:#67e8f9}
html[data-theme-resolved="dark"] .acc-meta-value,
html[data-theme-resolved="dark"] .vehicle-extra{color:#9fb0c6}
html[data-theme-resolved="dark"] .acc-hint{color:#9fb0c6}
html[data-theme-resolved="dark"] .acc-involved-name{color:#e5edf8}
html[data-theme-resolved="dark"] .acc-involved-name.is-deceased{color:#f87171}
html[data-theme-resolved="dark"] .acc-involved-name.is-injured{color:#facc15}
html[data-theme-resolved="dark"] .acc-involved-meta,
html[data-theme-resolved="dark"] .acc-involved-empty{color:#9fb0c6}
html[data-theme-resolved="dark"] .acc-involved-meta .vehicle-kind,
html[data-theme-resolved="dark"] .acc-involved-meta .vehicle-plate-summary{color:#cbd5e1}
html[data-theme-resolved="dark"] .acc-involved-more{color:#9fb0c6}
html[data-theme-resolved="dark"] .acc-actions-menu{background:#111827;border-color:#334155;box-shadow:0 18px 42px rgba(0,0,0,.42)}
html[data-theme-resolved="dark"] .acc-actions-item:hover{background:#1e3a8a;color:#dbeafe}
html[data-theme-resolved="dark"] .acc-actions-item.is-gps:hover{background:#14532d;color:#dcfce7}
html[data-theme-resolved="dark"] .acc-actions-item.is-danger{color:#fca5a5}
html[data-theme-resolved="dark"] .acc-actions-item.is-danger:hover{background:#450a0a;color:#fecaca}

@media(max-width:1050px){
  .acc-card-list{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:620px){
  .title{align-items:flex-start;flex-direction:column}
  .acc-card-list{grid-template-columns:minmax(0,1fr);gap:14px}
  .acc-card-main{gap:12px;padding:14px}
  .acc-card-right{top:10px;right:10px}
  .acc-head{gap:6px}
  .col-folder{min-width:auto;gap:6px}
  .acc-meta{display:grid;grid-template-columns:1fr}
  .acc-actions-menu{position:fixed;top:auto;right:14px;bottom:14px;left:14px;width:auto}
}
</style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="wrap">
  <div class="title">
    <h1 style="margin:0">Accidentes <span class="badge">Listado</span></h1>
    <nav class="toolbar" aria-label="Acciones">
      <a class="btn" href="#" onclick="history.back();return false;">Atras</a>
      <a class="btn" href="index.php">Inicio</a>
      <a class="btn" href="accidente_mapa.php">Mapa</a>
      <a class="btn primary" href="accidente_nuevo.php">Nuevo</a>
    </nav>
  </div>

  <?php if ($stationSelected || $favoritos === '1' || $verTodos === '1'): ?>
  <div class="card filter-card filter-glass">
    <div class="filter-glass-head">
      <h2 class="filter-glass-title"><span class="filter-glass-icon" aria-hidden="true">⌕</span><span>Buscar accidentes</span></h2>
      <span class="filter-mode-chip"><?php if ($favoritos === '1'): ?>Favoritos<?php elseif ($verTodos === '1'): ?>Todos los accidentes<?php else: ?>Comisaría seleccionada<?php endif; ?></span>
    </div>
    <form method="get" class="filters" id="filterForm">
      <div class="filter-primary">
        <div class="col-6">
          <label>Persona</label>
          <input type="text" name="persona" placeholder="Nombres o apellidos" value="<?=h($_GET['persona']??'')?>">
        </div>
        <div class="col-6">
          <label>Vehículo (placa)</label>
          <input type="text" name="vehiculo" placeholder="Placa" value="<?=h($_GET['vehiculo']??'')?>">
        </div>
      </div>

      <div class="filter-actions">
        <button type="button" class="filter-toggle" id="filterToggle" aria-expanded="false" aria-controls="advancedFilters">
          <span>Más filtros</span><span class="filter-toggle-icon">⌄</span>
        </button>
        <div class="filter-action-buttons">
          <button class="btn small filter-submit" type="submit">Aplicar filtros</button>
        </div>
      </div>

      <div class="filter-advanced" id="advancedFilters">
        <div class="col-3">
          <label>Registro SIDPOL</label>
          <input type="text" name="registro_sidpol" placeholder="Ej: 2025-ABC-123" value="<?=h($_GET['registro_sidpol']??'')?>">
        </div>
        <div class="col-3">
          <label>N&deg; informe policial</label>
          <input type="text" name="nro_informe_policial" placeholder="Ej: 105-2025" value="<?=h($_GET['nro_informe_policial']??'')?>">
        </div>
        <div class="col-3">
          <label>Distrito</label>
          <input type="text" name="distrito" placeholder="Distrito" value="<?=h($_GET['distrito']??'')?>">
        </div>
        <div class="col-3">
          <label>Comisaria</label>
          <select name="comisaria_id">
            <option value="">-- Todas --</option>
            <?php foreach($comisarias as $c): ?>
              <option value="<?=$c['id']?>" <?=($comisaria_id==$c['id']?'selected':'')?>><?=h($c['nombre'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-4">
          <label>Estado</label>
          <select name="estado">
            <?php foreach($estadoOpciones as $estadoValue => $estadoLabel): ?>
              <option value="<?=h($estadoValue)?>" <?=($estadoFiltro===$estadoValue?'selected':'')?>><?=h($estadoLabel)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-4">
          <label>Tipo de registro</label>
          <select name="tipo_registro">
            <?php foreach($tipoRegistroOpciones as $tipoValue => $tipoLabel): ?>
              <option value="<?=h($tipoValue)?>" <?=($tipo_registro===$tipoValue?'selected':'')?>><?=h($tipoLabel)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-4">
          <label>Ordenar por</label>
          <select name="orden">
            <?php foreach($ordenOpciones as $ordenValue => $ordenLabel): ?>
              <option value="<?=h($ordenValue)?>" <?=($orden===$ordenValue?'selected':'')?>><?=h($ordenLabel)?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php if ($favoritos === '1'): ?>
        <input type="hidden" name="favoritos" value="1">
      <?php endif; ?>
      <?php if ($verTodos === '1'): ?>
        <input type="hidden" name="ver_todos" value="1">
      <?php endif; ?>
    </form>

    <?php if ($restoredLastFilters): ?>
      <div class="memory-note">Mostrando la última búsqueda realizada.</div>
    <?php elseif (!$hasIncomingFilters && $ultimoAccidenteAbiertoId > 0): ?>
      <div class="memory-note">Último accidente abierto resaltado arriba.</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!$districtSelected && $favoritos !== '1' && $verTodos !== '1'): ?>
  <section class="card browse-panel district-browser-home" aria-label="Seleccionar distrito">
    <div class="district-sidebar" aria-label="Distritos">
      <div class="district-buttons">
        <div class="district-group district-special" style="--district-hue:42">
          <a class="district-btn" href="accidente_listar.php?favoritos=1&amp;estado=todos">
            <span class="district-name">★ Favoritos</span>
          </a>
        </div>
        <?php $districtPosition = 0; ?>
        <?php foreach ($comisariasPorDistrito as $districtName => $districtComisarias): ?>
          <?php
            $districtName = (string)$districtName;
            $districtHue = $districtHues[$districtPosition % count($districtHues)];
            $districtPosition++;
            $districtActive = $distrito !== '' && lower_u($distrito) === lower_u($districtName);
          ?>
          <div class="district-group" style="--district-hue:<?= (int)$districtHue ?>">
            <a
              class="district-btn"
              href="<?=h(url_filtro_accidente([
                'distrito' => $districtName !== 'Sin distrito asignado' ? $districtName : null,
                'comisaria_id' => null,
                'estado' => 'todos',
                'favoritos' => null,
                'q' => null,
                'desde' => null,
                'hasta' => null,
                'persona' => null,
                'vehiculo' => null,
                'registro_sidpol' => null,
                'nro_informe_policial' => null,
                'tipo_registro' => null,
              ]))?>"
            ><span class="district-name"><?=h($districtName)?></span></a>
          </div>
        <?php endforeach; ?>
        <div class="district-group district-special" style="--district-hue:205">
          <a class="district-btn" href="accidente_listar.php?ver_todos=1&amp;estado=todos">
            <span class="district-name">Ver todos</span>
          </a>
        </div>
      </div>
    </div>
  </section>

  <?php elseif ($districtSelected && !$stationSelected): ?>
  <section class="card browse-panel station-browser" aria-label="Seleccionar distrito y comisaría" style="--district-hue:<?= (int)($selectedDistrictHue ?? 220) ?>" data-close-url="accidente_listar.php">
    <a class="btn browse-back" href="accidente_listar.php">← Cerrar distrito</a>
    <div class="district-browser-stage">
      <div class="district-wheel-wrap">
        <div class="district-wheel" id="districtWheel" role="listbox" aria-label="Seleccionar distrito">
          <a class="district-wheel-item district-wheel-shortcut" role="option" aria-selected="false" data-mode="favoritos" href="accidente_listar.php?favoritos=1&amp;estado=todos" style="--wheel-hue:42">★ Favoritos</a>
          <?php foreach ($comisariasPorDistrito as $wheelIndex => $wheelStations):
            $wheelDistrict = (string)$wheelIndex;
            $wheelActive = lower_u($wheelDistrict) === lower_u($distrito);
            $wheelHueIndex = array_search($wheelDistrict, array_keys($comisariasPorDistrito), true);
            $wheelHue = $districtHues[((int)$wheelHueIndex) % count($districtHues)];
          ?>
            <button type="button" class="district-wheel-item <?=$wheelActive ? 'is-active' : ''?>" role="option" aria-selected="<?=$wheelActive ? 'true' : 'false'?>" data-district="<?=h($wheelDistrict)?>" style="--wheel-hue:<?= (int)$wheelHue ?>">
              <?=h($wheelDistrict)?>
            </button>
          <?php endforeach; ?>
          <a class="district-wheel-item district-wheel-shortcut" role="option" aria-selected="false" data-mode="todos" href="accidente_listar.php?ver_todos=1&amp;estado=todos" style="--wheel-hue:205">Ver todos</a>
        </div>
      </div>
      <div class="district-detail">
        <?php foreach ($comisariasPorDistrito as $panelDistrict => $panelStations):
          $panelDistrict = (string)$panelDistrict;
          $panelActive = lower_u($panelDistrict) === lower_u($distrito);
          $panelHueIndex = array_search($panelDistrict, array_keys($comisariasPorDistrito), true);
          $panelHue = $districtHues[((int)$panelHueIndex) % count($districtHues)];
        ?>
          <div class="district-station-panel <?=$panelActive ? 'is-active' : ''?>" data-station-panel="<?=h($panelDistrict)?>" style="--district-hue:<?= (int)$panelHue ?>">
            <?php if ($panelStations === []): ?>
              <div class="district-station-empty">No hay comisarías registradas.</div>
            <?php else: foreach ($panelStations as $station): $stationId = (string)$station['id']; ?>
              <a class="station-btn" href="<?=h(url_filtro_accidente([
                'comisaria_id' => $stationId, 'distrito' => $panelDistrict, 'estado' => 'todos',
                'favoritos' => null, 'q' => null, 'desde' => null, 'hasta' => null,
                'persona' => null, 'vehiculo' => null, 'registro_sidpol' => null,
                'nro_informe_policial' => null, 'tipo_registro' => null,
              ]))?>">
                <span class="station-symbol" aria-hidden="true">🏛️</span>
                <span class="station-label"><?=h((string)$station['comisaria'])?><small>Comisaría</small></span>
                <span class="station-count" title="Accidentes registrados"><?= (int)$station['accidentes_total'] ?></span>
              </a>
            <?php endforeach; endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php else: ?>
  <div class="district-accident-layout">
    <main class="district-main">
      <div class="station-stage-layout no-orbit" style="--district-hue:<?= (int)($selectedDistrictHue ?? 220) ?>">
        <div class="station-stage-content">
  <div class="card">
    <div class="acc-card-list <?= $selectedDistrictHue !== null ? 'has-district-color' : '' ?>" id="cards-list" role="list" aria-label="Lista de accidentes"<?= $selectedDistrictHue !== null ? ' style="--district-hue:' . (int)$selectedDistrictHue . '"' : '' ?>>
      <?php if (!$rows): ?>
        <div class="empty">Sin resultados</div>
      <?php else: foreach($rows as $i=>$r):
          $estado = $r['estado'] ?: 'Pendiente';
          $cls = ($estado==='Resuelto') ? 'estado-resuelto'
               : (($estado==='Con diligencias') ? 'estado-dilig' : 'estado-pendiente');
          $personasDetalle = $personasDetallePorAccidente[(int)$r['id']] ?? [];
          $personasResumen = $personasResumenPorAccidente[(int)$r['id']] ?? [];
          $vehiculosResumen = $vehiculosResumenPorAccidente[(int)$r['id']] ?? [];
          $modalidadesAccidente = $modalidadesPorAccidente[(int)$r['id']] ?? [];
          $vehiculosPreview = array_slice($vehiculosResumen, 0, 2);
          $personasVisuales = $personasDetalle;
          usort($personasVisuales, static function(array $a, array $b): int {
            return involucrado_prioridad_resumen($a['rol'] ?? '') <=> involucrado_prioridad_resumen($b['rol'] ?? '');
          });
          $personasPreview = array_slice($personasVisuales, 0, 3);
          $personasRestantes = max(0, count($personasVisuales) - count($personasPreview));
          $isPrior = !empty($r['priority']) && (int)$r['priority']===1;
          $folderVal = ($estado === 'Resuelto' || $r['folder'] === null ? '' : (string)$r['folder']);
          $tipoRegistro = tipo_registro_label($r['tipo_registro'] ?? '');
          $tipoRegistroClass = ($r['tipo_registro'] ?? '') === 'Intervencion' ? 'tipo-reg-intervencion' : 'tipo-reg-carpeta';
          $lat = trim((string)($r['latitud'] ?? ''));
          $lng = trim((string)($r['longitud'] ?? ''));
          $hasGps = is_numeric(str_replace(',', '.', $lat)) && is_numeric(str_replace(',', '.', $lng));
          $gpsUrl = $hasGps ? 'https://www.google.com/maps?q=' . rawurlencode(str_replace(',', '.', $lat) . ',' . str_replace(',', '.', $lng)) : '';
      ?>
        <article class="acc-card <?= (int)$r['id'] === $ultimoAccidenteAbiertoId ? 'last-opened' : '' ?>" role="listitem" tabindex="0" aria-label="Abrir accidente SIDPOL <?=h($r['registro_sidpol'])?>" data-url="accidente_vista_tabs.php?accidente_id=<?= (int)$r['id'] ?>" data-id="<?= (int)$r['id'] ?>" data-priority="<?= $isPrior ? '1' : '0' ?>" data-date="<?= h($r['fecha_accidente'] ?? '') ?>">
          <div class="acc-card-main">
            <div class="acc-card-left">
              <div class="acc-head">
                <div class="col-folder folder-cell acc-head-priority">
                  <button class="prio-btn" title="<?= $isPrior ? 'Quitar prioridad' : 'Marcar prioridad' ?>"
                          data-id="<?= $r['id'] ?>" data-priority="<?= $isPrior ? '1' : '0' ?>"
                          aria-pressed="<?= $isPrior ? 'true' : 'false' ?>">
                    <span class="star <?= $isPrior ? 'star-on' : 'star-off' ?>"><?= $isPrior ? '&#9733;' : '&#9734;' ?></span>
                  </button>
                </div>
                <a class="sidpol-link" href="accidente_vista_tabs.php?accidente_id=<?= $r['id'] ?>" title="Ver detalles">
                  <span class="badge sidpol-reg"><?=h($r['registro_sidpol'])?></span>
                </a>
                <span class="acc-report"><?=h($r['nro_informe_policial'] ?? '-')?></span>
                <select class="select-folder acc-folder-select" data-id="<?=$r['id']?>" aria-label="Número de Folder" title="Número de Folder">
                  <?php render_folder_options($folderVal, (int)$r['id'], $occupiedFolders); ?>
                </select>
                <span class="estado-badge <?=$cls?>"
                      data-id="<?=$r['id']?>"
                      data-estado="<?=h($estado)?>">
                  <?=h($estado)?>
                </span>
                <?php if ($tipoRegistro !== ''): ?>
                  <span class="tipo-reg-chip <?=h($tipoRegistroClass)?>"><?=h($tipoRegistro)?></span>
                <?php endif; ?>
              </div>
              <div class="acc-place">
                <span class="acc-place-icon" aria-hidden="true">📍</span>
                <span><?=h($r['lugar'])?><?php if (trim((string)($r['distrito'] ?? '')) !== ''): ?> <span class="acc-place-district">- <?=h($r['distrito'])?></span><?php endif; ?></span>
              </div>
              <?php if ($modalidadesAccidente !== []): ?>
                <div class="acc-modality" aria-label="Tipo de accidente">
                  <span class="acc-modality-label">Modalidad</span>
                  <?php foreach (array_slice($modalidadesAccidente, 0, 2) as $modalidadAccidente): ?>
                    <span class="acc-modality-chip"><?=h($modalidadAccidente)?></span>
                  <?php endforeach; ?>
                  <?php if (count($modalidadesAccidente) > 2): ?>
                    <span class="acc-modality-chip" title="<?=h(implode(', ', $modalidadesAccidente))?>">+<?=count($modalidadesAccidente) - 2?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <div class="acc-meta">
                <div class="acc-meta-item">
                  <span class="acc-meta-label"><span class="acc-meta-icon" aria-hidden="true">📅</span>Fecha</span>
                  <span class="acc-meta-value"><?=h(fecha_lista_corta($r['fecha_accidente'] ?? ''))?></span>
                </div>
                <div class="acc-meta-item">
                  <span class="acc-meta-label"><span class="acc-meta-icon" aria-hidden="true">🏢</span>Comisaria</span>
                  <span class="acc-meta-value"><?=h($r['comisaria'] ?? '-')?></span>
                </div>
              </div>
            </div>

            <div class="acc-card-center">
              <div class="acc-involved">
                <div class="acc-involved-title">Involucrados</div>
                <?php if ($personasPreview === []): ?>
                  <div class="acc-involved-empty">Sin personas registradas</div>
                <?php else: foreach ($personasPreview as $personaPreview):
                  $vehiculoPersona = is_array($personaPreview['vehiculo'] ?? null) ? $personaPreview['vehiculo'] : null;
                  $rolPreview = trim((string)($personaPreview['rol'] ?? ''));
                  $lesionPreview = trim((string)($personaPreview['lesion'] ?? ''));
                  $claseLesionNombre = $lesionPreview === 'Fallecido' ? 'is-deceased' : ($lesionPreview === 'Herido' ? 'is-injured' : '');
                  $tipoPreview = trim((string)($vehiculoPersona['tipo'] ?? ''));
                  $marcaModeloPreview = trim((string)($vehiculoPersona['marca_modelo'] ?? ''));
                  $placaPreview = trim((string)($vehiculoPersona['placa'] ?? ''));
                  if ($placaPreview === 'SIN PLACA') $placaPreview = '';
                  $metaPartes = array_values(array_filter([$rolPreview, $tipoPreview, $marcaModeloPreview, $placaPreview], static fn($v) => $v !== ''));
                ?>
                  <div class="acc-involved-row">
                    <span class="acc-involved-icon" aria-hidden="true"><?=h(involucrado_icono_resumen($rolPreview, $tipoPreview))?></span>
                    <div class="acc-involved-body">
                      <div class="acc-involved-name <?=h($claseLesionNombre)?>"><?=h($personaPreview['nombre'])?></div>
                      <div class="acc-involved-meta">
                        <?php foreach ($metaPartes as $parteIndex => $parte): ?><?= $parteIndex > 0 ? ' · ' : '' ?><span class="<?= $parteIndex === 1 && $tipoPreview !== '' ? 'vehicle-kind' : ($placaPreview !== '' && $parte === $placaPreview ? 'vehicle-plate-summary' : '') ?>"><?=h($parte)?></span><?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; endif; ?>
                <?php if ($personasRestantes > 0): ?>
                  <div class="acc-involved-more">👥 +<?=$personasRestantes?> persona<?=$personasRestantes === 1 ? '' : 's'?> más</div>
                <?php endif; ?>
              </div>
              <?php if (trim((string)($r['fiscalia'] ?? '')) !== '' || trim((string)($r['fiscal'] ?? '')) !== ''): ?>
                <div class="acc-prosecution">
                  <?php if (trim((string)($r['fiscalia'] ?? '')) !== ''): ?>
                    <div class="acc-prosecution-row">
                      <span class="acc-prosecution-icon" aria-hidden="true">⚖️</span>
                      <span><span class="acc-prosecution-label">Fiscalía</span><span class="acc-prosecution-value"><?=h($r['fiscalia'])?></span></span>
                    </div>
                  <?php endif; ?>
                  <?php if (trim((string)($r['fiscal'] ?? '')) !== ''): ?>
                    <div class="acc-prosecution-row">
                      <span class="acc-prosecution-icon" aria-hidden="true">👤</span>
                      <span><span class="acc-prosecution-label">Fiscal a cargo</span><span class="acc-prosecution-value"><?=h($r['fiscal'])?></span></span>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="acc-card-right">
              <div class="acc-top-actions">
                <button class="acc-actions-trigger js-acc-actions-trigger" type="button" aria-expanded="false" aria-controls="acc-actions-<?= (int)$r['id'] ?>" title="Más acciones" aria-label="Más acciones">&#8942;</button>
                <div class="acc-actions-menu" id="acc-actions-<?= (int)$r['id'] ?>" hidden>
                  <?php if ($hasGps): ?>
                    <a class="acc-actions-item is-gps" href="<?= h($gpsUrl) ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">📍</span>Ver GPS</a>
                  <?php endif; ?>
                  <a class="acc-actions-item" href="word_caratula_accidente.php?accidente_id=<?= (int)$r['id'] ?>"><span aria-hidden="true">📄</span>Carátula</a>
                  <div class="acc-actions-divider" aria-hidden="true"></div>
                  <a class="acc-actions-item" href="accidente_vista_tabs.php?accidente_id=<?= (int)$r['id'] ?>&tab=documentos&subtab=oficios"><span aria-hidden="true">📨</span>Oficios</a>
                  <a class="acc-actions-item" href="accidente_vista_tabs.php?accidente_id=<?= (int)$r['id'] ?>&tab=documentos&subtab=recibidos"><span aria-hidden="true">📥</span>Documentos recibidos</a>
                  <a class="acc-actions-item" href="accidente_vista_tabs.php?accidente_id=<?= (int)$r['id'] ?>&tab=documentos&subtab=actas"><span aria-hidden="true">📝</span>Actas</a>
                  <div class="acc-actions-divider" aria-hidden="true"></div>
                  <form class="acc-actions-form" action="accidente_eliminar.php" method="post"
                        onsubmit="return confirm('Eliminar este accidente de forma permanente?');">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="acc-actions-item is-danger" type="submit"><span aria-hidden="true">🗑️</span>Eliminar</button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; endif; ?>
    </div>

    <div class="table-wrap" role="region" aria-label="Lista de accidentes">
      <table class="compact" role="table" aria-describedby="tbl-desc">
<thead>
  <tr role="row">
    <th role="columnheader" data-sort="registro_sidpol">Registro SIDPOL <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="nro_informe_policial">NÂ° informe policial <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="lugar">Lugar <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="fecha_accidente">Fecha <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="Comisaria">Comisaria <span class="sort-indicator"></span></th>
    <th role="columnheader" class="th-people">Conductor / peaton</th>
    <th role="columnheader" data-sort="folder">Folder <span class="sort-indicator"></span></th>
    <th role="columnheader" data-sort="estado">Estado <span class="sort-indicator"></span></th>
    <th class="td-actions" role="columnheader">Acciones</th>
  </tr>
</thead>
        <tbody id="tbody-rows" role="rowgroup">
          <?php if (!$rows): ?>
            <tr><td colspan="9" class="empty">Sin resultados</td></tr>
          <?php else: foreach($rows as $i=>$r): 
              $estado = $r['estado'] ?: 'Pendiente';
              $cls = ($estado==='Resuelto') ? 'estado-resuelto'
                   : (($estado==='Con diligencias') ? 'estado-dilig' : 'estado-pendiente');
              $folderVal = ($estado === 'Resuelto' || $r['folder'] === null ? '' : (string)$r['folder']);
              $personasResumen = $personasResumenPorAccidente[(int)$r['id']] ?? [];
              $personasVisibles = array_slice($personasResumen, 0, 2);
              $personasExtra = array_slice($personasResumen, 2);
              $personasExtraTexto = implode(' | ', array_map(
                static fn($item) => trim((string)($item['nombre'] ?? '')).' - '.trim((string)($item['rol'] ?? '')).' - '.trim((string)($item['lesion'] ?? '')),
                $personasExtra
              ));
              $tipoRegistro = tipo_registro_label($r['tipo_registro'] ?? '');
              $tipoRegistroClass = ($r['tipo_registro'] ?? '') === 'Intervencion' ? 'tipo-reg-intervencion' : 'tipo-reg-carpeta';
          ?>
            <tr class="<?= (int)$r['id'] === $ultimoAccidenteAbiertoId ? 'last-opened-row' : '' ?>" data-id="<?= (int)$r['id'] ?>" role="row">
  <td role="cell">
    <a class="sidpol-link" href="accidente_vista_tabs.php?accidente_id=<?= $r['id'] ?>" title="Ver detalles">
      <span class="badge sidpol-reg"><?=h($r['registro_sidpol'])?></span>
    </a>
  </td>
  <td role="cell">
    <div class="cell-stack">
      <span class="cell-primary"><?=h($r['nro_informe_policial'] ?? '-')?></span>
      <?php if ($tipoRegistro !== ''): ?>
        <span class="tipo-reg-chip <?=h($tipoRegistroClass)?>"><?=h($tipoRegistro)?></span>
      <?php endif; ?>
    </div>
  </td>
  <td role="cell">
    <div class="cell-stack cell-place" title="<?=h($r['lugar'])?>">
      <span class="cell-primary"><?=h($r['lugar'])?></span>
    </div>
  </td>
  <td role="cell" data-sort-value="<?=h($r['fecha_accidente'] ?? '')?>">
    <div class="cell-stack cell-date">
      <span class="cell-primary"><?=h(fecha_lista_corta($r['fecha_accidente'] ?? ''))?></span>
    </div>
  </td>
  <td role="cell">
    <div class="cell-stack cell-Comisaria" title="<?=h($r['Comisaria']??'-')?>">
      <span class="cell-secondary"><?=h($r['Comisaria']??'-')?></span>
    </div>
  </td>
  <td role="cell">
    <?php if ($personasResumen === []): ?>
      <span class="cell-secondary">Sin conductor o peaton</span>
    <?php else: ?>
      <div class="inv-people">
        <?php foreach ($personasVisibles as $personaItem): ?>
          <div class="inv-person">
            <span class="inv-name"><?=h($personaItem['nombre'])?></span>
            <div class="inv-chips">
              <span class="chip <?=h(chip_rol_class($personaItem['rol'] ?? ''))?>"><?=h($personaItem['rol'])?></span>
              <span class="chip <?=h(chip_lesion_class($personaItem['lesion'] ?? ''))?>"><?=h($personaItem['lesion'])?></span>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if ($personasExtra !== []): ?>
          <div class="inv-chips">
            <span class="chip chip-more" title="<?=h($personasExtraTexto)?>">+<?=count($personasExtra)?> mÃ¡s</span>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </td>
  <!-- FOLDER + ESTRELLA prioridad -->
  <td class="col-folder folder-cell" role="cell">
    <?php $isPrior = !empty($r['priority']) && (int)$r['priority']===1; ?>
    <button class="prio-btn" title="<?= $isPrior ? 'Quitar prioridad' : 'Marcar prioridad' ?>"
            data-id="<?= $r['id'] ?>" data-priority="<?= $isPrior ? '1' : '0' ?>"
            aria-pressed="<?= $isPrior ? 'true' : 'false' ?>">
      <span class="star <?= $isPrior ? 'star-on' : 'star-off' ?>"><?= $isPrior ? '&#9733;' : '&#9734;' ?></span>
    </button>

    <select class="select-folder" data-id="<?=$r['id']?>" aria-label="Folder">
      <?php render_folder_options($folderVal, (int)$r['id'], $occupiedFolders); ?>
    </select>
  </td>
  <td role="cell">
    <span class="estado-badge <?=$cls?>"
          data-id="<?=$r['id']?>"
          data-estado="<?=h($estado)?>">
      <?=h($estado)?>
    </span>
  </td>
  <td class="td-actions" role="cell">
    <a class="btn small" href="word_caratula_accidente.php?accidente_id=<?= (int)$r['id'] ?>" title="Descargar carátula resumen">Carátula</a>
    <form action="accidente_eliminar.php" method="post" style="display:inline"
          onsubmit="return confirm('Eliminar este accidente de forma permanente?');">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn danger small btn-x" title="Eliminar" aria-label="Eliminar">&times;</button>
    </form>
  </td>
</tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
        </div>
      </div>
    </main>
  </div>
  <?php endif; ?>
</div>

<script>
const stationBrowser = document.querySelector('.station-browser[data-close-url]');
if (stationBrowser) {
  stationBrowser.addEventListener('click', (event) => {
    if (event.target.closest('.station-btn, .district-browser-stage, .browse-back')) return;
    window.location.assign(stationBrowser.dataset.closeUrl);
  });
}

const districtWheel = document.getElementById('districtWheel');
if (districtWheel) {
  const wheelItems = Array.from(districtWheel.querySelectorAll('.district-wheel-item'));
  const detailName = document.getElementById('districtDetailName');
  let wheelFrame = 0;
  let activeDistrict = '';

  function selectWheelDistrict(item) {
    if (!item) return;
    const district = item.dataset.district || '';
    if (!district) return;
    activeDistrict = district;
    wheelItems.forEach((candidate) => {
      const active = candidate === item;
      candidate.classList.toggle('is-active', active);
      candidate.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('.district-station-panel').forEach((panel) => {
      panel.classList.toggle('is-active', panel.dataset.stationPanel === district);
    });
    if (detailName) detailName.textContent = district;
    const url = new URL(window.location.href);
    url.searchParams.set('distrito', district);
    url.searchParams.set('estado', 'todos');
    url.searchParams.delete('comisaria_id');
    history.replaceState(null, '', url);
  }

  function paintDistrictWheel() {
    wheelFrame = 0;
    const wheelBox = districtWheel.getBoundingClientRect();
    const center = wheelBox.top + wheelBox.height / 2;
    let nearest = null;
    let nearestDistance = Infinity;
    wheelItems.forEach((item) => {
      const box = item.getBoundingClientRect();
      const signedDistance = (box.top + box.height / 2) - center;
      const distance = Math.abs(signedDistance);
      if (distance < nearestDistance) {
        nearestDistance = distance;
        nearest = item;
      }
      const ratio = Math.min(1, distance / (wheelBox.height * .46));
      item.style.setProperty('--wheel-x', `${30 - ratio * 56}px`);
      item.style.setProperty('--wheel-scale', String(1 - ratio * .22));
      item.style.setProperty('--wheel-opacity', String(1 - ratio * .68));
      item.style.setProperty('--wheel-saturation', String(1 - ratio * .62));
      item.style.setProperty('--wheel-blur', `${Math.max(0, ratio - .72) * 4}px`);
    });
    if (nearest && (nearest.dataset.district || '') !== activeDistrict) selectWheelDistrict(nearest);
  }

  function requestWheelPaint() {
    if (!wheelFrame) wheelFrame = requestAnimationFrame(paintDistrictWheel);
  }

  districtWheel.addEventListener('scroll', requestWheelPaint, {passive:true});
  districtWheel.addEventListener('keydown', (event) => {
    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
    event.preventDefault();
    const currentIndex = Math.max(0, wheelItems.findIndex((item) => item.classList.contains('is-active')));
    const nextIndex = Math.max(0, Math.min(wheelItems.length - 1, currentIndex + (event.key === 'ArrowDown' ? 1 : -1)));
    const nextItem = wheelItems[nextIndex];
    if (nextItem) districtWheel.scrollTo({top:nextItem.offsetTop - (districtWheel.clientHeight - nextItem.offsetHeight) / 2, behavior:'smooth'});
  });
  wheelItems.forEach((item) => item.addEventListener('click', () => {
    districtWheel.scrollTo({top:item.offsetTop - (districtWheel.clientHeight - item.offsetHeight) / 2, behavior:'smooth'});
  }));
  window.addEventListener('resize', requestWheelPaint);

  const initialWheelItem = wheelItems.find((item) => item.classList.contains('is-active')) || wheelItems[0];
  if (initialWheelItem) {
    activeDistrict = initialWheelItem.dataset.district || '';
    requestAnimationFrame(() => {
      districtWheel.scrollTop = initialWheelItem.offsetTop - (districtWheel.clientHeight - initialWheelItem.offsetHeight) / 2;
      paintDistrictWheel();
    });
  }
}

let occupiedFolders = new Map(Object.entries(<?= json_encode($occupiedFolders, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>).map(([folder, id]) => [String(folder), String(id)]));

function updateOccupiedFolders(map) {
  if (!map || typeof map !== 'object') return;
  occupiedFolders = new Map(Object.entries(map).map(([folder, id]) => [String(folder), String(id)]));
}

function folderSelectsByAccident(id) {
  const needle = String(id || '');
  return Array.from(document.querySelectorAll('.select-folder')).filter((select) => String(select.dataset.id || '') === needle);
}

function renderFolderSelect(select) {
  const id = String(select.dataset.id || '');
  const current = String(select.value || '');
  select.innerHTML = '';

  const blank = document.createElement('option');
  blank.value = '';
  blank.textContent = '—';
  blank.selected = current === '';
  select.appendChild(blank);

  for (let folder = 1; folder <= 20; folder++) {
    const value = String(folder);
    const occupiedBy = String(occupiedFolders.get(value) || '');
    if (occupiedBy && occupiedBy !== id && current !== value) continue;

    const option = document.createElement('option');
    option.value = value;
    option.textContent = value;
    option.selected = current === value;
    select.appendChild(option);
  }

}

function refreshFolderSelects(map) {
  updateOccupiedFolders(map);
  document.querySelectorAll('.select-folder').forEach(renderFolderSelect);
}

function setFolderForAccident(id, value) {
  folderSelectsByAccident(id).forEach((select) => {
    select.value = value === null || value === false || value === undefined ? '' : String(value);
    select.dataset.lastValue = select.value;
  });
}

document.querySelectorAll('.select-folder').forEach((select) => {
  select.dataset.lastValue = select.value || '';
});

// Busqueda progresiva: los textos filtran mientras se escriben; los selects filtran al cambiar.
(function(){
  const form = document.getElementById('filterForm');
  if (!form) return;

  const advancedFilters = document.getElementById('advancedFilters');
  const filterToggle = document.getElementById('filterToggle');

  const setAdvancedOpen = (open) => {
    advancedFilters?.classList.toggle('open', open);
    filterToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
    const label = filterToggle?.querySelector('span');
    if (label) label.textContent = open ? 'Menos filtros' : 'Más filtros';
  };

  filterToggle?.addEventListener('click', () => {
    setAdvancedOpen(filterToggle.getAttribute('aria-expanded') !== 'true');
  });
  setAdvancedOpen(false);

  let submitTimer = null;
  const submitNow = () => form.submit();
  const submitSoon = () => {
    clearTimeout(submitTimer);
    submitTimer = setTimeout(submitNow, 450);
  };

  form.querySelectorAll('input[type="text"]').forEach(input=>{
    input.addEventListener('input', submitSoon);
  });

  form.querySelectorAll('select').forEach(select=>{
    select.addEventListener('change', submitNow);
  });

  const hasActiveFilters = () => {
    const data = new FormData(form);
    for (const [name, value] of data.entries()) {
      const val = String(value).trim();
      if (name === 'estado') {
        if (val !== '' && val !== 'Pendiente') return true;
        continue;
      }
      if (val !== '') return true;
    }
    return false;
  };

  document.addEventListener('keydown', (e)=>{
    if(e.key !== 'Escape') return;
    const menuOpen = document.querySelector('.estado-menu, .acc-actions-menu:not([hidden])');
    if(menuOpen) return;
    if(!hasActiveFilters()) return;
    e.preventDefault();
    clearTimeout(submitTimer);
    window.location.href = 'accidente_listar.php';
  });
})();

// Toda la tarjeta abre el detalle, excepto cuando se usa uno de sus controles.
(function(){
  const interactiveSelector = 'a, button, select, input, form, label, .estado-badge, .acc-actions-menu';

  document.querySelectorAll('.acc-card[data-url]').forEach(card => {
    const openCard = () => {
      window.location.href = card.dataset.url;
    };

    card.addEventListener('click', event => {
      if (event.target.closest(interactiveSelector)) return;
      openCard();
    });

    card.addEventListener('keydown', event => {
      if (event.target !== card || (event.key !== 'Enter' && event.key !== ' ')) return;
      event.preventDefault();
      openCard();
    });
  });
})();

// Menú compacto de acciones de cada tarjeta.
(function(){
  const triggers = [...document.querySelectorAll('.js-acc-actions-trigger')];

  function closeAll(except = null){
    triggers.forEach(trigger=>{
      if (trigger === except) return;
      const menu = document.getElementById(trigger.getAttribute('aria-controls'));
      trigger.setAttribute('aria-expanded', 'false');
      if (menu) menu.hidden = true;
    });
  }

  triggers.forEach(trigger=>{
    trigger.addEventListener('click', (e)=>{
      e.stopPropagation();
      const menu = document.getElementById(trigger.getAttribute('aria-controls'));
      if (!menu) return;
      const open = trigger.getAttribute('aria-expanded') === 'true';
      closeAll(trigger);
      trigger.setAttribute('aria-expanded', open ? 'false' : 'true');
      menu.hidden = open;
    });
  });

  document.addEventListener('click', (e)=>{
    if (e.target.closest('.acc-top-actions')) return;
    closeAll();
  });
  document.addEventListener('keydown', (e)=>{
    if (e.key !== 'Escape') return;
    closeAll();
  });
})();

// MenÃº estilo badge para cambiar estado
(function(){
  let abierto = null;

  function cerrarMenu(){
    if (abierto){ abierto.remove(); abierto = null; document.removeEventListener('click', onClickFuera); }
  }
  function onClickFuera(e){
    if (abierto && !abierto.contains(e.target)) cerrarMenu();
  }

  document.querySelectorAll('.estado-badge').forEach(badge=>{
    badge.addEventListener('click', (e)=>{
      e.stopPropagation();
      cerrarMenu();

      const id = badge.dataset.id;
      const actual = badge.dataset.estado;

      const m = document.createElement('div');
      m.className = 'estado-menu';
      m.innerHTML = `
        <div class="opt opt-pendiente" data-val="Pendiente">Pendiente</div>
        <div class="opt opt-resuelto"  data-val="Resuelto">Resuelto</div>
        <div class="opt opt-dilig"     data-val="Con diligencias">Con diligencias</div>
      `;

      const r = badge.getBoundingClientRect();
      m.style.left = (r.left + window.scrollX) + 'px';
      m.style.top  = (r.bottom + window.scrollY + 6) + 'px';

      document.body.appendChild(m);
      abierto = m;
      setTimeout(()=>document.addEventListener('click', onClickFuera),0);

      [...m.querySelectorAll('.opt')].forEach(opt=>{
        if (opt.dataset.val === actual) opt.style.boxShadow = '0 0 0 2px rgba(99,102,241,.35)';

        opt.addEventListener('click', ()=>{
          const nuevo = opt.dataset.val;

          const fd = new FormData();
          fd.append('ajax','estado');
          fd.append('id', id);
          fd.append('estado', nuevo);

          fetch(location.pathname, { method:'POST', body:fd })
            .then(r=>r.json())
            .then(j=>{
              if(j.ok){
                document.querySelectorAll('.estado-badge').forEach((item)=>{
                  if (String(item.dataset.id || '') !== String(id)) return;
                  item.dataset.estado = nuevo;
                  item.textContent = nuevo;
                  item.classList.remove('estado-pendiente','estado-resuelto','estado-dilig');
                  item.classList.add(
                    nuevo==='Resuelto' ? 'estado-resuelto' :
                    (nuevo==='Con diligencias' ? 'estado-dilig' : 'estado-pendiente')
                  );
                });
                if (j.folder === null) {
                  setFolderForAccident(id, '');
                }
                refreshFolderSelects(j.occupied_folders);
              }else{
                alert(j.msg || 'No se pudo actualizar el estado');
              }
            })
            .finally(cerrarMenu);
        });
      });
    });
  });
})();

// Guardar Folder (1..20) al cambiar el select
document.querySelectorAll('.select-folder').forEach(sel=>{
  sel.addEventListener('change', ()=>{
    const id = sel.dataset.id;
    const val = sel.value;
    const previous = sel.dataset.lastValue || '';

    const fd = new FormData();
    fd.append('ajax','folder');
    fd.append('id', id);
    fd.append('folder', val);

    fetch(location.pathname, { method:'POST', body:fd })
      .then(r=>r.json())
      .then(j=>{
        if(!j.ok){
          alert(j.msg || 'No se pudo actualizar Folder');
          sel.value = previous;
          return;
        }
        refreshFolderSelects(j.occupied_folders);
        setFolderForAccident(id, j.val);
      })
      .catch(()=>{
        sel.value = previous;
        alert('Error de red al guardar Folder');
      });
  });
});

// Toggle prioridad (estrella) - opciÃ³n C: subir si marca, bajar si desmarca
document.querySelectorAll('.col-folder .prio-btn').forEach(btn=>{
  btn.addEventListener('click', function(e){
    e.preventDefault();
    const id = this.dataset.id;
    const card = this.closest('.acc-card');
    const list = document.getElementById('cards-list');
    const tr = this.closest('tr');
    const tbody = document.getElementById('tbody-rows');

    const cur = this.dataset.priority === '1' ? 1 : 0;
    const nuevo = cur ? 0 : 1;

    const fd = new FormData();
    fd.append('ajax','priority');
    fd.append('id', id);
    fd.append('priority', nuevo);

    const star = this.querySelector('.star');

    // --- FEEDBACK INMEDIATO ---
    if (nuevo===1) {
      // Activar
      star.textContent='\u2605';
      star.classList.remove('star-off');
      star.classList.add('star-on');
      this.setAttribute('aria-pressed','true');
      this.title='Quitar prioridad';
      this.dataset.priority='1';
      if (card) card.dataset.priority='1';

      // Mover a la parte superior de la lista visible
      if (card && list) list.prepend(card);
      if (tr && tbody) tbody.prepend(tr);

    } else {
      // Desactivar
      star.textContent='\u2606';
      star.classList.remove('star-on');
      star.classList.add('star-off');
      this.setAttribute('aria-pressed','false');
      this.title='Marcar prioridad';
      this.dataset.priority='0';
      if (card) card.dataset.priority='0';

      // --- REUBICAR FILA SEGÃšN ORDEN (folder â†’ fecha) ---
      const folder = (card || tr)?.querySelector('.select-folder')?.value || '';
      const fecha = card?.dataset.date || tr?.children[3]?.dataset.sortValue || tr?.children[3]?.innerText.trim() || '';

      // Insertar segÃºn orden SQL: prioridad DESC, folder ASC, fecha DESC
      let insertado = false;

      // Recorremos las filas y buscamos el primer 'other' donde insertar antes
      const rows = list ? [...list.querySelectorAll('.acc-card')] : [...tbody.querySelectorAll('tr')];
      for (let other of rows) {
        if (insertado) break;
        if (other === card || other === tr) continue;

        const otherPrior = other.querySelector('.prio-btn')?.dataset.priority === '1';
        // Saltar filas prioritarias: siempre van arriba
        if (otherPrior) continue;

        const otherFolder = other.querySelector('.select-folder')?.value || '';
        const otherFecha = other.dataset?.date || other.children?.[3]?.dataset?.sortValue || other.children?.[3]?.innerText.trim() || '';

        // ComparaciÃ³n por folder (vacÃ­o = NULL â†’ va al final)
        const f1 = folder==='' ? 999 : parseInt(folder);
        const f2 = otherFolder==='' ? 999 : parseInt(otherFolder);

        if (f1 < f2) {
          if (card && list && other.classList?.contains('acc-card')) list.insertBefore(card, other);
          else if (tr && tbody) tbody.insertBefore(tr, other);
          insertado = true;
          break;
        }

        if (f1 === f2) {
          // Comparar fecha (mayor primero: fecha mÃ¡s reciente debe quedar antes)
          // Convertimos a string porque el formato en DB es YYYY-MM-DD hh:mm:ss y la comparaciÃ³n lexicogrÃ¡fica funciona
          if (fecha > otherFecha) {
            if (card && list && other.classList?.contains('acc-card')) list.insertBefore(card, other);
            else if (tr && tbody) tbody.insertBefore(tr, other);
            insertado = true;
            break;
          }
        }
      }

      // Si no se insertÃ³ en ninguna posiciÃ³n â†’ va al final
      if (!insertado && card && list) list.appendChild(card);
      if (!insertado && tr && tbody) tbody.appendChild(tr);
    }

    // --- GUARDAR EN BD ---
    fetch(location.pathname, { method:'POST', body:fd })
      .then(r=>r.json())
      .then(j=>{
        if(!j.ok){
          alert(j.msg || 'No se pudo actualizar prioridad');
          location.reload();
        }
      })
      .catch(()=>{
        alert('Error de red al guardar prioridad');
        location.reload();
      });
  });
});

/* ---------- Ordenamiento por columna (client-side) ---------- */
(function(){
  function getCellText(row, colIndex){
    const cell = row.children[colIndex];
    if(!cell) return '';
    if(cell.dataset.sortValue) return cell.dataset.sortValue;
    // Si la celda contiene un input/select (ej: folder) preferimos su value
    const sel = cell.querySelector('select');
    if(sel) return sel.value === '' ? '' : sel.value;
    // Badge/registros: usar textContent
    return cell.textContent.trim();
  }

  function detectNumericSample(values){
    // Si mÃ¡s del 60% de valores parsean como nÃºmero => numÃ©rica
    let numCount = 0;
    let total = 0;
    for(const v of values){
      if(v==='') continue;
      total++;
      if(!isNaN(Number(v.replace(/[^\d\.\-]/g,'')))) numCount++;
    }
    if(total===0) return false;
    return (numCount/total) >= 0.6;
  }

  function compareValues(a,b, numeric, desc){
    if(numeric){
      const na = parseFloat(a.replace(/[^\d\.\-]/g,'')) || 0;
      const nb = parseFloat(b.replace(/[^\d\.\-]/g,'')) || 0;
      return desc ? (nb - na) : (na - nb);
    } else {
      // comparaciÃ³n lingÃ¼Ã­stica sensible
      const sa = a.toString().toLowerCase();
      const sb = b.toString().toLowerCase();
      if(sa < sb) return desc ? 1 : -1;
      if(sa > sb) return desc ? -1 : 1;
      return 0;
    }
  }

  function clearIndicators(ths){
    ths.forEach(th=> th.querySelector('.sort-indicator').textContent = '');
  }

  function initColumnSort(){
    const table = document.querySelector('.table-wrap table');
    if(!table) return;
    const thead = table.tHead;
    const tbody = table.tBodies[0];
    if(!thead || !tbody) return;

    const ths = [...thead.querySelectorAll('th')];
    ths.forEach((th, colIndex) => {
      // SÃ³lo aÃ±ado handler si tiene data-sort
      if(!th.dataset.sort) return;
      th.style.cursor = 'pointer';
      th.title = 'Ordenar por ' + (th.innerText || '').trim();
      th.addEventListener('click', function(e){
        // Determinar orden actual y alternar
        const cur = th.dataset.order === 'asc' ? 'asc' : (th.dataset.order === 'desc' ? 'desc' : null);
        const nuevo = cur === 'asc' ? 'desc' : 'asc';
        // recolectar valores de esa columna
        const rows = [...tbody.querySelectorAll('tr')];
        const sampleVals = rows.map(r => getCellText(r, colIndex));
        const numeric = detectNumericSample(sampleVals);

        // Crear array con [row, value] para ordenar
        const arr = rows.map(r => ({ row: r, val: getCellText(r, colIndex) }));

        arr.sort((A,B) => {
          return compareValues(A.val, B.val, numeric, nuevo === 'desc');
        });

        // Reinsertar en tbody segÃºn orden
        const frag = document.createDocumentFragment();
        arr.forEach(item => frag.appendChild(item.row));
        tbody.appendChild(frag);

        // Actualizar indicadores visuales
        ths.forEach(t => t.removeAttribute('data-order'));
        th.dataset.order = nuevo;
        clearIndicators(ths);
        th.querySelector('.sort-indicator').textContent = nuevo === 'asc' ? 'â–²' : 'â–¼';
      });
    });
  }

  // Inicializar tras carga completa del DOM (y tras tus otros handlers)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initColumnSort);
  } else {
    initColumnSort();
  }

})();
</script>
</body>
</html>
