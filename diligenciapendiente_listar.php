<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\DiligenciaPendienteRepository;
use App\Services\DiligenciaPendienteService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new DiligenciaPendienteService(new DiligenciaPendienteRepository($pdo));
$filters = [
    'accidente_id' => $_GET['accidente_id'] ?? '',
    'estado' => trim((string) ($_GET['estado'] ?? '')),
    'tipo' => (int) ($_GET['tipo'] ?? 0),
    'q' => trim((string) ($_GET['q'] ?? '')),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$message = trim((string) ($_GET['msg'] ?? ''));

function url_with_params(array $overrides): string
{
    $query = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    $qs = http_build_query($query);
    return basename(__FILE__) . ($qs !== '' ? ('?' . $qs) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $service->cambiarEstado((int) ($_POST['id'] ?? 0), (string) ($_POST['estado'] ?? ''));
        echo json_encode(['ok' => true, 'msg' => 'Estado actualizado.']);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $service->eliminar((int) ($_POST['id'] ?? 0));
        $target = url_with_params(['msg' => 'Diligencia eliminada correctamente.', 'page' => 1]);
        header('Location: ' . $target);
        exit;
    } catch (Throwable $e) {
        $message = $e->getMessage();
    }
}

$ctx = $service->listado($filters, $page, $perPage);
$rows = $ctx['rows'];
$total = (int) $ctx['total'];
$totalPages = max(1, (int) ceil($total / $perPage));
$accidenteId = (int) ($filters['accidente_id'] ?: 0);
$newUrl = 'diligenciapendiente_nuevo.php' . ($accidenteId > 0 ? ('?accidente_id=' . urlencode((string) $accidenteId)) : '');
@include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Listado de diligencias</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--bg:#f6f7fb;--card:#fff;--text:#111827;--muted:#6b7280;--accent:#1d4ed8;--border:#d9e0ea;--danger:#b91c1c}
@media (prefers-color-scheme: dark){:root{--bg:#0b1220;--card:#111827;--text:#e5e7eb;--muted:#9ca3af;--accent:#60a5fa;--border:#243041;--danger:#fecaca}}
body{margin:0;padding:24px;background:var(--bg);color:var(--text);font-family:"Segoe UI",sans-serif}.container{max-width:1120px;margin:0 auto}.header{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px}.title{margin:0;font-size:1.7rem}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px;box-shadow:0 12px 32px rgba(0,0,0,.08)}.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}.input,select{padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text)}.btn{display:inline-block;padding:10px 14px;border-radius:10px;text-decoration:none;border:1px solid var(--border);background:transparent;color:var(--text);font-weight:600;cursor:pointer}.btn.primary{background:var(--accent);color:#fff;border-color:transparent}.alert{padding:12px 14px;border-radius:12px;margin-bottom:14px;background:rgba(29,78,216,.12);color:var(--text)}table{width:100%;border-collapse:collapse}th,td{padding:12px;border-bottom:1px solid var(--border);vertical-align:top}th{text-align:left;color:var(--muted);font-size:.9rem}.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(29,78,216,.12);color:var(--accent);font-weight:700}.small{font-size:.9rem;color:var(--muted)}.actions{display:flex;gap:8px;flex-wrap:wrap}.pager{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:14px;flex-wrap:wrap}.content{white-space:pre-wrap;line-height:1.35}.estado{min-width:140px}.danger{color:var(--danger)}
@media (max-width:760px){body{padding:14px}table,thead,tbody,tr,td,th{display:block}thead{display:none}td{padding:10px 0}td::before{content:attr(data-label);display:block;color:var(--muted);font-size:.84rem;margin-bottom:4px}}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div>
      <h1 class="title">Listado de diligencias</h1>
      <div class="small">Consulta, filtra y actualiza el estado de las diligencias pendientes.</div>
    </div>
    <div class="actions">
      <a class="btn primary" href="<?= h($newUrl) ?>">+ Nueva diligencia</a>
      <?php if ($accidenteId > 0): ?>
        <a class="btn" href="Dato_General_accidente.php?accidente_id=<?= h($accidenteId) ?>">Volver al accidente</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <?php if ($message !== ''): ?><div class="alert"><?= h($message) ?></div><?php endif; ?>

    <form method="get" class="filters">
      <?php if ($accidenteId > 0): ?><input type="hidden" name="accidente_id" value="<?= h($accidenteId) ?>"><?php endif; ?>
      <input class="input" type="text" name="q" value="<?= h($filters['q']) ?>" placeholder="Buscar contenido, documentos o tipo..." style="min-width:240px;">
      <select name="tipo" class="input">
        <option value="0">Tipo de diligencia</option>
        <?php foreach ($ctx['tipos'] as $tipo): ?>
          <option value="<?= h($tipo['id']) ?>" <?= (int) $filters['tipo'] === (int) $tipo['id'] ? 'selected' : '' ?>><?= h($tipo['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="estado" class="input">
        <option value="">Estado</option>
        <?php foreach ($ctx['estados'] as $estado): ?>
          <option value="<?= h($estado) ?>" <?= $filters['estado'] === $estado ? 'selected' : '' ?>><?= h($estado) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn">Filtrar</button>
      <a class="btn" href="<?= h(url_with_params(['q' => null, 'tipo' => null, 'estado' => null, 'page' => 1, 'msg' => null])) ?>">Limpiar</a>
    </form>

    <div class="small" style="margin-bottom:10px;">Mostrando <?= count($rows) ?> de <?= h($total) ?> registro(s).</div>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Tipo</th>
          <th>Contenido</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5" class="small">No se encontraron diligencias con los filtros aplicados.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
          <?php
            $citaciones = [];
            if (!empty($row['citacion_ids'])) {
                $decoded = json_decode((string) $row['citacion_ids'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $citId) {
                        $citId = (int) $citId;
                        if ($citId > 0) {
                            $citaciones[] = $citId;
                        }
                    }
                }
            }
            if ($citaciones === [] && !empty($row['citacion_id'])) {
                $citaciones[] = (int) $row['citacion_id'];
            }
          ?>
          <tr>
            <td data-label="#"><strong><?= h($row['id']) ?></strong><div class="small">Acc. <?= h($row['accidente_id'] ?? '-') ?></div></td>
            <td data-label="Tipo"><?= !empty($row['tipo_nombre']) ? ('<span class="badge">' . h($row['tipo_nombre']) . '</span>') : '<span class="small">Sin tipo</span>' ?></td>
            <td data-label="Contenido">
              <div class="content"><?= h($row['contenido'] ?? '') ?></div>
              <?php if (!empty($row['documento_realizado'])): ?><div class="small" style="margin-top:8px;">Documento realizado: <?= h($row['documento_realizado']) ?></div><?php endif; ?>
              <?php if (!empty($row['documentos_recibidos'])): ?><div class="small" style="margin-top:4px;">Documentos recibidos: <?= h(mb_strimwidth((string) $row['documentos_recibidos'], 0, 140, '...')) ?></div><?php endif; ?>
              <?php if (!empty($row['oficio_id'])): ?><div class="small" style="margin-top:4px;">Oficio: <?= h($ctx['oficios_labels'][(int) $row['oficio_id']] ?? ('Oficio #' . $row['oficio_id'])) ?></div><?php endif; ?>
              <?php if ($citaciones): ?>
                <div class="small" style="margin-top:4px;">Citaciones:
                  <?= h(implode(', ', array_map(static fn ($citId) => $ctx['citaciones_labels'][$citId] ?? ('Citación #' . $citId), $citaciones))) ?>
                </div>
              <?php endif; ?>
            </td>
            <td data-label="Estado" class="estado">
              <select class="input js-estado" data-id="<?= h($row['id']) ?>">
                <?php foreach ($ctx['estados'] as $estado): ?>
                  <option value="<?= h($estado) ?>" <?= (string) ($row['estado'] ?? 'Pendiente') === $estado ? 'selected' : '' ?>><?= h($estado) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td data-label="Acciones">
              <div class="actions">
                <a class="btn" href="diligenciapendiente_ver.php?id=<?= h($row['id']) ?>">Ver</a>
                <a class="btn" href="diligenciapendiente_editar.php?id=<?= h($row['id']) ?>">Editar</a>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= h($row['id']) ?>">
                  <button type="submit" class="btn danger" onclick="return confirm('¿Eliminar diligencia #<?= h($row['id']) ?>?');">Eliminar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="pager">
      <div class="small">Página <?= h($page) ?> de <?= h($totalPages) ?></div>
      <div class="actions">
        <?php if ($page > 1): ?>
          <a class="btn" href="<?= h(url_with_params(['page' => 1, 'msg' => null])) ?>">Primera</a>
          <a class="btn" href="<?= h(url_with_params(['page' => $page - 1, 'msg' => null])) ?>">Anterior</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a class="btn" href="<?= h(url_with_params(['page' => $page + 1, 'msg' => null])) ?>">Siguiente</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.js-estado').forEach(function (select) {
  select.addEventListener('change', function () {
    const current = this;
    const previous = current.dataset.prev || current.value;
    current.disabled = true;

    fetch(window.location.pathname + window.location.search, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body: new URLSearchParams({
        action: 'update_status',
        id: current.dataset.id,
        estado: current.value
      })
    })
      .then(async function (response) {
        const data = await response.json();
        if (!response.ok || !data.ok) {
          throw new Error(data.msg || 'No se pudo actualizar el estado.');
        }
        current.dataset.prev = current.value;
      })
      .catch(function (error) {
        alert(error.message || 'No se pudo actualizar el estado.');
        current.value = previous;
      })
      .finally(function () {
        current.disabled = false;
      });
  });
  select.dataset.prev = select.value;
});
</script>
</body>
</html>
