<?php
$recipientEntityText = trim((string)($data['entidad_nombre'] ?? '')) ?: $entidadDestinoTexto;
$recipientCargoText = trim((string)($data['grado_cargo_nombre'] ?? ''));
if ($recipientCargoText === '') {
    foreach ($ctx['grado_cargo'] as $cargo) {
        if ((int)$cargo['id'] === (int)$data['grado_cargo_id']) {
            $recipientCargoText = $cargo['nombre'] . ($cargo['abrev'] !== '' ? ' - '.$cargo['abrev'] : '');
            break;
        }
    }
}
$recipientCargoOptions = array_map(static fn(array $cargo): string => $cargo['nombre'] . ($cargo['abrev'] !== '' ? ' - '.$cargo['abrev'] : ''), $ctx['grado_cargo']);
?>
<input type="hidden" name="subentidad_id" id="subentidad_id" value="<?= h($data['subentidad_id'] ?? '') ?>">
<input type="hidden" name="persona_id" id="persona_id" value="<?= h($data['persona_id'] ?? '') ?>">
<input type="hidden" name="persona_destino_manual" id="persona_destino_manual" value="<?= h($data['persona_destino_manual'] ?? '') ?>">
<div class="c6">
  <label for="entidad_id_text">Entidad de destino*</label>
  <div class="combo-wrap combo-menu">
    <input type="hidden" name="entidad_id" id="entidad_id" value="<?= h($data['entidad_id']) ?>">
    <input type="text" name="entidad_nombre" id="entidad_id_text" value="<?= h($recipientEntityText) ?>" maxlength="200" placeholder="Escribe para buscar o agregar una entidad" autocomplete="off" required>
    <div id="entidad_id_options" class="combo-suggestions" role="listbox" aria-label="Sugerencias de entidad"></div>
    <div class="combo-hint">Selecciona una coincidencia o escribe una entidad nueva. Se guardará para reutilizarla.</div>
  </div>
</div>
<div class="c6">
  <label for="grado_cargo_text">Grado y cargo</label>
  <input type="hidden" name="grado_cargo_id" id="grado_cargo_id" value="<?= h($data['grado_cargo_id']) ?>">
  <div class="category-combobox" data-creatable-combobox data-options="<?= h(json_encode($recipientCargoOptions, JSON_UNESCAPED_UNICODE)) ?>">
    <input type="text" name="grado_cargo_nombre" id="grado_cargo_text" value="<?= h($recipientCargoText) ?>" maxlength="120" autocomplete="off" placeholder="Escribe para buscar o agregar un grado/cargo" role="combobox" aria-autocomplete="list" aria-expanded="false">
  </div>
  <div class="combo-hint">Opcional. Selecciona una coincidencia o escribe un valor nuevo para reutilizarlo.</div>
</div>

<div class="c6">
  <label for="persona_id_text">Persona destino</label>
  <div class="field-row">
    <div class="combo-wrap">
      <input type="text" id="persona_id_text" list="persona_id_options" value="<?= h($personaDestinoTexto) ?>" placeholder="Selecciona o escribe manualmente">
      <datalist id="persona_id_options">
        <?php foreach ($personasActuales as $persona): ?>
          <option value="<?= h(trim((string)$persona['nombre'])) ?>" data-id="<?= h($persona['id']) ?>"></option>
        <?php endforeach; ?>
      </datalist>
      <div class="combo-hint">Puedes elegir una persona registrada o escribirla manualmente. El nombre escrito se guardará solo en este oficio.</div>
    </div>
    <button class="btn mini" type="button" onclick="openCreate('persona')" aria-label="Agregar persona destino">+</button>
  </div>
</div>
