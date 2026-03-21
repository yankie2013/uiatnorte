<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET NAMES utf8mb4");

include __DIR__ . '/sidebar.php';

function h($s)
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function g($k, $d = null)
{
    return isset($_GET[$k]) ? trim((string) $_GET[$k]) : $d;
}

function short_text($text, int $limit = 60): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return '-';
    }

    return mb_strimwidth($text, 0, $limit, '...', 'UTF-8');
}

$ok = g('ok', '');
$msg = g('msg', '');
$accidenteId = (int) g('accidente_id', 0);
$rolId = (int) g('rol_id', 0);
$q = g('q', '');

$accidentes = $pdo->query("
  SELECT id, CONCAT('#', id, ' - ', DATE_FORMAT(fecha_accidente, '%Y-%m-%d %H:%i'), ' - ', COALESCE(lugar, '')) AS nom
  FROM accidentes
  ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$roles = $pdo->query("
  SELECT Id AS id, Nombre AS nombre
  FROM participacion_persona
  WHERE Activo = 1
  ORDER BY Orden, Nombre
")->fetchAll(PDO::FETCH_ASSOC);

$sql = "
SELECT ip.id, ip.accidente_id, ip.rol_id, ip.vehiculo_id, ip.lesion, ip.observaciones,
       a.fecha_accidente, a.lugar,
       p.id AS persona_id, p.num_doc, p.apellido_paterno, p.apellido_materno, p.nombres,
       v.placa, v.color,
       r.Nombre AS rol_nombre
FROM involucrados_personas ip
JOIN accidentes a            ON a.id = ip.accidente_id
JOIN personas p              ON p.id = ip.persona_id
LEFT JOIN vehiculos v        ON v.id = ip.vehiculo_id
JOIN participacion_persona r ON r.Id = ip.rol_id
WHERE 1=1";

$params = [];
if ($accidenteId > 0) {
    $sql .= " AND ip.accidente_id = ?";
    $params[] = $accidenteId;
}
if ($rolId > 0) {
    $sql .= " AND ip.rol_id = ?";
    $params[] = $rolId;
}
if ($q !== '') {
    $sql .= " AND (p.num_doc LIKE ? OR p.nombres LIKE ? OR p.apellido_paterno LIKE ? OR p.apellido_materno LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql .= " ORDER BY ip.id DESC LIMIT 300";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$base = rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/';
$returnTo = $_SERVER['REQUEST_URI'] ?? ($base . 'involucrados_personas_listar.php');

$messages = [
    'updated' => 'Registro actualizado correctamente.',
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Involucrados - Personas</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    color-scheme: light dark;
    --bg:#0b1020; --panel:#0f1628; --line:#22304a; --ink:#e8eefc; --muted:#9aa4b2;
    --chip:#121a30; --brand:#88aaff; --ok:#10b981; --err:#ef4444;
  }
  @media (prefers-color-scheme: light){
    :root{ --bg:#f6f7fb; --panel:#ffffff; --line:#e5e7eb; --ink:#0f172a; --muted:#64748b; --chip:#f3f4f9; }
  }
  *{box-sizing:border-box}
  body{margin:0;background:
    radial-gradient(1200px 600px at 10% -10%, rgba(136,170,255,.18), transparent 60%),
    radial-gradient(1000px 600px at 90% -10%, rgba(136,170,255,.14), transparent 50%),
    var(--bg);
    color:var(--ink); font:13.5px/1.45 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
  }
  .wrap{max-width:1200px;margin:22px auto;padding:0 14px}
  .toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:8px;flex-wrap:wrap}
  h1{margin:0 0 12px; font-size:28px; font-weight:900; letter-spacing:.2px}
  .subtitle{color:var(--muted); margin-top:-6px; margin-bottom:12px}
  .btn{padding:8px 14px; border-radius:10px; border:1px solid var(--line); background:var(--chip); color:inherit; font-weight:700; cursor:pointer; text-decoration:none}
  .btn:hover{filter:brightness(1.1)}
  .btn.primary{background:#2563eb; border-color:#1d4ed8; color:#fff;}
  .btn.danger{color:#ef4444}
  .card{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,.01)); border:1px solid var(--line); border-radius:14px; padding:12px; box-shadow:0 10px 30px rgba(0,0,0,.25)}
  .grid{display:grid; gap:8px; grid-template-columns: 1fr 1fr 1fr}
  @media (max-width:860px){ .grid{grid-template-columns:1fr} }
  label{display:block; color:var(--muted); font-weight:800; margin:2px 2px 4px}
  input, select{ width:100%; padding:10px 12px; border-radius:12px; border:1px solid var(--line); background:var(--chip); color:inherit }
  table{ width:100%; border-collapse:collapse; border-radius:14px; overflow:hidden; min-width: 920px; }
  th, td{ padding:10px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top }
  th{font-size:12px; text-transform:uppercase; letter-spacing:.03em; color:var(--muted)}
  .ok{ background:#0c3f2d; color:#c6ffe3; border:1px solid #167a59; padding:10px 12px; border-radius:12px; margin-bottom:12px }
  .actions{display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap}
  .stack-actions{display:flex; gap:8px; flex-wrap:wrap}
  .muted{opacity:.85; color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <a class="btn" href="#" onclick="history.back(); return false;">Volver</a>
    <div style="display:flex; gap:8px;">
      <a class="btn primary" href="<?= $base ?>involucrados_personas_nuevo.php<?= $accidenteId ? ('?accidente_id=' . $accidenteId) : '' ?>">Nuevo</a>
      <a class="btn" href="<?= $base ?>index.php">Panel</a>
    </div>
  </div>

  <h1>Involucrados - Personas</h1>
  <div class="subtitle">Listado general de personas involucradas por accidente, rol y documento.</div>

  <?php if ($ok !== ''): ?><div class="ok"><?= h($messages[$ok] ?? 'Operacion realizada correctamente.') ?></div><?php endif; ?>
  <?php if ($msg === 'eliminado'): ?><div class="ok">Involucrado eliminado correctamente.</div><?php endif; ?>

  <form class="card" method="get">
    <div class="grid">
      <div>
        <label>Accidente</label>
        <select name="accidente_id">
          <option value="">Todos</option>
          <?php foreach ($accidentes as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $accidenteId === (int) $a['id'] ? 'selected' : '' ?>><?= h($a['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Rol</label>
        <select name="rol_id">
          <option value="">Todos</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $rolId === (int) $r['id'] ? 'selected' : '' ?>><?= h($r['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Busqueda</label>
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="DNI o nombre">
      </div>
    </div>
    <div class="actions" style="justify-content:flex-start; margin-top:8px">
      <button class="btn primary" type="submit">Filtrar</button>
      <a class="btn" href="<?= $base ?>involucrados_personas_listar.php">Limpiar</a>
    </div>
  </form>

  <div class="card" style="margin-top:12px">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Persona</th>
          <th>Rol</th>
          <th>Vehiculo</th>
          <th>Lesion</th>
          <th>Observaciones</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="muted">Sin registros.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
            $persona = trim((string) ($r['apellido_paterno'] . ' ' . $r['apellido_materno'] . ', ' . $r['nombres']));
            $fechaAccidente = !empty($r['fecha_accidente']) ? date('Y-m-d H:i', strtotime((string) $r['fecha_accidente'])) : 'Sin fecha';
            $accidenteTxt = '#'.$r['accidente_id'].' - '.$fechaAccidente.' - '.($r['lugar'] ?? '-');
            ?>
            <tr>
              <td>#<?= h($r['id']) ?></td>
              <td>
                <div style="font-weight:800"><?= h($persona) ?></div>
                <div class="muted">DNI: <?= h($r['num_doc']) ?></div>
                <div class="muted"><?= h($accidenteTxt) ?></div>
              </td>
              <td><?= h($r['rol_nombre']) ?></td>
              <td>
                <?php if (!empty($r['placa'])): ?>
                  <strong><?= h($r['placa']) ?></strong>
                  <?php if (!empty($r['color'])): ?><span class="muted"> - <?= h($r['color']) ?></span><?php endif; ?>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
              <td><?= h($r['lesion']) ?></td>
              <td><?= h(short_text($r['observaciones'] ?? '')) ?></td>
              <td>
                <div class="stack-actions">
                  <a class="btn" href="<?= $base ?>involucrados_personas_editar.php?id=<?= (int) $r['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Editar</a>
                  <a class="btn danger" href="<?= $base ?>involucrados_personas_eliminar.php?id=<?= (int) $r['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a>
                  <?php if (isset($r['rol_nombre']) && stripos((string) $r['rol_nombre'], 'conduc') !== false): ?>
                    <a class="btn" href="marcador_manifestacion_investigado.php?involucrado_id=<?= (int) $r['id'] ?>&accidente_id=<?= (int) $r['accidente_id'] ?>&download=1">Manifestacion</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
