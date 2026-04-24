<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function xml_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

function cdata_safe($value): string
{
    return str_replace(']]>', ']]]]><![CDATA[>', (string) $value);
}

function base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return $scheme . '://' . $host . ($dir !== '' ? $dir : '');
}

function build_ubigeo_code($codDep, $codProv, $codDist): ?string
{
    $dep = preg_replace('/\D+/', '', trim((string) $codDep));
    $prov = preg_replace('/\D+/', '', trim((string) $codProv));
    $dist = preg_replace('/\D+/', '', trim((string) $codDist));

    if ($dep === '' || $prov === '' || $dist === '') {
        return null;
    }

    return str_pad($dep, 2, '0', STR_PAD_LEFT)
        . str_pad($prov, 2, '0', STR_PAD_LEFT)
        . str_pad($dist, 2, '0', STR_PAD_LEFT);
}

function fetch_remote_text(string $url, int $timeout = 20): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'UIAT-Norte-Accidentes-Mapa/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json, text/plain, */*'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (is_string($body) && $body !== '' && $status >= 200 && $status < 300) {
            return $body;
        }
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => "User-Agent: UIAT-Norte-Accidentes-Mapa/1.0\r\nAccept: application/json, text/plain, */*\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    return is_string($body) && $body !== '' ? $body : null;
}

function filter_geojson_features(array $geojson, array $ubigeos): array
{
    $lookup = array_fill_keys($ubigeos, true);
    $features = [];

    foreach (($geojson['features'] ?? []) as $feature) {
        $districtId = (string) (($feature['properties']['IDDIST'] ?? '') ?: ($feature['properties']['UBIGEO'] ?? ''));
        if ($districtId !== '' && isset($lookup[$districtId])) {
            $features[] = $feature;
        }
    }

    return [
        'type' => 'FeatureCollection',
        'features' => $features,
    ];
}

function load_district_geojson_subset(array $ubigeos): array
{
    $ubigeos = array_values(array_unique(array_filter(array_map(static fn ($value) => preg_replace('/\D+/', '', (string) $value), $ubigeos), static fn ($value) => preg_match('/^\d{6}$/', (string) $value))));
    if ($ubigeos === []) {
        return ['type' => 'FeatureCollection', 'features' => []];
    }

    $cacheDir = __DIR__ . '/storage/cache/maps';
    $cacheFile = $cacheDir . '/districts_subset.geojson';
    $cachedGeojson = null;
    $cachedDistricts = [];

    if (is_file($cacheFile)) {
        $cachedRaw = @file_get_contents($cacheFile);
        $decoded = is_string($cachedRaw) ? json_decode($cachedRaw, true) : null;
        if (is_array($decoded) && ($decoded['type'] ?? '') === 'FeatureCollection') {
            $cachedGeojson = $decoded;
            foreach (($decoded['features'] ?? []) as $feature) {
                $districtId = (string) (($feature['properties']['IDDIST'] ?? '') ?: ($feature['properties']['UBIGEO'] ?? ''));
                if ($districtId !== '') {
                    $cachedDistricts[$districtId] = true;
                }
            }
        }
    }

    $missing = array_values(array_diff($ubigeos, array_keys($cachedDistricts)));
    if ($missing !== []) {
        $remoteRaw = fetch_remote_text('https://raw.githubusercontent.com/juaneladio/peru-geojson/master/peru_distrital_simple.geojson');
        $remoteGeojson = is_string($remoteRaw) ? json_decode($remoteRaw, true) : null;
        if (is_array($remoteGeojson) && ($remoteGeojson['type'] ?? '') === 'FeatureCollection') {
            $targetDistricts = array_values(array_unique(array_merge(array_keys($cachedDistricts), $ubigeos)));
            $subset = filter_geojson_features($remoteGeojson, $targetDistricts);
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0775, true);
            }
            @file_put_contents($cacheFile, json_encode($subset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $cachedGeojson = $subset;
        }
    }

    if (!is_array($cachedGeojson)) {
        return ['type' => 'FeatureCollection', 'features' => []];
    }

    return filter_geojson_features($cachedGeojson, $ubigeos);
}

function parse_gps_string(?string $raw): ?array
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }

    if (!preg_match('/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/', $raw, $match)) {
        return null;
    }

    $lat = (float) $match[1];
    $lng = (float) $match[2];

    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return null;
    }

    return ['lat' => $lat, 'lng' => $lng];
}

$estadoOpciones = [
    'todos' => 'Todos',
    'Pendiente' => 'Pendiente',
    'Resuelto' => 'Resuelto',
    'Con diligencias' => 'Con diligencias',
];

$estado = trim((string) ($_GET['estado'] ?? 'todos'));
if (!array_key_exists($estado, $estadoOpciones)) {
    $estado = 'todos';
}

$q = trim((string) ($_GET['q'] ?? ''));
$desde = trim((string) ($_GET['desde'] ?? ''));
$hasta = trim((string) ($_GET['hasta'] ?? ''));
$distrito = trim((string) ($_GET['distrito'] ?? ''));
$comisariaId = (int) ($_GET['comisaria_id'] ?? 0);

$comisarias = $pdo->query("SELECT id, nombre FROM comisarias ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT a.id, a.sidpol, a.registro_sidpol, a.tipo_registro, a.lugar, a.referencia,
               a.fecha_accidente, a.estado, a.latitud, a.longitud, a.cod_dep, a.cod_prov, a.cod_dist,
               c.nombre AS comisaria,
               dep.nombre AS departamento,
               prov.nombre AS provincia,
               dist.nombre AS distrito,
               (
                   SELECT i.ubicacion_gps
                   FROM itp i
                   WHERE i.accidente_id = a.id
                     AND TRIM(COALESCE(i.ubicacion_gps, '')) <> ''
                   ORDER BY i.id DESC
                   LIMIT 1
               ) AS itp_ubicacion_gps
        FROM accidentes a
        LEFT JOIN comisarias c
            ON c.id = a.comisaria_id
        LEFT JOIN ubigeo_departamento dep
            ON dep.cod_dep = a.cod_dep
        LEFT JOIN ubigeo_provincia prov
            ON prov.cod_dep = a.cod_dep
           AND prov.cod_prov = a.cod_prov
        LEFT JOIN ubigeo_distrito dist
            ON dist.cod_dep = a.cod_dep
           AND dist.cod_prov = a.cod_prov
           AND dist.cod_dist = a.cod_dist
        WHERE 1 = 1";

$params = [];
if ($estado !== 'todos') {
    $sql .= " AND COALESCE(NULLIF(TRIM(a.estado), ''), 'Pendiente') = ?";
    $params[] = $estado;
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $sql .= " AND (
        a.sidpol LIKE ?
        OR a.registro_sidpol LIKE ?
        OR a.lugar LIKE ?
        OR a.referencia LIKE ?
        OR c.nombre LIKE ?
        OR dist.nombre LIKE ?
    )";
    array_push($params, $like, $like, $like, $like, $like, $like);
}

if ($desde !== '') {
    $sql .= " AND DATE(a.fecha_accidente) >= ?";
    $params[] = $desde;
}

if ($hasta !== '') {
    $sql .= " AND DATE(a.fecha_accidente) <= ?";
    $params[] = $hasta;
}

if ($comisariaId > 0) {
    $sql .= " AND a.comisaria_id = ?";
    $params[] = $comisariaId;
}

if ($distrito !== '') {
    $sql .= " AND dist.nombre LIKE ?";
    $params[] = '%' . $distrito . '%';
}

$sql .= " ORDER BY a.fecha_accidente DESC, a.id DESC LIMIT 1500";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$markers = [];
$rowsWithoutGeo = [];
$districtUbigeos = [];
$districtLabels = [];

foreach ($rows as $row) {
    $districtUbigeo = build_ubigeo_code($row['cod_dep'] ?? null, $row['cod_prov'] ?? null, $row['cod_dist'] ?? null);
    if ($districtUbigeo !== null) {
        $districtUbigeos[$districtUbigeo] = true;
        $districtLabels[$districtUbigeo] = [
            'district' => (string) ($row['distrito'] ?? ''),
            'province' => (string) ($row['provincia'] ?? ''),
        ];
    }

    $lat = is_numeric((string) ($row['latitud'] ?? null)) ? (float) $row['latitud'] : null;
    $lng = is_numeric((string) ($row['longitud'] ?? null)) ? (float) $row['longitud'] : null;
    $origin = null;

    if ($lat !== null && $lng !== null) {
        $origin = 'accidente';
    } else {
        $itpPoint = parse_gps_string($row['itp_ubicacion_gps'] ?? '');
        if ($itpPoint !== null) {
            $lat = $itpPoint['lat'];
            $lng = $itpPoint['lng'];
            $origin = 'itp';
        }
    }

    $row['ubicacion_compacta'] = trim(implode(' / ', array_filter([
        $row['distrito'] ?? '',
        $row['provincia'] ?? '',
        $row['departamento'] ?? '',
    ])));

    if ($lat === null || $lng === null) {
        $rowsWithoutGeo[] = $row;
        continue;
    }

    $markers[] = [
        'id' => (int) $row['id'],
        'sidpol' => (string) ($row['sidpol'] ?? ''),
        'registro_sidpol' => (string) ($row['registro_sidpol'] ?? ''),
        'tipo_registro' => (string) ($row['tipo_registro'] ?? ''),
        'lugar' => (string) ($row['lugar'] ?? ''),
        'referencia' => (string) ($row['referencia'] ?? ''),
        'fecha_accidente' => (string) ($row['fecha_accidente'] ?? ''),
        'estado' => (string) ($row['estado'] ?? 'Pendiente'),
        'comisaria' => (string) ($row['comisaria'] ?? ''),
        'ubicacion_compacta' => (string) ($row['ubicacion_compacta'] ?? ''),
        'district_ubigeo' => $districtUbigeo,
        'district_name' => (string) ($row['distrito'] ?? ''),
        'lat' => $lat,
        'lng' => $lng,
        'source' => $origin,
        'view_url' => 'accidente_vista_tabs.php?accidente_id=' . (int) $row['id'],
        'edit_url' => 'accidente_editar.php?id=' . (int) $row['id'],
    ];
}

$geoCount = count($markers);
$totalCount = count($rows);
$missingGeoCount = count($rowsWithoutGeo);
$districtGeoJson = load_district_geojson_subset(array_keys($districtUbigeos));
foreach (($districtGeoJson['features'] ?? []) as &$feature) {
    $districtId = (string) (($feature['properties']['IDDIST'] ?? '') ?: ($feature['properties']['UBIGEO'] ?? ''));
    if ($districtId !== '' && isset($districtLabels[$districtId])) {
        $feature['properties']['DISPLAY_DISTRICT'] = $districtLabels[$districtId]['district'];
        $feature['properties']['DISPLAY_PROVINCE'] = $districtLabels[$districtId]['province'];
    }
}
unset($feature);

$exportQuery = array_filter([
    'q' => $q,
    'desde' => $desde,
    'hasta' => $hasta,
    'distrito' => $distrito,
    'comisaria_id' => $comisariaId > 0 ? $comisariaId : null,
    'estado' => $estado !== 'todos' ? $estado : null,
    'format' => 'kml',
], static fn ($value) => $value !== null && $value !== '');

if (($_GET['format'] ?? '') === 'kml') {
    $baseUrl = base_url();
    $today = date('Ymd_His');
    $fileName = 'accidentes_mapa_' . $today . '.kml';
    $filterParts = [];
    if ($q !== '') $filterParts[] = 'Busqueda: ' . $q;
    if ($desde !== '') $filterParts[] = 'Desde: ' . $desde;
    if ($hasta !== '') $filterParts[] = 'Hasta: ' . $hasta;
    if ($distrito !== '') $filterParts[] = 'Distrito: ' . $distrito;
    if ($comisariaId > 0) {
        foreach ($comisarias as $item) {
            if ((int) $item['id'] === $comisariaId) {
                $filterParts[] = 'Comisaria: ' . (string) $item['nombre'];
                break;
            }
        }
    }
    if ($estado !== 'todos') $filterParts[] = 'Estado: ' . $estado;
    $filterSummary = $filterParts !== [] ? implode(' | ', $filterParts) : 'Sin filtros adicionales';

    $kml = [];
    $kml[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $kml[] = '<kml xmlns="http://www.opengis.net/kml/2.2">';
    $kml[] = '<Document>';
    $kml[] = '<name>' . xml_h('Mapa de Accidentes UIAT Norte') . '</name>';
    $kml[] = '<description>' . xml_h('Exportación KML generada desde UIAT Norte. ' . $filterSummary) . '</description>';
    $kml[] = '<Style id="estado-pendiente"><IconStyle><color>ff0b9ef5</color><scale>1.15</scale><Icon><href>http://maps.google.com/mapfiles/kml/paddle/orange-circle.png</href></Icon></IconStyle></Style>';
    $kml[] = '<Style id="estado-resuelto"><IconStyle><color>ff5ec522</color><scale>1.15</scale><Icon><href>http://maps.google.com/mapfiles/kml/paddle/grn-circle.png</href></Icon></IconStyle></Style>';
    $kml[] = '<Style id="estado-diligencias"><IconStyle><color>fff8bd38</color><scale>1.15</scale><Icon><href>http://maps.google.com/mapfiles/kml/paddle/blu-circle.png</href></Icon></IconStyle></Style>';

    foreach ($markers as $marker) {
        $styleId = '#estado-pendiente';
        if (($marker['estado'] ?? '') === 'Resuelto') {
            $styleId = '#estado-resuelto';
        } elseif (($marker['estado'] ?? '') === 'Con diligencias') {
            $styleId = '#estado-diligencias';
        }

        $viewUrl = $baseUrl . '/' . ltrim((string) $marker['view_url'], '/');
        $editUrl = $baseUrl . '/' . ltrim((string) $marker['edit_url'], '/');
        $gmapsUrl = 'https://www.google.com/maps?q=' . rawurlencode($marker['lat'] . ',' . $marker['lng']);
        $descriptionHtml = ''
            . '<strong>SIDPOL:</strong> ' . h($marker['sidpol'] !== '' ? $marker['sidpol'] : $marker['registro_sidpol']) . '<br>'
            . '<strong>Fecha:</strong> ' . h($marker['fecha_accidente']) . '<br>'
            . '<strong>Estado:</strong> ' . h($marker['estado']) . '<br>'
            . '<strong>Ubicación:</strong> ' . h($marker['ubicacion_compacta']) . '<br>'
            . '<strong>Comisaría:</strong> ' . h($marker['comisaria']) . '<br>'
            . '<strong>Referencia:</strong> ' . h($marker['referencia']) . '<br>'
            . '<strong>Fuente GPS:</strong> ' . h($marker['source'] === 'itp' ? 'ITP' : 'Accidente') . '<br><br>'
            . '<a href="' . h($viewUrl) . '">Ver caso</a><br>'
            . '<a href="' . h($editUrl) . '">Editar caso</a><br>'
            . '<a href="' . h($gmapsUrl) . '">Abrir en Google Maps</a>';

        $kml[] = '<Placemark>';
        $kml[] = '<name>' . xml_h($marker['lugar'] !== '' ? $marker['lugar'] : ('Accidente #' . $marker['id'])) . '</name>';
        $kml[] = '<styleUrl>' . $styleId . '</styleUrl>';
        $kml[] = '<description><![CDATA[' . cdata_safe($descriptionHtml) . ']]></description>';
        $kml[] = '<ExtendedData>';
        $kml[] = '<Data name="id"><value>' . xml_h($marker['id']) . '</value></Data>';
        $kml[] = '<Data name="estado"><value>' . xml_h($marker['estado']) . '</value></Data>';
        $kml[] = '<Data name="sidpol"><value>' . xml_h($marker['sidpol'] !== '' ? $marker['sidpol'] : $marker['registro_sidpol']) . '</value></Data>';
        $kml[] = '<Data name="fecha_accidente"><value>' . xml_h($marker['fecha_accidente']) . '</value></Data>';
        $kml[] = '<Data name="comisaria"><value>' . xml_h($marker['comisaria']) . '</value></Data>';
        $kml[] = '<Data name="fuente_gps"><value>' . xml_h($marker['source'] === 'itp' ? 'ITP' : 'Accidente') . '</value></Data>';
        $kml[] = '</ExtendedData>';
        $kml[] = '<Point><coordinates>' . xml_h($marker['lng'] . ',' . $marker['lat'] . ',0') . '</coordinates></Point>';
        $kml[] = '</Placemark>';
    }

    $kml[] = '</Document>';
    $kml[] = '</kml>';

    header('Content-Type: application/vnd.google-earth.kml+xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    echo implode("\n", $kml);
    exit;
}

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mapa de Accidentes | UIAT Norte</title>
<link rel="stylesheet" href="assets/vendor/leaflet/leaflet.css">
<style>
:root{
  --page:#8fd2eb;
  --panel:#ffffff;
  --panel-soft:#f8fbfd;
  --border:rgba(15,23,42,.12);
  --text:#0f172a;
  --muted:#64748b;
  --accent:#1766cf;
  --accent-2:#047857;
  --warning:#f59e0b;
  --danger:#ef4444;
}
*{box-sizing:border-box}
body{margin:0;background:linear-gradient(180deg,#9fd8ee 0%,#90d2ea 100%);color:var(--text);font:14px/1.45 "Segoe UI",Tahoma,Arial,sans-serif}
.wrap{max-width:1480px;margin:20px auto;padding:16px}
.hero,.card{background:var(--panel);border:1px solid var(--border);border-radius:22px;box-shadow:0 16px 38px rgba(15,23,42,.08)}
.hero{padding:20px 22px;margin-bottom:16px}
.hero-top{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}
.hero h1{margin:0;font-size:30px;letter-spacing:.01em}
.hero p{margin:8px 0 0;color:var(--muted);max-width:760px}
.toolbar{display:flex;gap:10px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:14px;border:1px solid rgba(15,23,42,.12);color:var(--text);text-decoration:none;background:#fff}
.btn.primary{background:linear-gradient(135deg,#1766cf,#0ea5e9);color:#fff;border-color:transparent}
.btn.secondary{background:#ecfdf5;color:#065f46;border-color:rgba(5,150,105,.18)}
.stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:18px}
.stat{padding:14px 16px;border-radius:18px;background:var(--panel-soft);border:1px solid var(--border)}
.stat .k{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
.stat .v{font-size:28px;font-weight:800;margin-top:6px}
.stat .s{font-size:13px;color:var(--muted);margin-top:6px}
.filters{display:grid;grid-template-columns:minmax(0,1.4fr) repeat(4,minmax(0,1fr)) auto auto;gap:10px;padding:16px;margin-bottom:16px}
.filters input,.filters select{width:100%;padding:11px 12px;border-radius:14px;border:1px solid var(--border);background:#fff;color:var(--text)}
.main-grid{display:grid;grid-template-columns:360px minmax(0,1fr);gap:16px}
.list-card,.map-card{min-height:72vh}
.list-card{padding:14px;display:flex;flex-direction:column}
.list-head{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:12px}
.list-title{font-weight:800;font-size:16px}
.list-sub{font-size:12px;color:var(--muted)}
.case-list{display:flex;flex-direction:column;gap:10px;overflow:auto;padding-right:4px}
.case-item{padding:14px;border-radius:18px;border:1px solid var(--border);background:#fff;cursor:pointer;transition:transform .16s ease,border-color .16s ease,box-shadow .16s ease}
.case-item:hover,.case-item.active{transform:translateY(-1px);border-color:rgba(23,102,207,.42);box-shadow:0 14px 26px rgba(15,23,42,.12)}
.case-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
.case-title{font-weight:800}
.case-meta{margin-top:8px;color:var(--muted);font-size:13px}
.chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.chip{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:999px;font-size:12px;border:1px solid var(--border);background:#f8fafc}
.chip.pending{border-color:rgba(245,158,11,.45);color:#b45309}
.chip.resolved{border-color:rgba(34,197,94,.35);color:#15803d}
.chip.dilig{border-color:rgba(14,165,233,.35);color:#0369a1}
.chip.source-itp{border-color:rgba(168,85,247,.28);color:#7c3aed}
.map-card{padding:14px}
.map-tools{display:grid;grid-template-columns:minmax(0,1fr) auto auto auto auto;gap:10px;align-items:center;margin-bottom:12px}
.map-hints{font-size:12px;color:var(--muted)}
.toggle-group{display:flex;gap:8px;flex-wrap:wrap}
.toggle-chip{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border-radius:999px;border:1px solid var(--border);background:#fff;color:var(--text);cursor:pointer}
.toggle-chip input{accent-color:#1766cf}
.range-wrap{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:999px;border:1px solid var(--border);background:#fff}
.style-wrap{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:999px;border:1px solid var(--border);background:#fff}
.style-wrap select{border:none;background:transparent;color:var(--text);padding:0;min-width:110px}
.range-wrap input{width:120px;padding:0;border:none;background:transparent}
.range-wrap output{min-width:36px;text-align:right;color:#1766cf;font-weight:700}
.map-frame{height:100%;min-height:66vh;border-radius:20px;overflow:hidden;border:2px solid #1d77d0;background:#fff}
.map{width:100%;height:100%}
.empty{padding:18px;border-radius:18px;border:1px dashed var(--border);background:#f8fafc;color:var(--muted)}
.missing{margin-top:14px;padding-top:14px;border-top:1px solid var(--border)}
.missing summary{cursor:pointer;color:#0f172a;font-weight:700}
.missing ul{margin:12px 0 0;padding-left:18px;color:var(--muted)}
.infowindow{color:#0f172a;max-width:280px;font:13px/1.4 "Segoe UI",Arial,sans-serif}
.infowindow strong{display:block;font-size:14px;margin-bottom:6px}
.infowindow .meta{color:#475569;margin:6px 0}
.infowindow .actions{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
.infowindow a{color:#0f172a}
.leaflet-container{background:#d7edf7}
.leaflet-popup-content-wrapper{border-radius:16px;box-shadow:0 18px 34px rgba(15,23,42,.18)}
.leaflet-popup-content{margin:14px 16px}
.leaflet-control-zoom{border:none !important;box-shadow:0 10px 20px rgba(15,23,42,.12)}
.leaflet-control-zoom a{background:rgba(255,255,255,.97) !important;color:#0f172a !important;border-bottom:1px solid rgba(148,163,184,.28) !important}
.leaflet-control-attribution{background:rgba(255,255,255,.9) !important;color:#334155 !important;border-radius:10px 0 0 0;padding:4px 8px}
.leaflet-control-attribution a{color:#0f766e !important}
.district-tooltip{background:rgba(255,255,255,.96);border:1px solid rgba(239,68,68,.22);color:#991b1b;border-radius:999px;box-shadow:0 10px 26px rgba(15,23,42,.12);font:12px/1.2 "Segoe UI",Arial,sans-serif;padding:6px 10px}
.district-tooltip:before{display:none}
.district-label{
  color:#dc2626;
  font:700 18px/1 "Segoe UI",Tahoma,Arial,sans-serif;
  text-transform:uppercase;
  letter-spacing:.04em;
  text-shadow:
    -1px -1px 0 rgba(255,255,255,.95),
    1px -1px 0 rgba(255,255,255,.95),
    -1px 1px 0 rgba(255,255,255,.95),
    1px 1px 0 rgba(255,255,255,.95),
    0 2px 8px rgba(255,255,255,.85);
  white-space:nowrap;
  pointer-events:none;
}
@media(max-width:1100px){
  .main-grid{grid-template-columns:1fr}
  .list-card,.map-card,.map-frame{min-height:auto}
  .map-frame{height:62vh}
  .map-tools{grid-template-columns:1fr}
}
@media(max-width:960px){
  .district-label{font-size:13px}
}
@media(max-width:760px){
  .stats{grid-template-columns:1fr}
  .filters{grid-template-columns:1fr}
  .hero h1{font-size:24px}
}
</style>
</head>
<body>
<div class="wrap">
  <section class="hero">
    <div class="hero-top">
      <div>
        <h1>Mapa de Accidentes</h1>
        <p>Vista georreferenciada de los accidentes de tránsito. El mapa prioriza las coordenadas guardadas en el accidente y, si aún no existen, usa como respaldo la ubicación GPS del ITP.</p>
      </div>
      <nav class="toolbar" aria-label="Acciones del mapa">
        <a class="btn" href="index.php">Inicio</a>
        <a class="btn" href="accidente_listar.php">Listado</a>
        <a class="btn secondary" href="<?= h('accidente_mapa.php?' . http_build_query($exportQuery)) ?>">Exportar KML</a>
        <a class="btn primary" href="accidente_nuevo.php">Nuevo accidente</a>
      </nav>
    </div>

    <div class="stats">
      <div class="stat">
        <div class="k">Accidentes consultados</div>
        <div class="v"><?= number_format($totalCount) ?></div>
        <div class="s">Casos cargados según los filtros actuales.</div>
      </div>
      <div class="stat">
        <div class="k">Con punto en mapa</div>
        <div class="v"><?= number_format($geoCount) ?></div>
        <div class="s">Accidentes listos para verse en estilo satelital.</div>
      </div>
      <div class="stat">
        <div class="k">Pendientes de georreferenciar</div>
        <div class="v"><?= number_format($missingGeoCount) ?></div>
        <div class="s">Casos que todavía no tienen latitud y longitud aprovechables.</div>
      </div>
    </div>
  </section>

  <form class="card filters" method="get">
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar por SIDPOL, lugar, referencia o comisaría">
    <input type="date" name="desde" value="<?= h($desde) ?>" aria-label="Desde">
    <input type="date" name="hasta" value="<?= h($hasta) ?>" aria-label="Hasta">
    <select name="comisaria_id" aria-label="Comisaría">
      <option value="0">Todas las comisarías</option>
      <?php foreach ($comisarias as $comisaria): ?>
        <option value="<?= (int) $comisaria['id'] ?>" <?= $comisariaId === (int) $comisaria['id'] ? 'selected' : '' ?>><?= h($comisaria['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="distrito" value="<?= h($distrito) ?>" placeholder="Distrito">
    <select name="estado">
      <?php foreach ($estadoOpciones as $value => $label): ?>
        <option value="<?= h($value) ?>" <?= $estado === $value ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn secondary" type="submit">Aplicar filtro</button>
    <a class="btn" href="accidente_mapa.php">Limpiar</a>
  </form>

  <section class="main-grid">
    <aside class="card list-card">
      <div class="list-head">
        <div>
          <div class="list-title">Casos ubicados</div>
          <div class="list-sub"><?= number_format($geoCount) ?> accidentes con coordenadas visibles</div>
        </div>
      </div>

      <?php if ($markers === []): ?>
        <div class="empty">No hay accidentes con coordenadas para los filtros elegidos. Puedes editar un caso y marcar su punto desde el formulario.</div>
      <?php else: ?>
        <div class="case-list" id="case-list">
          <?php foreach ($markers as $marker): ?>
            <?php
              $estadoClass = 'pending';
              if ($marker['estado'] === 'Resuelto') {
                  $estadoClass = 'resolved';
              } elseif ($marker['estado'] === 'Con diligencias') {
                  $estadoClass = 'dilig';
              }
            ?>
            <article class="case-item" data-case-id="<?= (int) $marker['id'] ?>">
              <div class="case-top">
                <div class="case-title"><?= h($marker['lugar'] !== '' ? $marker['lugar'] : ('Accidente #' . $marker['id'])) ?></div>
                <div class="list-sub">#<?= (int) $marker['id'] ?></div>
              </div>
              <div class="case-meta">
                SIDPOL: <?= h($marker['sidpol'] !== '' ? $marker['sidpol'] : $marker['registro_sidpol']) ?><br>
                <?= h($marker['ubicacion_compacta']) ?><br>
                <?= h($marker['fecha_accidente']) ?>
              </div>
              <div class="chips">
                <span class="chip <?= h($estadoClass) ?>"><?= h($marker['estado']) ?></span>
                <?php if ($marker['source'] === 'itp'): ?>
                  <span class="chip source-itp">GPS tomado del ITP</span>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($rowsWithoutGeo !== []): ?>
        <details class="missing">
          <summary>Ver <?= number_format(count($rowsWithoutGeo)) ?> accidentes aún sin punto de mapa</summary>
          <ul>
            <?php foreach (array_slice($rowsWithoutGeo, 0, 20) as $row): ?>
              <li>
                <a href="<?= h('accidente_editar.php?id=' . (int) $row['id']) ?>" class="btn" style="padding:6px 10px;margin-top:6px">
                  #<?= (int) $row['id'] ?> <?= h($row['lugar'] !== '' ? $row['lugar'] : 'Sin lugar') ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </details>
      <?php endif; ?>
    </aside>

    <section class="card map-card">
      <div class="map-tools">
        <div class="map-hints">El estilo PNP deja calles y avenidas visibles, con límites distritales resaltados y el nombre del distrito directamente sobre el mapa.</div>
        <div class="toggle-group">
          <label class="toggle-chip"><input type="checkbox" id="toggle-markers" checked> Pines</label>
          <label class="toggle-chip"><input type="checkbox" id="toggle-heatmap"> Heatmap</label>
          <label class="toggle-chip"><input type="checkbox" id="toggle-districts" checked> Distritos</label>
        </div>
        <label class="style-wrap" for="map-style">
          Estilo
          <select id="map-style">
            <option value="pnp" selected>PNP</option>
            <option value="roadmap">Mapa</option>
            <option value="hybrid">Híbrido</option>
            <option value="satellite">Satélite</option>
            <option value="terrain">Relieve</option>
          </select>
        </label>
        <label class="range-wrap" for="heatmap-radius">
          Radio
          <input type="range" id="heatmap-radius" min="15" max="60" step="1" value="28">
          <output id="heatmap-radius-value">28</output>
        </label>
        <button type="button" class="btn" id="fit-map">Ajustar vista</button>
      </div>
      <div class="map-frame">
        <div id="accidentes-map" class="map"></div>
      </div>
    </section>
  </section>
</div>

<script src="assets/vendor/leaflet/leaflet.js"></script>
<script src="assets/vendor/leaflet-heat/leaflet-heat.js"></script>
<script>
const accidentesMapData = <?= json_encode($markers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const districtGeoData = <?= json_encode($districtGeoJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

window.initAccidentesMap = function initAccidentesMap(){
  const mapEl = document.getElementById('accidentes-map');
  if(!mapEl || !window.L){
    return;
  }

  const defaultCenter = [-9.189967, -75.015152];
  const caseItems = Array.from(document.querySelectorAll('.case-item'));
  const toggleMarkers = document.getElementById('toggle-markers');
  const toggleHeatmap = document.getElementById('toggle-heatmap');
  const toggleDistricts = document.getElementById('toggle-districts');
  const mapStyle = document.getElementById('map-style');
  const heatmapRadius = document.getElementById('heatmap-radius');
  const heatmapRadiusValue = document.getElementById('heatmap-radius-value');
  const fitMapBtn = document.getElementById('fit-map');

  const map = L.map(mapEl, {
    center: defaultCenter,
    zoom: 6,
    zoomControl: true,
  });

  map.createPane('districts');
  map.getPane('districts').style.zIndex = 420;
  map.createPane('district-labels');
  map.getPane('district-labels').style.zIndex = 430;
  map.getPane('district-labels').style.pointerEvents = 'none';
  map.createPane('labels');
  map.getPane('labels').style.zIndex = 650;
  map.getPane('labels').style.pointerEvents = 'none';

  const baseLayers = {
    pnp: {
      base: () => L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; Esri, HERE, Garmin, USGS, OpenStreetMap contributors',
        maxZoom: 19,
      }),
    },
    roadmap: {
      base: () => L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO',
        maxZoom: 20,
        subdomains: 'abcd',
      }),
    },
    hybrid: {
      base: () => L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; Esri, Maxar, Earthstar Geographics',
        maxZoom: 19,
      }),
      overlay: () => L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO',
        maxZoom: 20,
        subdomains: 'abcd',
        opacity: 0.96,
        pane: 'labels',
        noWrap: false,
        tileSize: 256,
        detectRetina: true,
      }),
    },
    satellite: {
      base: () => L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; Esri, Maxar, Earthstar Geographics',
        maxZoom: 19,
      }),
    },
    terrain: {
      base: () => L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenTopoMap, OpenStreetMap',
        maxZoom: 17,
      }),
    },
  };
  let activeBaseLayer = null;
  let activeOverlayLayer = null;

  function applyBaseLayer(styleKey){
    if(activeBaseLayer && map.hasLayer(activeBaseLayer)){
      map.removeLayer(activeBaseLayer);
    }
    if(activeOverlayLayer && map.hasLayer(activeOverlayLayer)){
      map.removeLayer(activeOverlayLayer);
    }
    const config = baseLayers[styleKey] || baseLayers.pnp;
    activeBaseLayer = config.base();
    activeBaseLayer.addTo(map);
    activeOverlayLayer = config.overlay ? config.overlay() : null;
    if(activeOverlayLayer){
      activeOverlayLayer.addTo(map);
    }
  }

  applyBaseLayer(mapStyle?.value || 'pnp');

  const bounds = [];
  const markersById = new Map();
  const itemsById = new Map();
  const markerInstances = [];
  const heatPoints = [];
  let heatmap = null;
  let districtsLayer = null;
  let districtLabelsLayer = L.layerGroup();
  let activeDistrictUbigeo = null;
  let activeCaseId = null;

  function escapeHtml(value){
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
  }

  function popupHtml(item){
    return `
      <div class="infowindow">
        <strong>${escapeHtml(item.lugar || `Accidente #${item.id}`)}</strong>
        <div>${escapeHtml(item.ubicacion_compacta || 'Ubicación no detallada')}</div>
        <div class="meta">SIDPOL: ${escapeHtml(item.sidpol || item.registro_sidpol || '-')}</div>
        <div class="meta">Fecha: ${escapeHtml(item.fecha_accidente || '-')}</div>
        <div class="meta">Estado: ${escapeHtml(item.estado || 'Pendiente')}</div>
        <div class="meta">Fuente GPS: ${item.source === 'itp' ? 'ITP' : 'Accidente'}</div>
        <div class="actions">
          <a href="${escapeHtml(item.view_url)}">Ver caso</a>
          <a href="${escapeHtml(item.edit_url)}">Editar</a>
          <a href="https://www.google.com/maps?q=${encodeURIComponent(`${item.lat},${item.lng}`)}" target="_blank" rel="noopener noreferrer">Abrir en Google Maps</a>
        </div>
      </div>
    `;
  }

  function setActiveCase(caseId){
    activeCaseId = Number(caseId);
    caseItems.forEach((node) => node.classList.toggle('active', Number(node.dataset.caseId) === Number(caseId)));
    markerInstances.forEach((marker) => {
      const isActive = Number(marker.__caseId) === Number(caseId);
      marker.setStyle({
        radius: isActive ? 10 : 7,
        weight: isActive ? 3 : 2,
        fillOpacity: isActive ? 1 : 0.92,
      });
      if(isActive){
        marker.bringToFront();
      }
    });
    activeDistrictUbigeo = itemsById.get(Number(caseId))?.district_ubigeo || null;
    refreshDistrictStyles();
  }

  function markerPalette(estado){
    if(estado === 'Resuelto') return {fill:'#0f766e', stroke:'#ecfdf5'};
    if(estado === 'Con diligencias') return {fill:'#1766cf', stroke:'#eff6ff'};
    return {fill:'#b45309', stroke:'#fff7ed'};
  }

  accidentesMapData.forEach((item) => {
    itemsById.set(Number(item.id), item);
    const colors = markerPalette(item.estado);
    const marker = L.circleMarker([item.lat, item.lng], {
      radius: 7,
      color: colors.stroke,
      weight: 2,
      fillColor: colors.fill,
      fillOpacity: 0.92,
    });
    marker.__caseId = item.id;
    marker.bindPopup(popupHtml(item));
    marker.on('click', () => {
      setActiveCase(item.id);
    });
    marker.addTo(map);

    markersById.set(item.id, marker);
    markerInstances.push(marker);
    heatPoints.push([item.lat, item.lng, 0.85]);
    bounds.push([item.lat, item.lng]);
  });

  function districtStyle(featureUbigeo){
    const isActive = featureUbigeo && featureUbigeo === activeDistrictUbigeo;
    return {
      pane: 'districts',
      color: isActive ? '#b91c1c' : '#dc2626',
      weight: isActive ? 4 : 3,
      opacity: isActive ? 1 : 0.96,
      fillColor: '#ffffff',
      fillOpacity: 0,
      dashArray: null,
    };
  }

  function refreshDistrictStyles(){
    if(!districtsLayer){
      return;
    }
    districtsLayer.eachLayer((layer) => {
      const districtId = String(layer.feature?.properties?.IDDIST || layer.feature?.properties?.UBIGEO || '');
      if(layer.setStyle){
        layer.setStyle(districtStyle(districtId));
      }
    });
  }

  if(Array.isArray(districtGeoData?.features) && districtGeoData.features.length){
    districtsLayer = L.geoJSON(districtGeoData, {
      style: (feature) => districtStyle(String(feature?.properties?.IDDIST || feature?.properties?.UBIGEO || '')),
      onEachFeature: (feature, layer) => {
        const districtId = String(feature?.properties?.IDDIST || feature?.properties?.UBIGEO || '');
        const districtName = String(feature?.properties?.DISPLAY_DISTRICT || feature?.properties?.NOMBDIST || feature?.properties?.NOMBRE || 'Distrito');
        const provinceName = String(feature?.properties?.DISPLAY_PROVINCE || feature?.properties?.NOMBPROV || '');

        layer.bindTooltip(
          provinceName ? `${districtName}, ${provinceName}` : districtName,
          {sticky:true, direction:'top', className:'district-tooltip'}
        );

        const labelMarker = L.marker(layer.getBounds().getCenter(), {
          interactive: false,
          pane: 'district-labels',
          icon: L.divIcon({
            className: 'district-label',
            html: escapeHtml(districtName),
          }),
        });
        districtLabelsLayer.addLayer(labelMarker);

        layer.on('mouseover', () => {
          if(layer.setStyle){
            layer.setStyle({
              color:'#991b1b',
              weight:4,
              opacity:1,
              fillColor:'#ffffff',
              fillOpacity:0,
              dashArray:null,
            });
          }
          if(layer.bringToFront){
            layer.bringToFront();
          }
        });

        layer.on('mouseout', () => {
          if(layer.setStyle){
            layer.setStyle(districtStyle(districtId));
          }
        });
      }
    });
  }

  if(bounds.length){
    map.fitBounds(bounds, {padding:[50, 50]});
  }

  if(window.L.heatLayer){
    heatmap = L.heatLayer(heatPoints, {
      radius: Number(heatmapRadius?.value || 28),
      blur: 24,
      maxZoom: 17,
      gradient: {
        0.2: '#38bdf8',
        0.4: '#22c55e',
        0.65: '#facc15',
        0.85: '#f97316',
        1.0: '#ef4444'
      }
    });
  }

  function focusCase(caseId, keepZoom){
    const marker = markersById.get(Number(caseId));
    const item = accidentesMapData.find((entry) => Number(entry.id) === Number(caseId));
    if(!marker || !item){
      return;
    }
    setActiveCase(caseId);
    map.panTo([item.lat, item.lng]);
    if(!keepZoom && map.getZoom() < 17){
      map.setZoom(17);
    }
    marker.openPopup();
  }

  caseItems.forEach((node) => {
    node.addEventListener('click', () => {
      focusCase(Number(node.dataset.caseId), false);
    });
  });

  function syncMarkerVisibility(){
    const visible = toggleMarkers ? toggleMarkers.checked : true;
    markerInstances.forEach((marker) => {
      if(visible){
        if(!map.hasLayer(marker)) marker.addTo(map);
      }else if(map.hasLayer(marker)){
        map.removeLayer(marker);
      }
    });
  }

  function syncHeatmapVisibility(){
    if(!heatmap){
      return;
    }
    const visible = !!(toggleHeatmap && toggleHeatmap.checked);
    if(visible){
      if(!map.hasLayer(heatmap)) heatmap.addTo(map);
    }else if(map.hasLayer(heatmap)){
      map.removeLayer(heatmap);
    }
  }

  function syncDistrictVisibility(){
    if(!districtsLayer){
      return;
    }
    const visible = !!(toggleDistricts && toggleDistricts.checked);
    if(visible){
      if(!map.hasLayer(districtsLayer)){
        districtsLayer.addTo(map);
      }
      if(!map.hasLayer(districtLabelsLayer)){
        districtLabelsLayer.addTo(map);
      }
      districtsLayer.eachLayer((layer) => layer.bringToBack?.());
      refreshDistrictStyles();
    }else{
      if(map.hasLayer(districtsLayer)){
        map.removeLayer(districtsLayer);
      }
      if(map.hasLayer(districtLabelsLayer)){
        map.removeLayer(districtLabelsLayer);
      }
    }
  }

  toggleMarkers?.addEventListener('change', syncMarkerVisibility);
  toggleHeatmap?.addEventListener('change', syncHeatmapVisibility);
  toggleDistricts?.addEventListener('change', syncDistrictVisibility);
  mapStyle?.addEventListener('change', () => {
    applyBaseLayer(mapStyle.value || 'pnp');
    syncDistrictVisibility();
  });
  heatmapRadius?.addEventListener('input', () => {
    if(heatmapRadiusValue){
      heatmapRadiusValue.value = heatmapRadius.value;
      heatmapRadiusValue.textContent = heatmapRadius.value;
    }
    if(heatmap){
      heatmap.setOptions({radius: Number(heatmapRadius.value || 28)});
      heatmap.redraw();
    }
  });
  fitMapBtn?.addEventListener('click', () => {
    if(bounds.length){
      map.fitBounds(bounds, {padding:[50, 50]});
    }
  });

  syncMarkerVisibility();
  syncHeatmapVisibility();
  syncDistrictVisibility();

  if(accidentesMapData.length > 0){
    focusCase(accidentesMapData[0].id, true);
  }
};

document.addEventListener('DOMContentLoaded', () => {
  window.initAccidentesMap();
});
</script>
</body>
</html>
