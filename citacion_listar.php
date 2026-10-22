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
$canChangeState = in_array(App\Support\Access::role(), ['admin', 'jefe_emi', 'adjunto'], true)
    && App\Support\Access::canEdit($accidenteId);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_state') {
    try {
        App\Support\Access::checkCsrf();
        if (!$canChangeState) {
            throw new RuntimeException('No tienes permiso para cambiar el estado de este expediente.');
        }
        $citacionRepository->updateOutcome($accidenteId, (array) ($_POST['citacion_ids'] ?? []), (string) ($_POST['estado'] ?? ''));
        $query = ['accidente_id' => $accidenteId, 'embed' => $embed ? 1 : 0, 'return_to' => $returnTo, 'estado_guardado' => 1];
        header('Location: citacion_listar.php?' . http_build_query($query), true, 303);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    try {
        App\Support\Access::checkCsrf();
        if (!App\Support\Access::canDeleteCaseRecord($accidenteId)) {
            throw new RuntimeException('Solo el administrador o el JEFE EMI encargado del expediente puede eliminar esta citación.');
        }
        if ($id > 0) {
            $citacion = $service->detail($id);
            if ($citacion !== null) {
                if ((int) $citacion['accidente_id'] !== $accidenteId) {
                    throw new RuntimeException('La citación no pertenece a este expediente.');
                }
                $eventId = trim((string) ($citacion['google_calendar_event_id'] ?? ''));
                if ($eventId !== '' && $calendarOwner) {
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

$rows = $service->listado($accidenteId, []);
$lawyers = (new App\Repositories\AbogadoRepository($pdo))->listByAccidente($accidenteId);
$contactPhones = [];
$personIds = array_values(array_unique(array_filter(array_column($rows, 'persona_id'))));
if ($personIds) {
    $contactStmt = $pdo->prepare('SELECT id, celular FROM personas WHERE id IN (' . implode(',', array_fill(0, count($personIds), '?')) . ')');
    $contactStmt->execute($personIds);
    $contactPhones = $contactStmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
$accidentStmt = $pdo->prepare('SELECT fecha_accidente, lugar, registro_sidpol FROM accidentes_activos WHERE id = ?');
$accidentStmt->execute([$accidenteId]);
$accidentContext = $accidentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$modality = (new App\Repositories\DocumentoPlantillaRepository($pdo))->accidenteModalidad($accidenteId);
$waMessage = App\Support\CitacionWhatsApp::message(App\Support\Access::greeting($accidenteId), $accidentContext, $modality);
// Resolve outcomes from linked diligencias, including installations without citacion_ids.
$linkedStates = [];
$st = $pdo->prepare('SELECT * FROM diligencias_pendientes_activos WHERE accidente_id = ? ORDER BY actualizado_en DESC, id DESC');
$st->execute([$accidenteId]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $diligencia) {
    $ids = json_decode((string) ($diligencia['citacion_ids'] ?? ''), true);
    if (!is_array($ids) || $ids === []) {
        $ids = [$diligencia['citacion_id'] ?? 0];
    }
    foreach ($ids as $id) {
        if ((int) $id > 0 && !isset($linkedStates[(int) $id])) {
            $linkedStates[(int) $id] = (string) ($diligencia['estado'] ?? 'Pendiente');
        }
    }
}
$notificationStmt = $pdo->prepare('SELECT n.* FROM citacion_notificacion_abogado n JOIN citacion_activos c ON c.id = n.citacion_id WHERE c.accidente_id = ?');
$notificationStmt->execute([$accidenteId]);
$lawyerNotifications = [];
foreach ($notificationStmt->fetchAll(PDO::FETCH_ASSOC) as $notification) {
    $lawyerNotifications[(int) $notification['abogado_id']][(int) $notification['citacion_id']] = $notification;
}
$now = new DateTimeImmutable('now', new DateTimeZone('America/Lima'));
foreach ($rows as &$row) {
    $state = $linkedStates[(int) $row['id']] ?? 'Pendiente';
    $scheduled = !empty($row['fecha'])
        ? new DateTimeImmutable($row['fecha'] . ' ' . ($row['hora'] ?: '23:59:59'), new DateTimeZone('America/Lima'))
        : null;
    $row['outcome'] = in_array($row['estado_realizacion'] ?? null, ['realizada', 'suspendida', 'reprogramada', 'no-realizada', 'pendiente'], true)
        ? $row['estado_realizacion'] : ($state === 'Realizado' ? 'realizada'
        : (($state === 'Cancelado' || ($scheduled !== null && $scheduled < $now)) ? 'no-realizada' : 'pendiente'));
}
unset($row);
$groups = [];
foreach ($rows as $row) {
    $key = json_encode([$row['fecha'] ?? '', $row['hora'] ?? '', App\Support\CitacionMotivo::texto($row), trim((string)($row['lugar'] ?? '')), $row['outcome']]);
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

.diligencia-lawyers{padding:14px 16px;background:rgba(141,114,195,.06);border-top:1px solid var(--border)}.diligencia-lawyers h3{font-size:13px;color:var(--muted);margin:0 0 10px}.diligencia-lawyer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 0;flex-wrap:wrap}.diligencia-lawyer strong{font-size:13px}.diligencia-lawyer p{font-size:12px;color:var(--muted);margin:5px 0}.diligencia-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,350px),1fr));align-items:start;gap:18px}.diligencia-card{display:flex;flex-direction:column;min-width:0;background:var(--card);border:1px solid #d7c5f5;border-left:5px solid #a466f4;border-radius:16px;overflow:hidden;box-shadow:0 6px 20px #0f172a08}.diligencia-header{display:grid;grid-template-columns:auto 1fr;align-items:start;gap:12px;padding:16px;background:linear-gradient(115deg,#f7f2ff,#f5f9ff);border-bottom:1px solid var(--border);color:#193859}.diligencia-date{flex-shrink:0;min-width:76px;padding:10px;border-radius:12px;background:#fff;box-shadow:0 3px 10px #264e7910;text-align:center;color:#245a86}.diligencia-date>div{display:flex;align-items:center;justify-content:center;gap:6px}.diligencia-date strong{font-size:42px;line-height:1;font-weight:800}.diligencia-date span{font-size:13px;color:#607a91}.diligencia-date b{display:block;margin-top:6px;font-size:13px;letter-spacing:.14em}.diligencia-heading{grid-column:1/-1;grid-row:2;min-width:0}.diligencia-heading h2{font-size:16px;margin:8px 0;line-height:1.3}.diligencia-heading p{font-size:13px;line-height:1.5;margin:0;color:#526b80}.diligencia-time{font-size:13px;font-weight:700;background:#dceafa;border-radius:20px;padding:5px 10px;display:inline-block}.diligence-count{grid-column:2;grid-row:1;justify-self:end;align-self:start;white-space:nowrap;background:#fff}.citacion-person{display:grid;grid-template-columns:minmax(0,1fr);gap:10px;padding:16px;border-bottom:1px solid var(--border)}.citacion-person:last-child{border-bottom:0}.citacion-person-name{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.citacion-person-name strong{font-size:14px}.citacion-person-info p{color:var(--muted);font-size:12px;margin:8px 0 0;line-height:1.5}.citacion-sync .small{font-weight:400;font-size:11px;margin:5px 0;overflow-wrap:anywhere}.citacion-sync>.small:first-child{font-weight:700;margin-top:0}.citacion-actions .stack-actions{display:flex;min-width:0;gap:6px}.citacion-actions .btn{display:inline-flex;align-items:center;min-height:30px;box-sizing:border-box;transition:background .15s,transform .15s}.citacion-actions .btn:hover{background:#e8f2ff;transform:translateY(-1px)}.citacion-actions .btn.danger:hover{background:#fff0f0}.empty-citations{grid-column:1/-1;text-align:center;padding:28px;color:var(--muted)}@media(max-width:800px){.citacion-person{grid-template-columns:minmax(0,1fr)}.citacion-actions{grid-column:1/-1}.diligencia-header{gap:12px;padding:16px}.diligence-count{display:inline-block}}@media(max-width:520px){.citacion-person{grid-template-columns:1fr;padding:16px}.diligencia-date{min-width:70px;padding:10px}.diligencia-date strong{font-size:34px}.diligencia-heading h2{font-size:15px}.diligencia-heading p{font-size:12px}}@media(prefers-color-scheme:dark){.diligencia-header{background:#172c43;color:#d4e7fa}.diligencia-date,.diligence-count{background:#203950;color:#d4e7fa}.diligencia-heading p,.diligencia-date span{color:#a9c0d5}.diligencia-time{background:#244566}.citacion-actions .btn:hover{background:#244566}}
.diligencia-card.estado-realizada{--outcome-color:#16a34a}
.diligencia-card.estado-no-realizada{--outcome-color:#dc2626}
.diligencia-card.estado-pendiente{--outcome-color:#f97316}
.diligencia-card[class*="estado-"]{border-color:var(--outcome-color);border-left-color:var(--outcome-color)}
.outcome-label{border-color:var(--outcome-color);color:var(--text);background:var(--card);margin-bottom:6px}
.citation-state-form{grid-column:1/-1;grid-row:3;min-width:0;margin:0}
.citation-state-form label{display:block;font-size:12px;font-weight:700;margin-bottom:6px;color:var(--text)}
.citation-state-form>div{display:flex;gap:8px;flex-wrap:wrap}
.citation-state-form select{flex:1;min-width:150px;padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text)}

/* Tarjetas de agenda: jerarquía clara y acciones alineadas. */
.toolbar{margin-bottom:20px;gap:16px}
.diligencia-cards{grid-template-columns:repeat(auto-fill,minmax(min(100%,440px),520px));gap:18px}
.diligencia-card[class*="estado-"]{border:1px solid var(--border);border-radius:18px;box-shadow:0 4px 20px #0f172a06;background:var(--card)}
.diligencia-header{display:grid;grid-template-columns:56px minmax(0,1fr);gap:12px;align-items:start;padding:18px;background:var(--card);color:var(--text)}
.diligencia-header .diligencia-date{grid-column:1;grid-row:1;min-width:0;padding:10px 6px;border-radius:12px;background:#eef4ff;box-shadow:none;color:#245a86}
.diligencia-header .diligencia-date>div{flex-direction:column;gap:4px}
.diligencia-header .diligencia-date strong{font-size:30px;letter-spacing:-1px}
.diligencia-header .diligencia-date span{font-size:10px}
.diligencia-header .diligencia-date b{font-size:10px;margin-top:6px;letter-spacing:.12em}
.diligencia-header .diligencia-heading{grid-column:2;grid-row:1}
.diligencia-header .diligencia-heading h2{font-size:15px;line-height:1.4;margin:7px 0 5px;letter-spacing:-.2px}
.diligencia-header .diligencia-heading p{font-size:12px;line-height:1.5;color:var(--muted);overflow-wrap:anywhere}
.diligencia-time{font-size:12px;padding:4px 9px;background:#eef4ff;color:#245a86}
.diligencia-header .add-participant{grid-column:2;grid-row:2;justify-self:start;white-space:nowrap;font-size:11px;padding:7px 10px;background:transparent;color:var(--primary);border-color:var(--border);border-radius:9px}
.diligencia-header .citation-state-form{grid-column:1/-1;grid-row:3;margin:2px 0 0;padding-top:12px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.citation-state-form label{font-size:11px;color:var(--muted);margin:0;font-weight:600}
.citation-state-form>div{gap:6px;flex:1;max-width:290px}
.citation-state-form select{min-width:120px;font-size:12px;padding:8px 10px}
.citation-state-form .btn{font-size:11px;padding:8px 12px;border-radius:8px}
.citacion-person{padding:16px 18px;gap:12px}
.citacion-person-name{gap:8px}
.citacion-person-name strong{font-size:14px;line-height:1.5}
.citacion-person-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.citacion-person-meta .pill{font-size:10px;padding:3px 8px;background:#f4f6fa;border-color:transparent;color:#52647e}
.citacion-actions .stack-actions{gap:7px}
.citacion-actions .btn{font-size:11px;padding:7px 11px;border-radius:8px;min-height:32px}
.citacion-actions .btn.danger{margin-left:auto;border-color:transparent}
.diligencia-lawyers{padding:14px 18px;background:transparent}
.diligencia-lawyers h3{font-size:12px;margin:0 0 10px}
@media(max-width:640px){
 .diligencia-header{padding:16px;gap:12px}
 .citation-state-form>div{max-width:none;flex-basis:100%}
 .citacion-person{padding:16px}
}
@media(prefers-color-scheme:dark){
 .diligencia-header .diligencia-date,.diligencia-time{background:#203950;color:#d4e7fa}
 .citacion-person-meta .pill{background:#203950;color:#d4e7fa}
}
/* Estado compacto en la parte superior de cada tarjeta. */
.diligencia-header .citation-state-form,.citation-state-chip{margin:0;padding:0;border:0;display:block;flex:0 0 auto}
.citation-state-form label{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
.citation-state-form>div{display:flex;max-width:none}
.citation-state-form select,.citation-state-chip{width:auto;min-width:0;border-radius:999px;padding:3px 7px;font-size:10px;font-weight:700;border:1px solid var(--chip-border);background:var(--chip-bg);color:var(--chip-text);cursor:pointer}
.diligencia-card{--chip-bg:#fff7ed;--chip-border:#fed7aa;--chip-text:#9a3412}
.estado-realizada{--chip-bg:#f0fdf4;--chip-border:#bbf7d0;--chip-text:#166534}
.estado-suspendida,.estado-no-realizada{--chip-bg:#fef2f2;--chip-border:#fecaca;--chip-text:#991b1b}
.estado-reprogramada{--chip-bg:#eff6ff;--chip-border:#bfdbfe;--chip-text:#1e40af}
.citation-topline{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:nowrap}
.citation-topline .diligencia-time{white-space:nowrap;flex:0 0 auto}
.diligencia-header .diligencia-date,.diligencia-header .diligencia-heading{grid-row:1}
.diligencia-header .add-participant{grid-row:2}
.calendar-sync-chip{display:inline-flex;align-items:center;flex:0 0 auto;padding:2px 6px;border-radius:999px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-size:9px;font-weight:700;line-height:1.3;letter-spacing:.03em}
@media(prefers-color-scheme:dark){.calendar-sync-chip{background:#143426;border-color:#256443;color:#86efac}}
.lawyer-notified{font-size:11px;font-weight:600;color:#15803d;white-space:nowrap}
/* Acciones compactas junto al nombre del citado. */
.citacion-person{grid-template-columns:minmax(0,1fr) auto;align-items:start;column-gap:8px;row-gap:0}
.citacion-person-info{display:contents}
.citacion-person-name{grid-column:1;grid-row:1;min-width:0;gap:5px;flex-wrap:nowrap;align-self:center}
.citacion-person-name strong{min-width:0;overflow-wrap:anywhere;font-size:12px}
.citacion-person-meta{grid-column:1/-1;grid-row:2}
.citacion-actions{grid-column:2;grid-row:1;align-self:center}
.citacion-actions .stack-actions{gap:5px;flex-wrap:nowrap;align-items:center}
.citacion-actions .btn{display:inline-flex;justify-content:center;align-items:center;width:28px;height:28px;min-height:28px;padding:0;border:1px solid var(--border);border-radius:7px;font-size:13px;line-height:1;background:var(--card);box-sizing:border-box;flex:0 0 28px}
.citacion-actions .btn.danger{margin:0;font-size:12px;border-color:var(--border)}
.citacion-actions .stack-actions form{margin:0;line-height:0}
.citacion-actions .btn:hover{transform:none;border-color:var(--primary)}
.citacion-actions .btn.danger:hover{border-color:var(--danger)}
.citacion-actions .muted{font-size:10px;max-width:85px}
.citacion-actions .btn.whatsapp{color:#178248;border-color:#9fe0b7;background:#f0fff5}
.citacion-actions .btn.whatsapp:hover{background:#dcfce7;border-color:#25d366}
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

  <?php if (isset($_GET['estado_guardado'])): ?><div class="card" role="status" style="margin-bottom:14px">Estado de la citación actualizado correctamente.</div><?php endif; ?>
  <div class="diligencia-cards">
    <?php if ($rows === []): ?><div class="card empty-citations">No hay citaciones registradas.</div><?php endif; ?>
    <?php foreach ($groups as $group): $first = $group[0];
      $stamp = !empty($first['fecha']) ? strtotime($first['fecha']) : false;
      $months = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SET','OCT','NOV','DIC'];
    ?>
    <article class="diligencia-card estado-<?= h($first['outcome']) ?>">
      <header class="diligencia-header">
        <div class="diligencia-date" aria-label="<?= h($first['fecha'] ?? '') ?>">
          <div><strong><?= $stamp ? date('d',$stamp) : '—' ?></strong><span><?= $stamp ? date('Y',$stamp) : '' ?></span></div>
          <b><?= $stamp ? $months[(int)date('n',$stamp)-1] : 'Sin fecha' ?></b>
        </div>
        <div class="diligencia-heading"><div class="citation-topline"><span class="diligencia-time">◷ <?= h(substr((string)($first['hora'] ?? ''),0,5) ?: 'Sin hora') ?></span>
        <?php if ($canChangeState): ?>
        <form method="post" class="citation-state-form">
          <input type="hidden" name="_csrf" value="<?= h(App\Support\Access::csrf()) ?>">
          <input type="hidden" name="action" value="update_state">
          <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
          <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
          <?php foreach ($group as $participant): ?><input type="hidden" name="citacion_ids[]" value="<?= (int) $participant['id'] ?>"><?php endforeach; ?>
          <label for="estado-citacion-<?= (int) $first['id'] ?>">Estado de la citación</label>
          <div><select aria-label="Estado de la citación" onchange="this.form.requestSubmit()" id="estado-citacion-<?= (int) $first['id'] ?>" name="estado">
            <?php foreach (['pendiente' => 'Pendiente', 'realizada' => 'Realizada', 'suspendida' => 'Suspendida', 'reprogramada' => 'Reprogramada'] + ($first['outcome'] === 'no-realizada' ? ['no-realizada' => 'No realizada'] : []) as $value => $label): ?>
              <option value="<?= h($value) ?>" <?= $first['outcome'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select><noscript><button class="btn primary" type="submit">Guardar</button></noscript></div>
        </form>
        <?php else: ?>
        <span class="citation-state-chip"><?= h(['pendiente' => 'Pendiente', 'realizada' => 'Realizada', 'suspendida' => 'Suspendida', 'reprogramada' => 'Reprogramada', 'no-realizada' => 'No realizada'][$first['outcome']]) ?></span>
        <?php endif; ?>
        </div>
          <h2><?= h(App\Support\CitacionMotivo::texto($first) ?: 'Motivo sin registrar') ?></h2>
          <p>📍 <?= h($first['lugar'] ?: 'Lugar sin registrar') ?></p>
        </div>
        <a class="btn add-participant" href="citacion_nuevo.php?accidente_id=<?= $accidenteId ?>&base_citacion_id=<?= (int) $first['id'] ?><?= $embed ? '&embed=1' : '' ?>&return_to=<?= urlencode($returnTo) ?>">+ Agregar participante</a>
      </header>
      <div class="diligencia-participants">
        <?php foreach ($group as $row): ?>
          <?php
            $nombre = trim((string) (($row['persona_nombres'] ?? '') . ' ' . ($row['persona_apep'] ?? '') . ' ' . ($row['persona_apem'] ?? '')));
            $documento = trim((string) (($row['persona_doc_tipo'] ?? '') . ' ' . ($row['persona_doc_num'] ?? '')));
            $calendarEventId = trim((string) ($row['google_calendar_event_id'] ?? ''));
          ?>

        <section class="citacion-person">
          <div class="citacion-person-info"><div class="citacion-person-name">👤 <strong><?= h($nombre ?: 'Sin nombre') ?></strong><?php if ($calendarEventId !== '' && ($row['google_calendar_sync_status'] ?? '') === 'sincronizado'): ?><span class="calendar-sync-chip" title="Sincronizado con Google Calendar" aria-label="Sincronizado con Google Calendar">SYNC</span><?php endif; ?></div>
            <p class="citacion-person-meta"><span><?= h($documento ?: 'Sin documento') ?></span><span class="pill"><?= h($row['en_calidad'] ?? '') ?></span></p>
          </div>
          <div class="citacion-actions">
              <div class="stack-actions">
                <a class="btn whatsapp whatsapp-citacion" href="<?= h(App\Support\CitacionWhatsApp::link((string) ($contactPhones[(int) ($row['persona_id'] ?? 0)] ?? ''), $waMessage)) ?>" target="_blank" rel="noopener" title="Notificar citación por WhatsApp (CIT)" aria-label="Notificar citación por WhatsApp"><svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.48A11.88 11.88 0 0 0 12.06 0C5.47 0 .11 5.36.11 11.95c0 2.1.55 4.15 1.6 5.96L0 24l6.26-1.64a11.93 11.93 0 0 0 5.8 1.48h.01C18.65 23.84 24 18.48 24 11.9c0-3.19-1.24-6.19-3.48-8.42ZM12.07 21.82a9.9 9.9 0 0 1-5.05-1.38l-.36-.21-3.71.97.99-3.61-.24-.37a9.86 9.86 0 0 1-1.52-5.27c0-5.47 4.45-9.92 9.92-9.92a9.86 9.86 0 0 1 7.02 2.91 9.86 9.86 0 0 1 2.9 7.02c0 5.47-4.45 9.92-9.95 9.92Zm5.44-7.43c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.47-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.18-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.49s1.07 2.89 1.22 3.09c.15.2 2.1 3.2 5.09 4.49.71.31 1.27.49 1.7.63.71.23 1.36.2 1.87.12.57-.09 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42-.07-.13-.27-.2-.57-.35Z"/></svg></a>
                <a class="btn" href="citacion_leer.php?id=<?= (int) $row['id'] ?>&return=<?= urlencode('citacion_listar.php?accidente_id=' . $accidenteId) ?>" title="Ver citación" aria-label="Ver citación">👁️</a>
                <a class="btn" href="citacion_editar.php?id=<?= (int) $row['id'] ?>&return=<?= urlencode('citacion_listar.php?accidente_id=' . $accidenteId) ?>" title="Editar citación" aria-label="Editar citación">✏️</a>
                <a class="btn" href="citacion_diligencia.php?citacion_id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener" title="Descargar Word" aria-label="Descargar Word">⬇️</a>
                <?php if (App\Support\Access::canDeleteCaseRecord($accidenteId)): ?>
                <form method="post" onsubmit="return confirm('Eliminar esta citacion<?= $calendarEventId !== '' && $calendarOwner ? ' y tambien su evento en Google Calendar' : '' ?>?');" style="display:inline;">
                  <input type="hidden" name="_csrf" value="<?= h(App\Support\Access::csrf()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                  <?php if ($returnTo !== ''): ?><input type="hidden" name="return_to" value="<?= h($returnTo) ?>"><?php endif; ?>
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <button class="btn danger" type="submit" title="Eliminar citación" aria-label="Eliminar citación">X</button>
                </form>
                <?php endif; ?>
              </div>
          </div>
        </section>
        <?php endforeach; ?>
      </div>
      <?php
        $groupLawyers = array_filter($lawyers, static fn($lawyer) => !empty($lawyer['es_actual']));
      ?>
      <?php if ($groupLawyers): ?>
      <div class="diligencia-lawyers"><h3>⚖️ Abogados del expediente</h3>
        <?php foreach ($groupLawyers as $lawyer): ?>
          <?php
            $lawyerCitations = App\Support\CitacionNotificacion::forLawyer($group, (int) $lawyer['persona_id']);
            $lawyerNotified = App\Support\CitacionNotificacion::isNotified($lawyerCitations, $lawyerNotifications[(int) $lawyer['id']] ?? []);
          ?>
          <div class="diligencia-lawyer"><div><strong><?= h(trim($lawyer['nombres'].' '.$lawyer['apellido_paterno'].' '.$lawyer['apellido_materno'])) ?></strong><p>Representa a <?= h($lawyer['persona_rep_nom']) ?><?= $lawyer['registro'] ? ' · '.h($lawyer['registro']) : '' ?></p></div>
            <?php if ($lawyerNotified): ?><span class="lawyer-notified" title="Documento de notificación generado">✅ Abogado notificado</span><?php else: ?>
            <a class="btn" href="marcador_abogado.php?<?= h(http_build_query(['citacion_ids' => array_column($lawyerCitations, 'id')])) ?>&abogado_id=<?= (int)$lawyer['id'] ?>&return_to=<?= urlencode('citacion_listar.php?accidente_id='.$accidenteId) ?>">Notificar a abogado</a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
</div>
<script src="assets/js/citacion_whatsapp.js"></script>
</body>
</html>
