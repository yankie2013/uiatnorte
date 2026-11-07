<?php
$welcomeActor = \App\Support\Access::actor();
$welcomeName = trim((string)($welcomeActor['nombre'] ?? 'Usuario')) ?: 'Usuario';
$welcomeShortName = implode(' ', array_slice(preg_split('/\s+/u', $welcomeName), 0, 2));
$welcomeDistricts = array_keys($comisariasPorDistrito);
sort($welcomeDistricts, SORT_NATURAL | SORT_FLAG_CASE);
$welcomeCriterion = $welcomeSearch['criterion'];
$welcomePlaceholders = ['persona'=>'Busca un expediente por nombres o apellidos…', 'vehiculo'=>'Ingresa una placa vehicular…', 'distrito'=>'Selecciona o busca un distrito…', 'comisaria_id'=>'Selecciona o busca una comisaría…'];
?>
<?php if (!$isArchivo): ?>
<div class="welcome-actions"><a class="welcome-new-case" href="accidente_nuevo.php"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/><path d="M12 11v6m-3-3h6"/></svg><span>Nuevo Expediente</span></a></div>
<?php endif; ?>
<main class="welcome-hero" aria-labelledby="welcome-title" <?= $welcomeHasSearch ? 'hidden' : '' ?>>
  <div class="welcome-content">
    <h1 id="welcome-title">Bienvenido, <span><?= h($welcomeShortName) ?></span></h1>
  </div>
</main>
<section class="welcome-workspace" aria-label="Búsqueda de expedientes">
    <h2 class="welcome-question">¿En qué te gustaría trabajar hoy?</h2>
    <form class="welcome-search" action="index.php" method="get" role="search" aria-label="Buscar expedientes" data-criterion="<?= h($welcomeCriterion) ?>">
      <fieldset class="welcome-criteria"><legend class="welcome-sr">Buscar por</legend><?php foreach (\App\Support\WelcomeSearch::CRITERIA as $key=>$label): ?><label><input type="radio" name="criterio" value="<?= h($key) ?>" <?= $welcomeCriterion === $key ? 'checked' : '' ?>><span><?= h($label) ?></span></label><?php endforeach; ?></fieldset>
      <div class="welcome-searchbar">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>
        <?php foreach (\App\Support\WelcomeSearch::CRITERIA as $key=>$label): $selected = $key === $welcomeCriterion; ?>
        <div class="welcome-field" data-field="<?= h($key) ?>" <?= $selected ? '' : 'hidden' ?>>
          <label class="welcome-sr" for="welcome-<?= h($key) ?>"><?= h($label) ?></label>
          <?php if (in_array($key, ['distrito','comisaria_id'], true)): ?>
          <input id="welcome-<?= h($key) ?>" name="valor" list="welcome-options-<?= h($key) ?>" value="<?= $selected ? h($welcomeSearch['value']) : '' ?>" placeholder="<?= h($welcomePlaceholders[$key]) ?>" autocomplete="off" aria-describedby="welcome-help" <?= $selected ? '' : 'disabled' ?>>
          <datalist id="welcome-options-<?= h($key) ?>"><?php if ($key === 'distrito'): foreach ($welcomeDistricts as $option): ?><option value="<?= h($option) ?>"></option><?php endforeach; else: foreach ($comisarias as $option): ?><option value="<?= h($option['nombre']) ?>"></option><?php endforeach; endif; ?></datalist>
          <?php else: ?><input id="welcome-<?= h($key) ?>" name="valor" value="<?= $selected ? h($welcomeSearch['value']) : '' ?>" placeholder="<?= h($welcomePlaceholders[$key]) ?>" autocomplete="off" maxlength="200" aria-describedby="welcome-help" <?= $selected ? '' : 'disabled' ?>><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <button type="submit">Buscar <span aria-hidden="true">→</span></button>
      </div>
      <div class="welcome-search-footer"><p id="welcome-help">Elige un criterio e ingresa un dato para comenzar.</p><a href="index.php" class="welcome-clear">Limpiar búsqueda</a></div>
      <p class="welcome-status" role="status" aria-live="polite" hidden></p>
      <?php if ($welcomeSearch['error'] !== ''): ?><p class="welcome-error" role="alert"><?= h($welcomeSearch['error']) ?></p><?php elseif ($welcomeSearch['submitted'] && !$welcomeHasSearch): ?><p class="welcome-error" role="status">Ingresa un dato para buscar un expediente.</p><?php endif; ?>
    </form>
</section>
    <nav class="welcome-shortcuts" <?= $welcomeHasSearch ? 'hidden' : '' ?> aria-label="Accesos rápidos"><a href="accidente_listar.php">Mis expedientes <span aria-hidden="true">↗</span></a><a href="dashboard.php">Resumen general <span aria-hidden="true">↗</span></a></nav>
