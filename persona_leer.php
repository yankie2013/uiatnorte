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
$id = (int) ($_GET['id'] ?? 0);
$dni = trim((string) ($_GET['dni'] ?? ''));

if ($id > 0) {
    $context = $service->detailContext($id);
} elseif ($dni !== '') {
    $row = $service->findByDocumentNumber($dni);
    if ($row === null) {
        http_response_code(404);
        exit('Persona no encontrada.');
    }
    $context = $service->detailContext((int) $row['id']);
} else {
    http_response_code(400);
    exit('Falta id o dni');
}

$row = $context['row'];
$references = $context['references'];
$involvementCount = (int) $context['involvement_count'];
$returnTo = trim((string) ($_GET['return_to'] ?? ''));
if ($returnTo === '') {
    $returnTo = 'persona_listar.php';
}
$selfUrl = $_SERVER['REQUEST_URI'] ?? ('persona_leer.php?id=' . (int) $row['id']);
$ok = trim((string) ($_GET['ok'] ?? ''));

include __DIR__ . '/sidebar.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Detalle de persona</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--page:#f6f8fc;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#d7deea;--primary:#2563eb;--danger:#b91c1c;--ok:#166534}
@media (prefers-color-scheme: dark){:root{--page:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#23314d;--primary:#60a5fa;--danger:#fecaca;--ok:#bbf7d0}}
*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--text);font:14px/1.45 Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1080px;margin:24px auto;padding:0 12px}.toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:rgba(37,99,235,.12);color:var(--primary);border:1px solid rgba(37,99,235,.18);font-size:11px}.btn{padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--card);color:var(--text);font-weight:700;text-decoration:none;cursor:pointer}.btn.danger{color:var(--danger)}.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}.c12{grid-column:span 12}.c6{grid-column:span 6}.c4{grid-column:span 4}.field{padding:12px;border:1px solid var(--border);border-radius:14px;background:rgba(148,163,184,.08)}.label{font-size:12px;color:var(--muted);font-weight:700;margin-bottom:4px}.value{font-weight:700;word-break:break-word}.small{color:var(--muted);font-size:12px}.actions{display:flex;gap:10px;flex-wrap:wrap}.refs{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}.pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid var(--border);font-size:11px}.ok{background:rgba(22,163,74,.12);color:var(--ok);padding:10px;border-radius:10px;margin-bottom:14px}@media(max-width:860px){.c6,.c4{grid-column:span 12}}
</style>
</head>
<body>
<div class="wrap">
  <div class="toolbar">
    <div><h1 style="margin:0 0 6px">Persona <span class="badge">Detalle</span></h1><div class="small">Registro #<?= (int) $row['id'] ?></div></div>
    <div class="actions"><a class="btn" href="<?= h($returnTo) ?>">Volver</a><a class="btn" href="persona_editar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($selfUrl) ?>">Editar</a><a class="btn danger" href="persona_eliminar.php?id=<?= (int) $row['id'] ?>&return_to=<?= urlencode($returnTo) ?>">Eliminar</a></div>
  </div>

  <?php if ($ok !== ''): ?><div class="ok"><?= h($ok === 'updated' ? 'Persona actualizada correctamente.' : 'Operacion realizada correctamente.') ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:14px;">
    <strong><?= h(trim((string) (($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? '') . ', ' . ($row['nombres'] ?? '')))) ?></strong>
    <div class="small" style="margin-top:4px;">Documento: <?= h(trim((string) (($row['tipo_doc'] ?? '') . ' ' . ($row['num_doc'] ?? '')))) ?></div>
    <div class="refs"><span class="pill">Accidentes vinculados: <?= $involvementCount ?></span><?php foreach ($references as $ref): ?><span class="pill"><?= h($ref['label']) ?>: <?= (int) $ref['count'] ?></span><?php endforeach; ?></div>
  </div>

  <div class="card">
    <div class="grid">
      <div class="c4 field"><div class="label">Sexo</div><div class="value"><?= h((string) (($row['sexo'] ?? '') !== '' ? $row['sexo'] : '-')) ?></div></div>
      <div class="c4 field"><div class="label">Fecha de nacimiento</div><div class="value"><?= h((string) (($row['fecha_nacimiento'] ?? '') !== '' ? $row['fecha_nacimiento'] : '-')) ?></div></div>
      <div class="c4 field"><div class="label">Edad</div><div class="value"><?= h((string) (($row['edad'] ?? '') !== '' ? $row['edad'] : '-')) ?></div></div>
      <div class="c4 field"><div class="label">Estado civil</div><div class="value"><?= h((string) (($row['estado_civil'] ?? '') !== '' ? $row['estado_civil'] : '-')) ?></div></div>
      <div class="c4 field"><div class="label">Nacionalidad</div><div class="value"><?= h((string) (($row['nacionalidad'] ?? '') !== '' ? $row['nacionalidad'] : '-')) ?></div></div>
      <div class="c4 field"><div class="label">Ocupacion</div><div class="value"><?= h((string) (($row['ocupacion'] ?? '') !== '' ? $row['ocupacion'] : '-')) ?></div></div>
      <div class="c4 field"><div class="label">Grado de instruccion</div><div class="value"><?= h((string) (($row['grado_instruccion'] ?? '') !== '' ? $row['grado_instruccion'] : '-')) ?></div></div>
      <div class="c4 field"><div class="label">Celular</div><div class="value"><?= h((string) (($row['celular'] ?? '') !== '' ? $row['celular'] : '-')) ?></div></div>
      <div class="c4 field"><div class="label">Email</div><div class="value"><?= h((string) (($row['email'] ?? '') !== '' ? $row['email'] : '-')) ?></div></div>
      <div class="c4 field"><div class="label">Departamento nacimiento</div><div class="value"><?= h((string) (($row['departamento_nac'] ?? '') !== '' ? $row['departamento_nac'] : '-')) ?></div></div>
      <div class="c4 field"><div class="label">Provincia nacimiento</div><div class="value"><?= h((string) (($row['provincia_nac'] ?? '') !== '' ? $row['provincia_nac'] : '-')) ?></div></div>
      <div class="c4 field"><div class="label">Distrito nacimiento</div><div class="value"><?= h((string) (($row['distrito_nac'] ?? '') !== '' ? $row['distrito_nac'] : '-')) ?></div></div>
      <div class="c12 field"><div class="label">Domicilio</div><div class="value"><?= nl2br(h(trim((string) (($row['domicilio'] ?? '') . (($row['domicilio_distrito'] ?? '') !== '' ? ', ' . $row['domicilio_distrito'] : '') . (($row['domicilio_provincia'] ?? '') !== '' ? ', ' . $row['domicilio_provincia'] : '') . (($row['domicilio_departamento'] ?? '') !== '' ? ', ' . $row['domicilio_departamento'] : ''))))) ?></div></div>
      <div class="c6 field"><div class="label">Nombre del padre</div><div class="value"><?= h((string) (($row['nombre_padre'] ?? '') !== '' ? $row['nombre_padre'] : '-')) ?></div></div>
      <div class="c6 field"><div class="label">Nombre de la madre</div><div class="value"><?= h((string) (($row['nombre_madre'] ?? '') !== '' ? $row['nombre_madre'] : '-')) ?></div></div>
      <div class="c12 field"><div class="label">Notas</div><div class="value"><?= nl2br(h((string) (($row['notas'] ?? '') !== '' ? $row['notas'] : '-'))) ?></div></div>
      <?php if ((string) ($row['foto_path'] ?? '') !== ''): ?><div class="c12 field"><div class="label">Foto</div><div class="value"><a href="<?= h((string) $row['foto_path']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string) $row['foto_path']) ?></a></div></div><?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
