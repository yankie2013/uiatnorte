<?php use App\Support\WorkspacePage as InboxListPage; $esc = static fn($value) => InboxListPage::escape($value); ?>
<?php if (!$result['rows']): ?>
<div class="inbox-empty"><strong><?= $q!=='' || $state!==''?'Sin resultados para estos filtros':'No tienes diligencias pendientes' ?></strong><p><?= $q!=='' || $state!==''?'Prueba otro dato o limpia los filtros.':'Aquí aparecerán las diligencias pendientes y en proceso de tus expedientes.' ?></p></div>
<?php else: ?>
<div class="inbox-task-list" role="listbox" aria-label="Diligencias pendientes y en proceso">
<?php foreach ($result['rows'] as $row):
    $isSelected = $selected && (int)$selected['id'] === (int)$row['id'];
    $title = trim((string)$row['tipo_nombre']) ?: 'Diligencia #'.$row['id'];
    $description = trim((string)$row['contenido']);
    $inboxHue = (((int)$row['accidente_id'] % 360) * 137) % 360;
    $href = 'diligencias_pendientes.php?'.http_build_query(['q'=>$q,'estado'=>$state,'page'=>$result['page'],'seleccion'=>$row['id']]);
?>
<a href="<?= $esc($href) ?>" class="inbox-task <?= $isSelected?'is-selected':'' ?>" style="--inbox-case-hue:<?= $inboxHue ?>" role="option" aria-selected="<?= $isSelected?'true':'false' ?>" tabindex="<?= $isSelected?'0':'-1' ?>" data-task-id="<?= (int)$row['id'] ?>">
  <div class="inbox-task-top"><strong><?= $esc($title) ?></strong><span class="inbox-state <?= $row['estado']==='En proceso'?'in-process':'pending' ?>"><?= $esc($row['estado']) ?></span></div>
  <?php if ($description !== '' && $description !== $title): ?><span class="inbox-task-description"><?= $esc($description) ?></span><?php endif; ?>
</a>
<?php endforeach; ?>
</div>
<nav class="inbox-pagination" aria-label="Páginas de diligencias">
<?php if ($result['page']>1): ?><a href="?<?= $esc(http_build_query(['q'=>$q,'estado'=>$state,'page'=>$result['page']-1])) ?>" data-page="<?= $result['page']-1 ?>">← Anterior</a><?php endif; ?>
<span>Página <?= $result['page'] ?> de <?= $result['pages'] ?></span>
<?php if ($result['page']<$result['pages']): ?><a href="?<?= $esc(http_build_query(['q'=>$q,'estado'=>$state,'page'=>$result['page']+1])) ?>" data-page="<?= $result['page']+1 ?>">Siguiente →</a><?php endif; ?>
</nav>
<?php endif; ?>
