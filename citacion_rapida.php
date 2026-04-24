<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
require __DIR__ . '/google_calendar.php';

use App\Repositories\CitacionRepository;
use App\Services\CitacionService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$citacionRepository = new CitacionRepository($pdo);
$service = new CitacionService($citacionRepository);
$accidenteId = (int) ($_GET['accidente_id'] ?? $_POST['accidente_id'] ?? 0);
$personaSelector = trim((string) ($_GET['persona'] ?? $_POST['persona'] ?? ''));
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));

if ($returnTo === '' && $accidenteId > 0) {
    $returnTo = 'accidente_vista_tabs.php?accidente_id=' . $accidenteId;
}

$error = '';
$success = '';
$newId = null;
$context = null;
$createdId = isset($_GET['created']) ? (int) $_GET['created'] : 0;
$calendarStatus = trim((string) ($_GET['calendar'] ?? ''));
$calendarLink = trim((string) ($_GET['calendar_link'] ?? ''));
$autoDownload = $createdId > 0 && (string) ($_GET['download'] ?? '') === '1';
$accidenteResumen = $accidenteId > 0 ? $citacionRepository->accidenteResumen($accidenteId) : null;
$upcomingRows = [];
$selfParams = array_filter([
    'accidente_id' => $accidenteId > 0 ? $accidenteId : null,
    'persona' => $personaSelector !== '' ? $personaSelector : null,
    'return_to' => $returnTo !== '' ? $returnTo : null,
], static fn ($value) => $value !== null && $value !== '');
$selfUrl = 'citacion_rapida.php' . ($selfParams !== [] ? ('?' . http_build_query($selfParams)) : '');

function fmt_calendar_datetime(?string $date, ?string $time = null): string
{
    $date = trim((string) ($date ?? ''));
    $time = trim((string) ($time ?? ''));
    if ($date === '') {
        return 'Sin fecha';
    }
    $raw = $date . ' ' . ($time !== '' ? $time : '00:00:00');
    $ts = strtotime($raw);
    if ($ts === false) {
        return trim($date . ' ' . $time);
    }
    return date('d/m/Y', $ts) . ($time !== '' ? (' · ' . substr($time, 0, 5)) : '');
}

try {
    if ($accidenteId > 0 && $personaSelector !== '') {
        $context = $service->quickContext($accidenteId, $personaSelector);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'delete') {
    $deleteId = (int) ($_POST['id'] ?? 0);
    try {
        $citacion = $service->detail($deleteId);
        if ($citacion === null) {
            throw new RuntimeException('La citación ya no existe.');
        }
        if ($accidenteId > 0 && (int) ($citacion['accidente_id'] ?? 0) !== $accidenteId) {
            throw new RuntimeException('La citación no pertenece al accidente actual.');
        }

        $eventId = trim((string) ($citacion['google_calendar_event_id'] ?? ''));
        if ($eventId !== '') {
            gc_eliminar_evento_citacion($eventId);
            $success = 'La citación fue eliminada y también se quitó de Google Calendar.';
        } else {
            $success = 'La citación fue eliminada. No tenía un evento sincronizado en Google Calendar.';
        }

        $service->delete($deleteId, $accidenteId > 0 ? $accidenteId : null);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

try {
    $upcomingSql = "SELECT c.*,
                           a.registro_sidpol,
                           a.lugar AS accidente_lugar
                      FROM citacion c
                 LEFT JOIN accidentes a ON a.id = c.accidente_id
                     WHERE TIMESTAMP(c.fecha, COALESCE(c.hora, '23:59:59')) >= NOW()";
    $upcomingParams = [];
    if ($accidenteId > 0) {
        $upcomingSql .= " AND c.accidente_id = ?";
        $upcomingParams[] = $accidenteId;
    }
    $upcomingSql .= " ORDER BY c.fecha ASC, COALESCE(c.hora, '23:59:59') ASC, c.id ASC LIMIT 12";
    $upcomingStmt = $pdo->prepare($upcomingSql);
    $upcomingStmt->execute($upcomingParams);
    $upcomingRows = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $upcomingRows = [];
}

$data = $context['defaults'] ?? [
    'en_calidad' => '',
    'tipo_diligencia' => '',
    'fecha' => date('Y-m-d'),
    'hora' => '09:00',
    'lugar' => '',
    'motivo' => '',
    'orden_citacion' => 1,
    'oficio_id' => '',
];

$lugarOpciones = [
    'Lugar de los hechos',
    'Carretera Panamericana Norte km. 42 (alt. garita control SUNAT) sede de la Unidad de Investigacion de Accidentes de Transito-Lima Norte',
];

$motivoOpciones = [
    'Rendir manifestacion',
    'Visualizacion de video',
    'Imposicion de PIT',
    'Diligencia DIRCRI',
    'Entrega vehiculo',
    'Entrega enseres',
    'Entrega documentos',
];

$calendarEmbedUrl = 'https://calendar.google.com/calendar/embed?height=600&wkst=1&ctz=America%2FLima&showPrint=0&src=YTg4ZTg1MjI3NDkwZDZhMzJlNDcyMzIwMDZjMDYxZjljMDYyNmIzMmM2M2E1ZmQ2NWRkMGVlNGVkNTFlNTYwZUBncm91cC5jYWxlbmRhci5nb29nbGUuY29t&src=MjUzNmFiZGUzMWY3YjA5ZmNhMDBhMGQ4NjEwZTY0ZDM3MWMwNDBmMGQ4ZWU0YTlhZTdlMWJhMmZhY2RiNjFkYUBncm91cC5jYWxlbmRhci5nb29nbGUuY29t&src=N250Ym05aGFibG00am0wbGJpMXN2Mm83YTRAZ3JvdXAuY2FsZW5kYXIuZ29vZ2xlLmNvbQ&color=%2333b679&color=%23d50000&color=%23009688';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $context !== null) {
    $data = [
        'en_calidad' => $_POST['en_calidad'] ?? '',
        'tipo_diligencia' => $_POST['tipo_diligencia'] ?? '',
        'fecha' => $_POST['fecha'] ?? '',
        'hora' => $_POST['hora'] ?? '',
        'lugar' => $_POST['lugar'] ?? '',
        'motivo' => $_POST['motivo'] ?? '',
        'orden_citacion' => $_POST['orden_citacion'] ?? 1,
        'oficio_id' => $_POST['oficio_id'] ?? '',
    ];

    try {
        $created = $service->create($accidenteId, ['persona' => $personaSelector] + $data);
        $newId = (int) $created['id'];
        $calendarStatus = 'ok';
        $linkEvento = '';

        try {
            $calendarDetail = gc_crear_evento_citacion_detalle($service->calendarPayload($accidenteId, $newId, $created));
            $linkEvento = (string) ($calendarDetail['html_link'] ?? '');
            $service->updateCalendarSync($newId, [
                'event_id' => (string) ($calendarDetail['event_id'] ?? ''),
                'event_link' => $linkEvento !== '' ? $linkEvento : null,
                'sync_status' => 'sincronizado',
                'synced_at' => date('Y-m-d H:i:s'),
                'last_error' => null,
            ]);
            if ($linkEvento === '') {
                $calendarStatus = 'sin_link';
            }
        } catch (Throwable $calendarError) {
            $calendarStatus = 'error';
            $service->updateCalendarSync($newId, [
                'event_id' => null,
                'event_link' => null,
                'sync_status' => 'error',
                'synced_at' => null,
                'last_error' => $calendarError->getMessage(),
            ]);
            error_log('No se pudo crear evento Google Calendar para citacion rapida #' . $newId . ': ' . $calendarError->getMessage());
        }

        $redirectParams = [
            'accidente_id' => $accidenteId,
            'persona' => $personaSelector,
            'return_to' => $returnTo,
            'created' => $newId,
            'calendar' => $calendarStatus,
            'download' => 1,
        ];
        if (!empty($linkEvento)) {
            $redirectParams['calendar_link'] = $linkEvento;
        }

        header('Location: citacion_rapida.php?' . http_build_query($redirectParams));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Citacion rapida</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="style_mushu.css">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#1d4ed8;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#3b82f6;--danger:#fecaca;--ok:#bbf7d0}}
body{background:var(--page);color:var(--text)}.wrap{max-width:1040px;margin:24px auto;padding:16px}.toolbar{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);text-decoration:none;font-weight:700;cursor:pointer}.btn.primary{background:var(--primary);color:#fff;border-color:transparent}.btn.danger{color:var(--danger);border-color:rgba(220,38,38,.24)}.card{background:var(--card);border:1px solid var(--border);border-radius:18px;padding:20px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}.c3{grid-column:span 3}.c4{grid-column:span 4}.c6{grid-column:span 6}.c8{grid-column:span 8}.c12{grid-column:span 12}label{display:block;font-weight:700;color:var(--muted);margin-bottom:6px}.card input,.card select,.card textarea{width:100%;box-sizing:border-box;min-height:42px;padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);line-height:1.25}.card select{padding-right:38px;appearance:auto;-webkit-appearance:menulist}.card textarea{min-height:120px;resize:vertical}.muted{color:var(--muted);font-size:.92rem}.alert{padding:12px 14px;border-radius:12px;margin-bottom:12px}.alert.err{background:rgba(220,38,38,.12);color:var(--danger)}.alert.ok{background:rgba(22,163,74,.12);color:var(--ok)}.summary{border:1px dashed var(--border);border-radius:14px;padding:14px;background:rgba(148,163,184,.06)}.summary strong{display:block;margin-bottom:4px}.pill{display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(29,78,216,.12);color:var(--primary);font-weight:700}.actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:6px}.doc-link{display:inline-flex;margin-top:10px}@media (max-width:900px){.c3,.c4,.c6,.c8{grid-column:span 12}}
.calendar-card{margin-top:18px}.calendar-frame{border-radius:14px;overflow:hidden;border:1px solid var(--border);background:var(--card)}.calendar-frame iframe{display:block;width:100%;height:600px;border:0}
.agenda-card{margin-top:18px}
.agenda-list{display:grid;gap:10px}
.agenda-item{border:1px solid var(--border);border-radius:14px;background:rgba(148,163,184,.05);padding:14px;display:grid;gap:7px}
.agenda-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}
.agenda-title{font-size:15px;font-weight:800;line-height:1.2}
.agenda-meta{display:flex;gap:8px;flex-wrap:wrap}
.agenda-chip{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;border:1px solid var(--border);background:var(--card);font-size:11px;font-weight:700}
.agenda-chip.sync-ok{background:rgba(22,163,74,.12);color:#166534;border-color:rgba(22,163,74,.22)}
.agenda-chip.sync-off{background:rgba(148,163,184,.1);color:var(--muted)}
.agenda-chip.sync-error{background:rgba(220,38,38,.12);color:#b91c1c;border-color:rgba(220,38,38,.22)}
.agenda-sub{font-size:13px;line-height:1.35;color:var(--muted)}
.agenda-actions{display:flex;gap:8px;flex-wrap:wrap}
.agenda-empty{padding:14px;border:1px dashed var(--border);border-radius:14px;color:var(--muted);font-size:13px}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div>
      <h1 style="margin:0;">Google Calendar</h1>
      <div class="muted">
        <?php if ($accidenteId > 0): ?>
          Agenda del accidente actual y acceso rapido a sus proximas citaciones.
        <?php else: ?>
          Agenda general con proximas citaciones y acceso directo al caso.
        <?php endif; ?>
      </div>
    </div>
    <div class="actions" style="margin-top:0;">
      <?php if ($returnTo !== ''): ?><a class="btn" href="<?= h($returnTo) ?>">Volver</a><?php endif; ?>
      <?php if ($accidenteId > 0): ?><a class="btn" href="citacion_listar.php?accidente_id=<?= (int) $accidenteId ?>">Ver citaciones</a><?php endif; ?>
    </div>
  </div>

  <div class="card agenda-card">
    <h2 style="margin:0 0 8px;font-size:16px;">
      <?= $accidenteId > 0 ? 'Próximas citaciones del accidente' : 'Próximas citaciones programadas' ?>
    </h2>
    <div class="muted" style="margin-bottom:12px;">
      <?php if ($accidenteId > 0): ?>
        <?php if ($accidenteResumen !== null): ?>
          Caso <?= h((string) ($accidenteResumen['registro_sidpol'] ?? '')) ?> · <?= h((string) ($accidenteResumen['lugar'] ?? 'Sin lugar')) ?>
        <?php else: ?>
          Se muestran solo las citaciones futuras del accidente seleccionado.
        <?php endif; ?>
      <?php else: ?>
        Se muestran las citaciones futuras de todos los casos. Cada registro incluye acceso a la citación y al caso correspondiente.
      <?php endif; ?>
    </div>

    <?php if ($upcomingRows === []): ?>
      <div class="agenda-empty">No hay citaciones próximas para mostrar.</div>
    <?php else: ?>
      <div class="agenda-list">
        <?php foreach ($upcomingRows as $row): ?>
          <?php
            $personaNombre = trim((string) implode(' ', array_filter([
              trim((string) ($row['persona_nombres'] ?? '')),
              trim((string) ($row['persona_apep'] ?? '')),
              trim((string) ($row['persona_apem'] ?? '')),
            ], static fn ($part) => $part !== '')));
            $casoUrl = 'accidente_vista_tabs.php?accidente_id=' . (int) ($row['accidente_id'] ?? 0);
            $citacionUrl = 'citacion_leer.php?id=' . (int) ($row['id'] ?? 0);
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
          <article class="agenda-item">
            <div class="agenda-top">
              <div>
                <div class="agenda-title"><?= h((string) (($row['tipo_diligencia'] ?? '') !== '' ? $row['tipo_diligencia'] : 'Citación programada')) ?></div>
                <div class="agenda-sub"><?= h($personaNombre !== '' ? $personaNombre : 'Persona no especificada') ?></div>
              </div>
              <div class="agenda-meta">
                <span class="agenda-chip"><?= h(fmt_calendar_datetime($row['fecha'] ?? null, $row['hora'] ?? null)) ?></span>
                <?php if (!empty($row['en_calidad'])): ?><span class="agenda-chip"><?= h((string) $row['en_calidad']) ?></span><?php endif; ?>
                <span class="agenda-chip <?= h($syncClass) ?>"><?= h($syncLabel) ?></span>
              </div>
            </div>
            <?php if (!empty($row['lugar'])): ?><div class="agenda-sub"><strong>Lugar:</strong> <?= h((string) $row['lugar']) ?></div><?php endif; ?>
            <?php if (!empty($row['motivo'])): ?><div class="agenda-sub"><strong>Motivo:</strong> <?= h((string) $row['motivo']) ?></div><?php endif; ?>
            <?php if ($syncStatus === 'error' && !empty($row['google_calendar_last_error'])): ?><div class="agenda-sub"><strong>Detalle sync:</strong> <?= h((string) $row['google_calendar_last_error']) ?></div><?php endif; ?>
            <div class="agenda-sub">
              <strong>SIDPOL:</strong> <?= h((string) (($row['registro_sidpol'] ?? '') !== '' ? $row['registro_sidpol'] : '—')) ?>
              <?php if (!empty($row['accidente_lugar'])): ?> · <strong>Caso:</strong> <?= h((string) $row['accidente_lugar']) ?><?php endif; ?>
            </div>
            <div class="agenda-actions">
              <a class="btn" href="<?= h($citacionUrl) ?>">Ver citación</a>
              <?php if (!empty($row['accidente_id'])): ?><a class="btn" href="<?= h($casoUrl) ?>">Ir al caso</a><?php endif; ?>
              <?php if ($calendarEventLink !== ''): ?><a class="btn" href="<?= h($calendarEventLink) ?>" target="_blank" rel="noopener">Ver evento</a><?php endif; ?>
              <form method="post" onsubmit="return confirm('¿Eliminar esta citación<?= $calendarEventId !== '' ? ' y también su evento en Google Calendar' : '' ?>?');" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
                <?php if ($accidenteId > 0): ?><input type="hidden" name="accidente_id" value="<?= (int) $accidenteId ?>"><?php endif; ?>
                <?php if ($personaSelector !== ''): ?><input type="hidden" name="persona" value="<?= h($personaSelector) ?>"><?php endif; ?>
                <?php if ($returnTo !== ''): ?><input type="hidden" name="return_to" value="<?= h($returnTo) ?>"><?php endif; ?>
                <button class="btn danger" type="submit">Eliminar</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($error !== ''): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
  <?php if ($success !== ''): ?><div class="alert ok"><?= h($success) ?></div><?php endif; ?>
  <?php if ($createdId > 0): ?>
    <div class="alert <?= $calendarStatus === 'error' ? 'err' : 'ok' ?>">
      Citacion rapida registrada correctamente.
      <?php if ($calendarStatus === 'ok'): ?>
        Evento creado en Google Calendar.
      <?php elseif ($calendarStatus === 'sin_link'): ?>
        Google Calendar respondio, pero no devolvio enlace del evento.
      <?php elseif ($calendarStatus === 'error'): ?>
        No se pudo crear el evento en Google Calendar. La citacion si fue guardada.
      <?php endif; ?>
      <?php if ($calendarLink !== ''): ?>
        <a class="btn doc-link" href="<?= h($calendarLink) ?>" target="_blank" rel="noopener">Ver evento</a>
      <?php endif; ?>
      <a class="btn doc-link" href="citacion_diligencia.php?citacion_id=<?= (int) $createdId ?>" target="_blank" rel="noopener">Descargar Word</a>
    </div>
  <?php endif; ?>
  <?php if ($context !== null): ?>
    <form method="post" class="card">
      <input type="hidden" name="accidente_id" value="<?= (int) $accidenteId ?>">
      <input type="hidden" name="persona" value="<?= h($personaSelector) ?>">
      <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">

      <div class="grid">
        <div class="c12 summary">
          <span class="pill">Accidente y persona preconfigurados</span>
          <div class="grid" style="margin-top:12px;">
            <div class="c6">
              <strong>Accidente</strong>
              <div><?= h($context['accidente']['label']) ?></div>
            </div>
            <div class="c6">
              <strong>Persona citada</strong>
              <div><?= h($context['persona']['nombre']) ?></div>
            </div>
            <div class="c3">
              <strong>Fuente</strong>
              <div><?= h($context['persona']['fuente']) ?></div>
            </div>
            <div class="c3">
              <strong>Relacion</strong>
              <div><?= h($context['persona']['relacion'] !== '' ? $context['persona']['relacion'] : 'Sin relacion') ?></div>
            </div>
            <div class="c3">
              <strong>Documento</strong>
              <div><?= h($context['persona']['doc'] !== '' ? $context['persona']['doc'] : 'Sin documento') ?></div>
            </div>
            <div class="c3">
              <strong>Edad</strong>
              <div><?= $context['persona']['edad'] !== null ? (int) $context['persona']['edad'] . ' anos' : 'Sin dato' ?></div>
            </div>
            <div class="c12">
              <strong>Domicilio</strong>
              <div><?= h($context['persona']['domicilio'] !== '' ? $context['persona']['domicilio'] : 'Sin domicilio registrado') ?></div>
            </div>
          </div>
        </div>

        <div class="c4">
          <label>En calidad de*</label>
          <select name="en_calidad" required>
            <option value="">Selecciona</option>
            <?php foreach ($context['calidades'] as $calidad): ?>
              <option value="<?= h($calidad) ?>" <?= (string) $data['en_calidad'] === (string) $calidad ? 'selected' : '' ?>><?= h($service->calidadLabel((string) $calidad)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="c4">
          <label>Tipo de diligencia*</label>
          <select name="tipo_diligencia" required>
            <option value="">Selecciona</option>
            <?php foreach ($context['tipos'] as $tipo): ?>
              <option value="<?= h($tipo) ?>" <?= (string) $data['tipo_diligencia'] === (string) $tipo ? 'selected' : '' ?>><?= h($tipo) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="c4">
          <label>Oficio que ordena</label>
          <select name="oficio_id">
            <option value="">Sin oficio</option>
            <?php foreach ($context['oficios'] as $oficio): ?>
              <?php $label = 'Oficio ' . ($oficio['numero'] ?? '?') . '/' . ($oficio['anio'] ?? '?') . ' · ID ' . ($oficio['id'] ?? ''); ?>
              <option value="<?= (int) ($oficio['id'] ?? 0) ?>" <?= (string) $data['oficio_id'] === (string) ($oficio['id'] ?? '') ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="c3">
          <label>Fecha*</label>
          <input type="date" name="fecha" value="<?= h((string) $data['fecha']) ?>" required>
        </div>

        <div class="c3">
          <label>Hora*</label>
          <input type="time" name="hora" value="<?= h((string) $data['hora']) ?>" required>
        </div>

        <div class="c6">
          <label>Lugar*</label>
          <select name="lugar" required>
            <option value="">Selecciona</option>
            <?php foreach ($lugarOpciones as $lugarOpcion): ?>
              <option value="<?= h($lugarOpcion) ?>" <?= (string) $data['lugar'] === (string) $lugarOpcion ? 'selected' : '' ?>><?= h($lugarOpcion) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="c12">
          <label>Motivo / observaciones*</label>
          <div style="display:flex;gap:8px;align-items:flex-start;">
            <select id="motivo_sel" style="flex:1;">
              <option value="">Selecciona</option>
              <?php foreach ($motivoOpciones as $motivoOpcion): ?>
                <option value="<?= h($motivoOpcion) ?>"><?= h($motivoOpcion) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" id="btnMotivoOtro" class="btn" title="Escribir otro motivo">+</button>
          </div>
          <textarea id="motivo_otro" rows="4" placeholder="Escribe el motivo..." style="display:none; margin-top:8px;"></textarea>
          <input type="hidden" name="motivo" id="motivo_final" value="<?= h((string) $data['motivo']) ?>">
        </div>

        <div class="c12 actions">
          <?php if ($returnTo !== ''): ?><a class="btn" href="<?= h($returnTo) ?>">Cancelar</a><?php endif; ?>
          <button class="btn primary" type="submit">Guardar y descargar Word</button>
        </div>
      </div>
    </form>
  <?php elseif ($accidenteId > 0): ?>
    <div class="card" style="margin-top:18px;">
      <h2 style="margin:0 0 8px;font-size:16px;">Nueva citación rápida</h2>
      <div class="muted">
        Para crear una citación rápida desde este accidente, entra desde una persona del caso o abre el flujo desde la vista contextual del participante.
      </div>
    </div>
  <?php endif; ?>

  <div class="card calendar-card">
    <h2 style="margin:0 0 8px;font-size:16px;">Calendario de turnos</h2>
    <div class="muted" style="margin-bottom:12px;">Aqui puedes ver tus dias de servicio, franco y las citaciones creadas en Google Calendar.</div>
    <div class="calendar-frame">
      <iframe src="<?= h($calendarEmbedUrl) ?>" frameborder="0" scrolling="no"></iframe>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('form.card');
  const motivoSel = document.getElementById('motivo_sel');
  const motivoOtro = document.getElementById('motivo_otro');
  const motivoFinal = document.getElementById('motivo_final');
  const btnMotivo = document.getElementById('btnMotivoOtro');
  let usandoMotivoCustom = false;

  if (motivoSel && motivoOtro && motivoFinal && btnMotivo) {
    const preset = (motivoFinal.value || '').trim();
    if (preset !== '') {
      let found = false;
      for (const option of motivoSel.options) {
        if (option.value === preset) {
          found = true;
          break;
        }
      }

      if (found) {
        motivoSel.value = preset;
      } else {
        usandoMotivoCustom = true;
        motivoSel.style.display = 'none';
        motivoOtro.style.display = 'block';
        motivoOtro.value = preset;
        btnMotivo.textContent = 'x';
      }
    }

    btnMotivo.addEventListener('click', function () {
      usandoMotivoCustom = !usandoMotivoCustom;
      motivoOtro.style.display = usandoMotivoCustom ? 'block' : 'none';
      motivoSel.style.display = usandoMotivoCustom ? 'none' : 'block';
      btnMotivo.textContent = usandoMotivoCustom ? 'x' : '+';

      if (usandoMotivoCustom && motivoSel.value) {
        motivoOtro.value = motivoSel.value;
      }
    });
  }

  if (form) {
    form.addEventListener('submit', function (event) {
      if (!motivoFinal) return;
      motivoFinal.value = (usandoMotivoCustom ? motivoOtro.value : motivoSel.value).trim();
      if (!motivoFinal.value) {
        event.preventDefault();
        alert('Indica el motivo / observaciones.');
      }
    });
  }

  <?php if ($autoDownload): ?>
  const downloadFrame = document.createElement('iframe');
  downloadFrame.style.display = 'none';
  downloadFrame.src = 'citacion_diligencia.php?citacion_id=<?= (int) $createdId ?>';
  document.body.appendChild(downloadFrame);
  <?php endif; ?>
});
</script>
</body>
</html>
