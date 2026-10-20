<style>
#reniecFields{border:1px solid var(--border);border-radius:12px;padding:0 12px;background:var(--card)}
#reniecFields>summary{cursor:pointer;padding:12px 0;font-weight:700;font-size:14px}
#reniecFields .reniec-help{font-size:13px;line-height:1.4;margin:0 0 12px;color:var(--muted)}
#reniecFields .grid{padding-bottom:12px;gap:12px}
#reniecFields label{font-size:13px;margin-bottom:5px}
#reniecFields input{min-height:38px;height:38px;padding:8px 10px;font-size:14px}
#reniecFields textarea{min-height:64px;height:64px;padding:8px 10px;font-size:14px;line-height:1.35;resize:vertical}
</style>
<details class="c12" id="reniecFields" style="display:none">
  <summary>Inscripción defunción RENIEC — datos de la morgue</summary>
  <p class="reniec-help">Transcribe exactamente los documentos de la morgue. Los datos personales se completan desde la persona fallecida seleccionada.</p>
  <div class="grid">
  <?php foreach (\App\Support\DefuncionReniec::FIELDS as $key => $label): ?>
    <div class="c6"><label for="reniec_<?= h($key) ?>"><?= h($label) ?>*</label>
    <?php if (in_array($key, ['lugar_fallecimiento', 'causa_muerte'], true)): ?>
    <textarea id="reniec_<?= h($key) ?>" name="reniec[<?= h($key) ?>]" maxlength="4000" rows="2" disabled><?= h($data['reniec'][$key] ?? '') ?></textarea>
    <?php else: ?>
    <input type="text" id="reniec_<?= h($key) ?>" name="reniec[<?= h($key) ?>]" maxlength="4000" value="<?= h($data['reniec'][$key] ?? '') ?>" disabled>
    <?php endif; ?></div>
  <?php endforeach; ?>
    <div class="c12"><label for="reniec_adjuntos">Documentos que se adjuntan (opcional, según corresponda)</label><textarea id="reniec_adjuntos" name="reniec[adjuntos]" maxlength="4000" rows="2" disabled><?= h($data['reniec']['adjuntos'] ?? '') ?></textarea></div>
  </div>
</details>
<script>
function toggleReniecFields() {
  const text = normalizeText(asuntoTexto());
  const active = text.includes('defuncion') && text.includes('reniec');
  const box = document.getElementById('reniecFields');
  box.style.display = active ? 'block' : 'none';
  if (!active) box.open = false;
  box.querySelectorAll('input, textarea').forEach(input => { input.disabled = !active; input.required = active && input.name !== 'reniec[adjuntos]'; });
}
document.addEventListener('DOMContentLoaded', toggleReniecFields);
// Reveal mandatory fields before the browser focuses a validation error.
document.getElementById('reniecFields').addEventListener('invalid', function () { this.open = true; }, true);
</script>
