<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\ActaRecepcionVideoRepository;
use App\Services\ActaRecepcionVideoService;
use App\Support\Access;

function arv_h(mixed $value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function arv_field(string $name, string $label, array $data, string $type = 'text', bool $required = false, string $help = ''): void
{
    $id = 'field-' . str_replace('_', '-', $name);
    echo '<div class="field"><label for="' . arv_h($id) . '">' . arv_h($label) . ($required ? ' *' : '') . '</label>';
    if ($type === 'textarea') {
        echo '<textarea id="' . arv_h($id) . '" name="' . arv_h($name) . '" rows="2"' . ($required ? ' required' : '') . '>' . arv_h($data[$name] ?? '') . '</textarea>';
    } else {
        echo '<input id="' . arv_h($id) . '" name="' . arv_h($name) . '" type="' . arv_h($type) . '" value="' . arv_h($data[$name] ?? '') . '"' . ($required ? ' required' : '') . '>';
    }
    if ($help !== '') echo '<small>' . arv_h($help) . '</small>';
    echo '</div>';
}

function arv_district_select(array $data, string $name = 'distrito_acta', string $label = 'Distrito'): void
{
    $selected = ActaRecepcionVideoService::canonicalDistrict((string) ($data[$name] ?? ''));
    $id = 'field-' . str_replace('_', '-', $name);
    echo '<div class="field"><label for="' . arv_h($id) . '">' . arv_h($label) . ' *</label><select id="' . arv_h($id) . '" name="' . arv_h($name) . '" required>';
    echo '<option value="">Seleccionar distrito...</option>';
    foreach (ActaRecepcionVideoService::DISTRICTS as $district) {
        echo '<option value="' . arv_h($district) . '"' . ($selected === $district ? ' selected' : '') . '>' . arv_h($district) . '</option>';
    }
    echo '</select></div>';
}

$repo = new ActaRecepcionVideoRepository($pdo);
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
$row = $id > 0 ? $repo->find($id) : null;
if ($id > 0 && !$row) { http_response_code(404); exit('Acta no encontrada.'); }
$accidenteId = $row ? (int) $row['accidente_id'] : (int) ($_GET['accidente_id'] ?? $_POST['accidente_id'] ?? 0);
if ($accidenteId <= 0) { http_response_code(400); exit('Falta accidente_id.'); }
Access::requireWorkspaceCase($accidenteId);
$accident = $repo->accident($accidenteId);
if (!$accident) { http_response_code(404); exit('Accidente no encontrado.'); }
$instructor = current_user() ?? [];
$userQuery = $pdo->prepare('SELECT telefono FROM usuarios WHERE id=? LIMIT 1');
$userQuery->execute([Access::id()]);
$instructor['telefono'] = (string) ($userQuery->fetchColumn() ?: '');
$defaults = ActaRecepcionVideoService::defaults($accident, $instructor);
$data = $row ? array_merge($defaults, $row['datos']) : $defaults;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = array_merge($data, $_POST);
    try {
        Access::requireEdit($accidenteId);
        Access::checkCsrf();
        if (!$row && ($accident['responsable_rol'] ?? '') !== 'jefe_emi') {
            throw new RuntimeException('Asigne un JEFE EMI responsable al accidente antes de guardar el acta.');
        }
        // La identidad del responsable proviene del expediente o de la copia ya guardada.
        foreach (['jefe_emi_nombre', 'jefe_emi_grado', 'jefe_emi_cip', 'jefe_emi_cargo', 'jefe_emi_unidad'] as $field) {
            $data[$field] = $row ? ($row['datos'][$field] ?? $defaults[$field]) : $defaults[$field];
        }
        $data = ActaRecepcionVideoService::validated($data);
        $id = $repo->save($id > 0 ? $id : null, $accidenteId, $data, Access::id());
        if ($embed) {
            echo '<!doctype html><meta charset="utf-8"><script>window.parent.postMessage({type:"acta.saved"},"*")</script><p>Acta guardada. <a href="acta_recepcion_video_descargar.php?id=' . $id . '">Descargar Word</a></p>';
            exit;
        }
        header('Location: accidente_vista_tabs.php?accidente_id=' . $accidenteId . '&tab=documentos&subtab=actas');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $id ? 'Editar' : 'Nueva' ?> acta de recepción de video</title>
<style>
:root{--bg:#f5f7fb;--panel:#fff;--line:#dce3ed;--text:#182235;--muted:#67758a;--blue:#255fbd}*{box-sizing:border-box}body{margin:0;padding:18px;background:var(--bg);color:var(--text);font:14px/1.45 Inter,system-ui,sans-serif}.wrap{max-width:1020px;margin:0 auto}.head,.actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.panel{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:20px;margin:0 0 14px}.panel h2{font-size:17px;margin:0 0 14px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.wide{grid-column:1/-1}.field label{display:block;font-weight:700;margin-bottom:5px}.field input,.field select,.field textarea{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:9px;background:white;color:var(--text);font:inherit}.field textarea{resize:vertical}.field small,.muted{display:block;color:var(--muted);font-size:12px;margin-top:4px}.btn{display:inline-flex;align-items:center;justify-content:center;padding:9px 14px;border:1px solid var(--line);border-radius:9px;background:white;color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--blue);border-color:var(--blue);color:white}.error{padding:12px;margin-bottom:14px;border:1px solid #fecdd3;border-radius:9px;background:#fff1f2;color:#9f1239}@media(max-width:700px){.grid{grid-template-columns:1fr}.wide{grid-column:auto}}
</style>
</head>
<body>
<?php if (!$embed) require __DIR__ . '/sidebar.php'; ?>
<main class="wrap">
<div class="panel head"><div><h1 style="margin:0 0 4px;font-size:22px"><?= $id ? 'Editar' : 'Nueva' ?> acta de recepción de video</h1><span class="muted">Accidente #<?= $accidenteId ?></span></div><div class="actions"><?php if ($id): ?><a class="btn" href="acta_recepcion_video_descargar.php?id=<?= $id ?>">Descargar Word</a><?php endif; ?><?php if ($embed): ?><button class="btn" type="button" onclick="parent.postMessage({type:'acta.close'},'*')">Cerrar</button><?php else: ?><a class="btn" href="accidente_vista_tabs.php?accidente_id=<?= $accidenteId ?>&tab=documentos&subtab=actas">Volver</a><?php endif; ?></div></div>
<?php if ($error !== ''): ?><div class="error"><?= arv_h($error) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="_csrf" value="<?= arv_h(Access::csrf()) ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="accidente_id" value="<?= $accidenteId ?>"><input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
<section class="panel"><h2>Datos de la diligencia</h2><div class="grid">
<?php arv_field('fecha_acta', 'Fecha del acta', $data, 'date', true); arv_field('hora_inicio', 'Hora de inicio', $data, 'time', true); arv_field('hora_fin', 'Hora de culminación', $data, 'time', true); arv_district_select($data); arv_field('unidad', 'Oficina o unidad policial', $data, 'text', true); arv_field('instructor_grado', 'Grado del instructor', $data); arv_field('instructor_nombre', 'Instructor', $data, 'text', true); ?>
</div></section>
<section class="panel"><h2>Datos del accidente</h2><p class="muted">Se cargaron del expediente. Revísalos antes de generar el acta; esta copia conservará los valores guardados.</p><div class="grid">
<?php arv_field('accidente_fecha', 'Fecha y hora del accidente', $data, 'datetime-local', true); arv_field('accidente_tipo', 'Modalidad', $data); arv_field('accidente_consecuencia', 'Consecuencia', $data, 'text', false, 'Ej.: con consecuencia fatal'); arv_district_select($data, 'accidente_distrito', 'Distrito del accidente'); ?>
<?php arv_field('accidente_lugar', 'Lugar del accidente', $data, 'textarea', true); arv_field('accidente_referencia', 'Referencia del accidente', $data, 'textarea'); ?>
</div></section>
<section class="panel"><h2>Sello y firma del JEFE EMI responsable</h2><p class="muted">Estos datos se toman de la cuenta asignada al expediente y se guardan en esta acta. Para corregirlos, el administrador debe editar ese usuario antes de crear el acta.</p><div class="grid">
<div class="field"><label>CIP</label><input value="<?= arv_h($data['jefe_emi_cip'] ?? '') ?>" readonly></div>
<div class="field"><label>Grado</label><input value="<?= arv_h($data['jefe_emi_grado'] ?? '') ?>" readonly></div>
<div class="wide field"><label>Nombre completo</label><input value="<?= arv_h($data['jefe_emi_nombre'] ?? '') ?>" readonly></div>
</div></section>
<section class="panel"><h2>Recepción del video</h2><div class="grid">
<?php arv_field('fecha_hallazgo', 'Fecha del hallazgo de la cámara', $data, 'date', true); arv_field('hora_hallazgo', 'Hora del hallazgo', $data, 'time', true); ?>
<div class="wide"><?php arv_field('ubicacion_camara', 'Ubicación de la cámara', $data, 'textarea', true); ?></div>
<?php arv_field('persona_entrega', 'Persona que proporcionó el video', $data, 'text', true); arv_field('tipo_documento', 'Tipo de documento', $data, 'text', true); arv_field('numero_documento', 'Número de documento', $data, 'text', true); arv_field('oficio_numero', 'Número completo del oficio entregado', $data, 'text', true); arv_field('medio_recepcion', 'Medio de recepción', $data, 'text', true); arv_field('telefono_origen', 'Teléfono de origen', $data); arv_field('telefono_destino', 'Teléfono de destino', $data); ?>
</div></section>
<section class="panel"><h2>Transferencia, grabación y lacrado</h2><div class="grid">
<?php arv_field('marca_pc', 'Marca de la PC', $data, 'text', true); arv_field('nombre_archivo', 'Nombre exacto del archivo', $data, 'text', true); arv_field('medio_grabacion', 'Medio de grabación', $data, 'text', true); arv_field('sobre_descripcion', 'Sobre o empaque', $data); arv_field('lacrado_descripcion', 'Material de lacrado', $data); arv_field('cadena_custodia', 'Constancia de cadena de custodia', $data); ?>
</div></section>
<div class="panel actions"><span class="muted">Los campos con * son obligatorios.</span><button class="btn primary" type="submit">Guardar acta</button></div>
</form>
</main></body></html>
