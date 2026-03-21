<?php
require_once __DIR__ . '/auth.php';
require_login();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Buscar Placa (UIAT Norte)</title>
  <style>
    body{font-family:system-ui,Arial;margin:20px;max-width:900px}
    input,button{padding:10px;font-size:16px}
    pre{background:#111;color:#0f0;padding:12px;overflow:auto}
    .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  </style>
</head>
<body>

<h2>Buscar placa</h2>

<div class="row">
  <input id="placa" placeholder="ANN697" maxlength="10">
  <button id="btn">Consultar</button>
  <button id="btnSave" disabled>Guardar en BD</button>
</div>

<p id="msg"></p>
<pre id="out">{}</pre>

<script>
let lastData = null;

const $ = (id)=>document.getElementById(id);
const btnConsultar = $("btn");
const btnGuardar = $("btnSave");

async function readJsonSafe(response){
  const raw = await response.text();
  try{
    return raw ? JSON.parse(raw) : {};
  }catch{
    throw new Error("El servidor no devolvio JSON valido.");
  }
}

btnConsultar.addEventListener("click", async ()=>{
  const placa = $("placa").value.trim().toUpperCase();
  $("msg").textContent = "";
  $("out").textContent = "{}";
  btnConsultar.disabled = true;
  btnGuardar.disabled = true;
  lastData = null;

  if(!placa){
    $("msg").textContent = "Ingrese placa";
    btnConsultar.disabled = false;
    return;
  }

  try{
    const r = await fetch(`buscar_placa.php?placa=${encodeURIComponent(placa)}`, {
      method: "GET",
      headers: { "Accept": "application/json" },
      credentials: "same-origin"
    });

    const json = await readJsonSafe(r);

    if(!json.ok){
      $("msg").textContent = json.error || "No se pudo consultar la placa.";
      $("out").textContent = JSON.stringify(json, null, 2);
      return;
    }

    lastData = json.respuesta || null;
    $("msg").textContent = "OK. Puedes guardar en BD.";
    $("out").textContent = JSON.stringify(json.respuesta || {}, null, 2);
    btnGuardar.disabled = !lastData;
  }catch(err){
    $("msg").textContent = "Error: " + (err.message || err);
  }finally{
    btnConsultar.disabled = false;
  }
});

btnGuardar.addEventListener("click", async ()=>{
  if(!lastData){ return; }

  try{
    btnGuardar.disabled = true;
    $("msg").textContent = "Guardando en BD...";

    const r = await fetch("guardar_vehiculo.php", {
      method: "POST",
      headers: { "Content-Type":"application/json", "Accept":"application/json" },
      body: JSON.stringify(lastData),
      credentials: "same-origin"
    });

    const j = await readJsonSafe(r);
    if(!r.ok || !j.ok){
      const msg = j.error || j.detail || "No se pudo guardar.";
      $("msg").textContent = "No guardo: " + msg;
      $("out").textContent = JSON.stringify(j, null, 2);
      return;
    }

    $("msg").textContent = "Guardado OK";
    $("out").textContent = JSON.stringify(j, null, 2);
  }catch(err){
    $("msg").textContent = "Error guardando: " + (err.message || err);
  }finally{
    btnGuardar.disabled = !lastData;
  }
});
</script>

</body>
</html>