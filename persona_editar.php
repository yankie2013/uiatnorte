<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Repositories\PersonaRepository;
use App\Services\PersonaService;

header('Content-Type: text/html; charset=utf-8');

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$service = new PersonaService(new PersonaRepository($pdo));
$embed = (int) ($_GET['embed'] ?? $_POST['embed'] ?? 0) === 1;
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$returnTo = trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? ''));
if ($returnTo === '') {
    $returnTo = 'persona_listar.php';
}
if ($id <= 0) {
    http_response_code(400);
    exit('Falta id');
}

$context = $service->detailContext($id);
$row = $context['row'];
$error = '';
$data = $service->defaultData($row);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'tipo_doc' => $_POST['tipo_doc'] ?? 'DNI',
        'num_doc' => $_POST['num_doc'] ?? '',
        'apellido_paterno' => $_POST['apellido_paterno'] ?? '',
        'apellido_materno' => $_POST['apellido_materno'] ?? '',
        'nombres' => $_POST['nombres'] ?? '',
        'sexo' => $_POST['sexo'] ?? '',
        'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? '',
        'edad' => $_POST['edad'] ?? '',
        'estado_civil' => $_POST['estado_civil'] ?? '',
        'nacionalidad' => $_POST['nacionalidad'] ?? '',
        'departamento_nac' => $_POST['departamento_nac'] ?? '',
        'provincia_nac' => $_POST['provincia_nac'] ?? '',
        'distrito_nac' => $_POST['distrito_nac'] ?? '',
        'domicilio' => $_POST['domicilio'] ?? '',
        'domicilio_departamento' => $_POST['domicilio_departamento'] ?? '',
        'domicilio_provincia' => $_POST['domicilio_provincia'] ?? '',
        'domicilio_distrito' => $_POST['domicilio_distrito'] ?? '',
        'ocupacion' => $_POST['ocupacion'] ?? '',
        'grado_instruccion' => $_POST['grado_instruccion'] ?? '',
        'nombre_padre' => $_POST['nombre_padre'] ?? '',
        'nombre_madre' => $_POST['nombre_madre'] ?? '',
        'celular' => $_POST['celular'] ?? '',
        'email' => $_POST['email'] ?? '',
        'notas' => $_POST['notas'] ?? '',
        'foto_path' => $_POST['foto_path'] ?? '',
        'api_fuente' => $_POST['api_fuente'] ?? '',
        'api_ref' => $_POST['api_ref'] ?? '',
    ];

    try {
        $service->update($id, $data);
        if ($embed) {
            ?>
            <!doctype html>
            <html lang="es"><head><meta charset="utf-8"><title>Persona actualizada</title></head><body>
            <script>
            try { window.parent && window.parent.postMessage({type:'persona_saved', id:<?= json_encode($id) ?>}, '*'); } catch (e) {}
            </script>
            Persona actualizada correctamente.
            </body></html>
            <?php
            exit;
        }
        header('Location: ' . $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'ok=updated');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (!$embed) {
    include __DIR__ . '/sidebar.php';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Editar persona</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#0f766e;--danger:#b91c1c}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#5eead4;--danger:#fecaca}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1120px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(15,118,110,.12);color:var(--primary);border:1px solid rgba(15,118,110,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.primary{background:var(--primary);color:#ecfeff;border-color:transparent}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.c4{grid-column:span 4}.c3{grid-column:span 3}.field{display:flex;flex-direction:column;gap:6px}.label{font-size:12px;color:var(--muted);font-weight:700}.small{color:var(--muted);font-size:12px}.err{background:rgba(220,38,38,.12);color:var(--danger);padding:10px;border-radius:10px;margin-bottom:12px}.photo{width:100px;height:100px;border:1px solid var(--border);border-radius:14px;object-fit:cover;display:block}.actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:16px}input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:transparent;color:var(--text)}textarea{min-height:96px;resize:vertical}@media(max-width:860px){.c6,.c4,.c3{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div><h1 style="margin:0 0 6px">Persona <span class="badge">Editar</span></h1><div class="small">Registro #<?= (int) $id ?></div></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><?php if (!$embed): ?><a class="btn" href="<?= h($returnTo) ?>">Volver</a><?php endif; ?><button class="btn primary" type="submit" form="frmPersona">Guardar cambios</button></div>
  </div>

  <?php if ($error !== ''): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

  <form method="post" class="card" id="frmPersona" autocomplete="off">
    <input type="hidden" name="embed" value="<?= $embed ? 1 : 0 ?>">
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
    <input type="hidden" name="foto_path" value="<?= h((string) $data['foto_path']) ?>">
    <input type="hidden" name="api_fuente" value="<?= h((string) $data['api_fuente']) ?>">
    <input type="hidden" name="api_ref" value="<?= h((string) $data['api_ref']) ?>">
    <div class="grid">
      <div class="c3 field"><label class="label">Tipo de documento*</label><select name="tipo_doc" required><?php foreach (['DNI','CE','PAS','OTRO'] as $opt): ?><option value="<?= $opt ?>" <?= (string) $data['tipo_doc'] === $opt ? 'selected' : '' ?>><?= $opt ?></option><?php endforeach; ?></select></div>
      <div class="c3 field"><label class="label">Numero de documento*</label><input type="text" name="num_doc" value="<?= h((string) $data['num_doc']) ?>" maxlength="20" required></div>
      <div class="c6 field"><label class="label">Foto / fuente API</label><div class="small"><?= h((string) (($data['foto_path'] !== '' ? $data['foto_path'] : 'Sin foto') . (($data['api_fuente'] !== '' ? ' - ' . $data['api_fuente'] : '')))) ?></div><?php if ((string) $data['foto_path'] !== ''): ?><img class="photo" src="<?= h((string) $data['foto_path']) ?>" alt="Foto persona"><?php endif; ?></div>
      <div class="c4 field"><label class="label">Apellido paterno*</label><input type="text" name="apellido_paterno" value="<?= h((string) $data['apellido_paterno']) ?>" required></div>
      <div class="c4 field"><label class="label">Apellido materno*</label><input type="text" name="apellido_materno" value="<?= h((string) $data['apellido_materno']) ?>" required></div>
      <div class="c4 field"><label class="label">Nombres*</label><input type="text" name="nombres" value="<?= h((string) $data['nombres']) ?>" required></div>
      <div class="c3 field"><label class="label">Sexo*</label><select name="sexo" required><option value="">Selecciona</option><option value="M" <?= (string) $data['sexo'] === 'M' ? 'selected' : '' ?>>Masculino</option><option value="F" <?= (string) $data['sexo'] === 'F' ? 'selected' : '' ?>>Femenino</option></select></div>
      <div class="c3 field"><label class="label">Fecha de nacimiento*</label><input type="date" name="fecha_nacimiento" id="fecha_nacimiento" value="<?= h((string) $data['fecha_nacimiento']) ?>" required></div>
      <div class="c3 field"><label class="label">Edad</label><input type="number" id="edad" value="<?= h((string) $data['edad']) ?>" readonly></div>
      <div class="c3 field"><label class="label">Estado civil</label><select name="estado_civil"><?php $states=[''=>'Selecciona','Soltero'=>'Soltero','Casado'=>'Casado','Viudo'=>'Viudo','Divorciado'=>'Divorciado','Conviviente'=>'Conviviente']; foreach($states as $value=>$label): ?><option value="<?= h((string) $value) ?>" <?= (string) $data['estado_civil'] === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
      <div class="c3 field"><label class="label">Nacionalidad</label><input type="text" name="nacionalidad" value="<?= h((string) $data['nacionalidad']) ?>"></div>
      <div class="c3 field"><label class="label">Departamento nacimiento</label><input type="text" name="departamento_nac" value="<?= h((string) $data['departamento_nac']) ?>"></div>
      <div class="c3 field"><label class="label">Provincia nacimiento</label><input type="text" name="provincia_nac" value="<?= h((string) $data['provincia_nac']) ?>"></div>
      <div class="c3 field"><label class="label">Distrito nacimiento</label><input type="text" name="distrito_nac" value="<?= h((string) $data['distrito_nac']) ?>"></div>
      <div class="c12 field"><label class="label">Domicilio</label><textarea name="domicilio"><?= h((string) $data['domicilio']) ?></textarea></div>
      <div class="c4 field"><label class="label">Departamento domicilio</label><input type="text" name="domicilio_departamento" value="<?= h((string) $data['domicilio_departamento']) ?>"></div>
      <div class="c4 field"><label class="label">Provincia domicilio</label><input type="text" name="domicilio_provincia" value="<?= h((string) $data['domicilio_provincia']) ?>"></div>
      <div class="c4 field"><label class="label">Distrito domicilio</label><input type="text" name="domicilio_distrito" value="<?= h((string) $data['domicilio_distrito']) ?>"></div>
      <div class="c4 field"><label class="label">Ocupacion</label><input type="text" name="ocupacion" value="<?= h((string) $data['ocupacion']) ?>"></div>
      <div class="c4 field"><label class="label">Grado de instruccion</label><input type="text" name="grado_instruccion" value="<?= h((string) $data['grado_instruccion']) ?>"></div>
      <div class="c4 field"><label class="label">Celular</label><input type="text" name="celular" value="<?= h((string) $data['celular']) ?>"></div>
      <div class="c6 field"><label class="label">Email</label><input type="email" name="email" value="<?= h((string) $data['email']) ?>"></div>
      <div class="c6 field"><label class="label">Nombre del padre</label><input type="text" name="nombre_padre" value="<?= h((string) $data['nombre_padre']) ?>"></div>
      <div class="c6 field"><label class="label">Nombre de la madre</label><input type="text" name="nombre_madre" value="<?= h((string) $data['nombre_madre']) ?>"></div>
      <div class="c12 field"><label class="label">Notas</label><textarea name="notas"><?= h((string) $data['notas']) ?></textarea></div>
    </div>
    <div class="actions"><?php if (!$embed): ?><a class="btn" href="<?= h($returnTo) ?>">Cancelar</a><?php endif; ?><button class="btn primary" type="submit">Guardar</button></div>
  </form>
</div>
<script>
(function(){const fecha=document.getElementById('fecha_nacimiento');const edad=document.getElementById('edad');function calcEdad(){if(!fecha.value){edad.value='';return;}const birth=new Date(fecha.value+'T00:00:00');const today=new Date();let years=today.getFullYear()-birth.getFullYear();const m=today.getMonth()-birth.getMonth();if(m<0||(m===0&&today.getDate()<birth.getDate()))years--;edad.value=years>=0?years:'';}fecha.addEventListener('change',calcEdad);calcEdad();})();
</script>
</body>
</html>
