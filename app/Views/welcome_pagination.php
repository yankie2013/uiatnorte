<?php if ($welcomeHasSearch && $welcomeSearch['error'] === '' && $welcomeTotal > 0): ?>
<nav class="welcome-pagination" aria-label="Páginas de resultados">
  <?php if ($welcomeSearch['page'] > 1): ?><a href="<?= h(\App\Support\WelcomeSearch::url($welcomeSearch, $welcomeSearch['page'] - 1)) ?>#accident-filter-results" rel="prev">← Anterior</a><?php endif; ?>
  <span>Página <?= $welcomeSearch['page'] ?> de <?= $welcomePages ?> · <?= $welcomeTotal ?> expedientes</span>
  <?php if ($welcomeSearch['page'] < $welcomePages): ?><a href="<?= h(\App\Support\WelcomeSearch::url($welcomeSearch, $welcomeSearch['page'] + 1)) ?>#accident-filter-results" rel="next">Siguiente →</a><?php endif; ?>
</nav>
<?php endif; ?>
