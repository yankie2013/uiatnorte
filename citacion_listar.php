<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require __DIR__ . '/google_calendar.php';

use App\Repositories\CitacionRepository;
use App\Services\CitacionService;
use App\Support\CalendarAccess;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$citacionRepository = new CitacionRepository($pdo);
$service = new CitacionService($citacionRepository);
$calendarOwner = CalendarAccess::ownsConnectedCalendar();
$accidenteId = (int) ($_GET['accidente_id'] ?? 0);
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
if ($accidenteId <= 0) {
    http_response_code(400);
    exit('Falta accidente_id');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if ($id > 0) {
            $citacion = $service->detail($id);
            if ($citacion !== null) {
                $eventId = trim((string) ($citacion['google_calendar_event_id'] ?? ''));
                if ($eventId !== '' && !$calendarOwner) {
                    throw new RuntimeException('Esta citación está vinculada al calendario de otro usuario; solo su propietario puede eliminarla.');
                }
                if ($eventId !== '') {
                    gc_eliminar_evento_citacion($eventId);
                }
            }
            $service->delete($id, $accidenteId);
        }
        header('Location: citacion_listar.php?accidente_id=' . $accidenteId . ($embed ? '&embed=1' : '') . ($returnTo !== '' ? '&return_to=' . urlencode($returnTo) : ''));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'desde' => trim((string) ($_GET['desde'] ?? '')),
    'hasta' => trim((string) ($_GET['hasta'] ?? '')),
];
$rows = $service->listado($accidenteId, $filters);
$lawyers = (new App\Repositories\AbogadoRepository($pdo))->listByAccidente($accidenteId);
$groups = [];
foreach ($rows as $row) {
    $key = json_encode([$row['fecha'] ?? '', $row['hora'] ?? '', trim((string)($row['tipo_diligencia'] ?? '')), trim((string)($row['lugar'] ?? ''))]);
    if (empty($row['fecha']) || empty($row['hora'])) $key .= ':' . (int)$row['id'];
    $groups[$key][] = $row;
}
$pdfDisponible = file_exists(__DIR__ . '/citacion_diligencia_pdf.php');
if (!$embed) {
    include __DIR__ . '/sidebar.php';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Listado de citaciones</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8;--danger:#b91c1c}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#3b82f6;--danger:#fecaca}}
body{background:var(--page);color:var(--text)}.wrap{max-width:1280px;margin:24px auto;padding:0 12px}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:10px}.c12{grid-column:span 12}.c3{grid-column:span 3}.c2{grid-column:span 2}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.btn.danger{color:var(--danger)}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(29,78,216,.12);color:var(--primary);border:1px solid rgba(29,78,216,.18);font-size:11px}.small{color:var(--muted);font-size:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin:10px 0}.actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin:16px 0}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:16px;background:var(--card)}table{width:100%;border-collapse:collapse;min-width:1240px}th,td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:top;text-align:left}th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;background:rgba(148,163,184,.08)}tbody tr:hover{background:rgba(59,130,246,.05)}.stack-actions{display:flex;gap:8px;flex-wrap:wrap}.pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid var(--border);font-size:11px}.pill.sync-ok{background:rgba(22,163,74,.12);color:#166534;border-color:rgba(22,163,74,.2)}.pill.sync-off{background:rgba(148,163,184,.1);color:var(--muted)}.pill.sync-error{background:rgba(220,38,38,.12);color:var(--danger);border-color:rgba(220,38,38,.2)}.muted{color:var(--muted)}input{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text);box-sizing:border-box}body.is-embed{background:transparent}.is-embed .wrap{max-width:none;margin:0;padding:0}.is-embed .toolbar{margin-bottom:10px}.is-embed h1{font-size:22px}.is-embed .card{padding:12px;margin-bottom:10px}.is-embed table{min-width:1120px}.is-embed th,.is-embed td{padding:8px 10px}.embed-hide{display:none!important}@media(max-width:900px){.c3,.c2{grid-column:span 12}}
.wrap{max-width:1256px;margin-top:20px}.toolbar h1{font-size:20px;line-height:1.1}.toolbar .small{font-size:11px}.card{padding:12px 14px;border-radius:12px}.grid{gap:8px}.small,label.small{font-size:10.5px;font-weight:700;letter-spacing:.01em}.btn{padding:7px 11px;border-radius:8px;font-size:12px;line-height:1.15}.actions{gap:7px}.badge{padding:2px 7px;font-size:10px}.table-wrap{border-radius:12px;box-shadow:0 8px 18px rgba(15,23,42,.04)}table{min-width:1160px;font-size:12.5px;line-height:1.25}th,td{padding:8px 10px}th{font-size:10px;letter-spacing:.04em}td strong{display:block;font-size:12.5px;line-height:1.25;font-weight:800}.muted{font-size:11.5px}.pill{padding:3px 7px;font-size:10.5px;line-height:1.15}.stack-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;min-width:112px}.stack-actions .btn{justify-content:center;text-align:center;padding:6px 9px;font-size:12px}.stack-actions form{display:block!important}.stack-actions form .btn{width:100%}input{padding:8px 11px;border-radius:10px;font-size:12px}td:nth-child(1){width:46px;white-space:nowrap}td:nth-child(2){width:150px}td:nth-child(3){width:92px}td:nth-child(4){width:96px}td:nth-child(5){width:118px}td:nth-child(6),td:nth-child(7){width:68px;white-space:nowrap}td:nth-child(8){max-width:250px}td:nth-child(9){width:72px}td:nth-child(10){width:112px}td:nth-child(11){width:128px}

.diligencia-lawyers{padding:16px 20px;background:rgba(141,114,195,.06);border-top:1px solid var(--border)}.diligencia-lawyers h3{font-size:13px;color:var(--muted);margin:0 0 10px}.diligencia-lawyer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 0;flex-wrap:wrap}.diligencia-lawyer strong{font-size:13px}.diligencia-lawyer p{font-size:12px;color:var(--muted);margin:5px 0}.diligencia-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,350px),1fr));align-items:start;gap:18px}.diligencia-card{display:flex;flex-direction:column;min-width:0;background:var(--card);border:1px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:0 6px 20px #0f172a08}.diligencia-header{display:grid;grid-template-columns:auto 1fr;align-items:start;gap:14px;padding:20px;background:linear-gradient(115deg,#e8f2ff,#f4f8ff);border-bottom:1px solid var(--border);color:#193859}.diligencia-date{flex-shrink:0;min-width:96px;padding:12px;border-radius:14px;background:#fff;box-shadow:0 3px 10px #264e7910;text-align:center;color:#245a86}.diligencia-date>div{display:flex;align-items:center;justify-content:center;gap:6px}.diligencia-date strong{font-size:42px;line-height:1;font-weight:800}.diligencia-date span{font-size:13px;color:#607a91}.diligencia-date b{display:block;margin-top:6px;font-size:13px;letter-spacing:.14em}.diligencia-heading{grid-column:1/-1;grid-row:2;min-width:0}.diligencia-heading h2{font-size:18px;margin:8px 0;line-height:1.3}.diligencia-heading p{font-size:13px;line-height:1.5;margin:0;color:#526b80}.diligencia-time{font-size:13px;font-weight:700;background:#dceafa;border-radius:20px;padding:5px 10px;display:inline-block}.diligence-count{grid-column:2;grid-row:1;justify-self:end;align-self:start;white-space:nowrap;background:#fff}.citacion-person{display:grid;grid-template-columns:minmax(0,1fr);gap:12px;padding:18px 20px;border-bottom:1px solid var(--border)}.citacion-person:last-child{border-bottom:0}.citacion-person-name{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.citacion-person-name strong{font-size:14px}.citacion-person-info p{color:var(--muted);font-size:12px;margin:8px 0 0;line-height:1.5}.citacion-sync .small{font-weight:400;font-size:11px;margin:5px 0;overflow-wrap:anywhere}.citacion-sync>.small:first-child{font-weight:700;margin-top:0}.citacion-actions .stack-actions{display:flex;min-width:0;gap:6px}.citacion-actions .btn{display:inline-flex;align-items:center;min-height:30px;box-sizing:border-box;transition:background .15s,transform .15s}.citacion-actions .btn:hover{background:#e8f2ff;transform:translateY(-1px)}.citacion-actions .btn.danger:hover{background:#fff0f0}.empty-citations{grid-column:1/-1;text-align:center;padding:28px;color:var(--muted)}@media(max-width:800px){.citacion-person{grid-template-columns:minmax(0,1fr)}.citacion-actions{grid-column:1/-1}.diligencia-header{gap:12px;padding:16px}.diligence-count{display:inline-block}}@media(max-width:520px){.citacion-person{grid-template-columns:1fr;padding:16px}.diligencia-date{min-width:70px;padding:10px}.diligencia-date strong{font-size:34px}.diligencia-heading h2{font-size:15px}.diligencia-heading p{font-size:12px}}@media(prefers-color-scheme:dark){.diligencia-header{background:#172c43;color:#d4e7fa}.diligencia-date,.diligence-count{background:#203950;color:#d4e7fa}.diligencia-heading p,.diligencia-date span{color:#a9c0d5}.diligencia-time{background:#244566}.citacion-actions .btn:hover{background:#244566}}
</style>
</head>
<body class="<?= $embed ? 'is-embed' : '' ?>">
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0 0 6px">Citaciones <span class="badge">Listado</span></h1>
      <div class="small">Accidente ID: <?= (int) $accidenteId ?> · <?= count($groups) ?> diligencia(s) · <?= count($rows) ?> citación(es)</div>
    </div>
    <div class="actions" style="margin:0;">
      <?php if (!$embed): ?><a class="btn" href="<?= h($returnTo !== '' ? $returnTo : 'Dato_General_accidente.php?accidente_id=' . (int) $accidenteId) ?>">Volver al accidente</a><?php endif; ?>
      <a class="btn primary" href="citacion_nuevo.php?accidente_id=<?= (int) $accidenteId ?><?= $embed ? '&embed=1' : '' ?><?= $returnTo !== '' ? '&return_to=' . urlencode($returnTo) : '' ?>">Nueva citacion</a>
    </div>
  </div>

  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:14px;">
    <form method="get" class="grid">
      <input type="hidden" name="accidente_id" value="<?= (int) $accidenteId ?>">
      <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
      <?php if ($returnTo !== ''): ?><input type="hidden" name="return_to" value="<?= h($returnTo) ?>"><?php endif; ?>
      <div class="c3">
        <label class="small">Buscar</label>
        <input name="q" value="<?= h($filters['q']) ?>" placeholder="Nombre, documento, lugar, motivo">
      </div>
      <div class="c2">
        <label class="small">Desde</label>
        <input type="date" name="desde" value="<?= h($filters['desde']) ?>">
      </div>
      <div class="c2">
        <label class="small">Hasta</label>
        <input type="date" name="hasta" value="<?= h($filters['hasta']) ?>">
      </div>
      <div class="c3" style="display:flex;align-items:end;gap:8px;">
        <button class="btn" type="submit">Filtrar</button>
        <a class="btn" href="citacion_listar.php?accidente_id=<?= (int) $accidenteId ?><?= $embed ? '&embed=1' : '' ?><?= $returnTo !== '' ? '&return_to=' . urlencode($returnTo) : '' ?>">Limpiar</a>
      </div>
    </form>
  </div>

  <div class="diligencia-cards">
    <?php if ($rows === []): ?><div class="card empty-citations">No hay citaciones registradas con esos filtros.</div><?php endif; ?>
    <?php foreach ($groups as $group): $first = $group[0];
      $stamp = !empty($first['fecha']) ? strtotime($first['fecha']) : false;
      $months = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SET','OCT','NOV','DIC'];
    ?>
    <article class="diligencia-card">
      <header class="diligencia-header">
        <div class="diligencia-date" aria-label="<?= h($first['fecha'] ?? '') ?>">
          <div><strong><?= $stamp ? date('d',$stamp) : '—' ?></strong><span><?= $stamp ? date('Y',$stamp) : '' ?></span></div>
          <b><?= $stamp ? $months[(int)date('n',$stamp)-1] : 'Sin fecha' ?></b>
        </div>
        <div class="diligencia-heading"><span class="diligencia-time">◷ <?= h(substr((string)($first['hora'] ?? ''),0,5) ?: 'Sin hora') ?></span>
          <h2>📋 <?= h($first['tipo_diligencia'] ?: 'Diligencia') ?></h2>
          <p>📍 <?= h($first['lugar'] ?: 'Lugar sin registrar') ?></p>
        </div>
        <span class="pill diligence-count">👥 <?= count($group) ?> citado<?= count($group) === 1 ? '' : 's' ?></span>
      </header>
      <div class="diligencia-participants">
        <?php foreach ($group as $row): ?>
          <?php
            $nombre = trim((string) (($row['persona_nombres'] ?? '') . ' ' . ($row['persona_apep'] ?? '') . ' ' . ($row['persona_apem'] ?? '')));
            $documento = trim((string) (($row['persona_doc_tipo'] ?? '') . ' ' . ($row['persona_doc_num'] ?? '')));
            $oficio = (!empty($row['oficio_num']) && !empty($row['oficio_anio']))
                ? ((string) $row['oficio_num'] . '/' . (string) $row['oficio_anio'])
                : 'Sin oficio';
            $calendarEventLink = trim((string) ($row['google_calendar_event_link'] ?? ''));
            $calendarEventId = trim((string) ($row['google_calendar_event_id'] ?? ''));
            $syncStatus = trim((string) ($row['google_calendar_sync_status'] ?? ''));
            $syncClass = 'sync-off';
            $syncLabel = 'No sincronizada';
            if ($calendarEventId !== '' && $syncStatus !== 'error') {
                $syncClass = 'sync-ok';
                $syncLabel = 'Sincronizada';
            } elseif ($syncStatus === 'error') {
                $syncClass = 'sync-error';
                $syncLabel = 'Error de sincronización';
            }
          ?>

        <section class="citacion-person">
          <div class="citacion-person-info"><div class="citacion-person-name">👤 <strong><?= h($nombre ?: 'Sin nombre') ?></strong><span class="pill"><?= h($row['en_calidad'] ?? '') ?></span></div>
            <p><?= h($documento ?: 'Sin documento') ?> · Citación #<?= (int)$row['id'] ?> · Orden <?= (int)($row['orden_citacion'] ?? 0) ?></p>
            <p>📄 <?= h($oficio) ?></p>
          </div>
          <div class="citacion-sync"><div class="small">Google Calendar</div>
              <span class="pill <?= h($syncClass) ?>"><?= h($syncLabel) ?></span>
              <?php if (!empty($row['google_calendar_synced_at'])): ?>
                <div class="small">Sync: <?= h((string) $row['google_calendar_synced_at']) ?></div>
              <?php endif; ?>
              <?php if ($syncStatus === 'error' && !empty($row['google_calendar_last_error'])): ?>
                <div class="small" style="color:var(--danger);"><?= h((string) $row['google_calendar_last_error']) ?></div>
              <?php endif; ?>
              <?php if ($calendarOwner && $calendarEventLink !== ''): ?>
                <div class="small"><a href="<?= h($calendarEventLink) ?>" target="_blank" rel="noopener">Ver evento</a></div>
              <?php endif; ?>
          </div>
          <div class="citacion-actions">
              <div class="stack-actions">
                <a class="btn" href="citacion_leer.php?id=<?= (int) $row['id'] ?>&return=<?= urlencode('citacion_listar.php?accidente_id=' . $accidenteId) ?>">Ver</a>
                <a class="btn" href="citacion_editar.php?id=<?= (int) $row['id'] ?>&return=<?= urlencode('citacion_listar.php?accidente_id=' . $accidenteId) ?>">Editar</a>
                <a class="btn" href="citacion_diligencia.php?citacion_id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">DOCX</a>
                <?php if ($pdfDisponible): ?><a class="btn" href="citacion_diligencia_pdf.php?citacion_id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener">PDF</a><?php endif; ?>
                <?php if ($calendarEventId !== '' && !$calendarOwner): ?>
                  <span class="muted">Evento en calendario de otro usuario</span>
                <?php else: ?>
                <form method="post" onsubmit="return confirm('Eliminar esta citacion<?= $calendarEventId !== '' ? ' y tambien su evento en Google Calendar' : '' ?>?');" style="display:inline;">
                  <input type="hidden" name="action" value="delete">
                  <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                  <?php if ($returnTo !== ''): ?><input type="hidden" name="return_to" value="<?= h($returnTo) ?>"><?php endif; ?>
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <button class="btn danger" type="submit">Eliminar</button>
                </form>
                <?php endif; ?>
              </div>
          </div>
        </section>
        <?php endforeach; ?>
      </div>
      <?php
        $personIds = array_filter(array_column($group, 'persona_id'));
        $groupLawyers = array_filter($lawyers, static fn($lawyer) => in_array($lawyer['persona_id'], $personIds));
      ?>
      <div class="diligencia-lawyers"><h3>⚖️ Abogados vinculados a los citados</h3>
        <?php if (!$groupLawyers): ?><p class="muted">Sin abogados vinculados registrados.</p><?php endif; ?>
        <?php foreach ($groupLawyers as $lawyer): ?>
          <div class="diligencia-lawyer"><div><strong><?= h(trim($lawyer['nombres'].' '.$lawyer['apellido_paterno'].' '.$lawyer['apellido_materno'])) ?></strong><p>Representa a <?= h($lawyer['persona_rep_nom']) ?><?= $lawyer['registro'] ? ' · '.h($lawyer['registro']) : '' ?></p></div>
            <a class="btn" href="marcador_abogado.php?abogado_id=<?= (int)$lawyer['id'] ?>&return_to=<?= urlencode('citacion_listar.php?accidente_id='.$accidenteId) ?>">📄 Notificación</a>
          </div>
        <?php endforeach; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
