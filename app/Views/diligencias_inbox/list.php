<?php use App\Support\WorkspacePage as InboxListPage; $esc = static fn($value) => InboxListPage::escape($value); ?>
<?php if (!$result['rows']): ?>
<div class="inbox-empty"><strong><?= $q!=='' || $state!==''?'Sin resultados para estos filtros':'No tienes diligencias registradas' ?></strong><p><?= $q!=='' || $state!==''?'Prueba otro dato o limpia los filtros.':'Aquí aparecerán las diligencias de tus expedientes.' ?></p></div>
<?php else: ?>
<div class="inbox-task-list" role="list" aria-label="Diligencias">
<?php foreach ($result['rows'] as $row):
    $isSelected = $selected && (int)$selected['id'] === (int)$row['id'];
    $title = trim((string)$row['tipo_nombre']) ?: 'Diligencia #'.$row['id'];
    $description = trim((string)$row['contenido']);
    $inboxHue = (((int)$row['accidente_id'] % 360) * 137) % 360;
    $href = 'diligenciapendiente_ver.php?id='.(int)$row['id'];
?>
<article class="inbox-task <?= $isSelected?'is-selected':'' ?>" style="--inbox-case-hue:<?= $inboxHue ?>" role="listitem" <?= $isSelected?'aria-current="true"':'' ?> tabindex="<?= $isSelected?'0':'-1' ?>" data-task-id="<?= (int)$row['id'] ?>">
  <div class="inbox-task-top"><strong><a class="inbox-task-title" data-task-open href="<?= $esc($href) ?>"><?= $esc($title) ?></a></strong><select class="inbox-state inbox-state-select <?= ['Pendiente'=>'pending','En proceso'=>'in-process','Realizado'=>'completed'][$row['estado']] ?? 'pending' ?>" data-task-status data-prev-state="<?= $esc($row['estado']) ?>" aria-label="Estado de <?= $esc($title) ?>">
<?php foreach (['Pendiente'=>'Pendiente','En proceso'=>'En proceso','Realizado'=>'Realizada'] as $value=>$label): ?><option value="<?= $esc($value) ?>" <?= $row['estado']===$value?'selected':'' ?>><?= $esc($label) ?></option><?php endforeach; ?>
</select></div>
  <?php if ($description !== '' && $description !== $title): ?><span class="inbox-task-description"><?= $esc($description) ?></span><?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<nav class="inbox-pagination" aria-label="Páginas de diligencias">
<?php if ($result['page']>1): ?><a href="?<?= $esc(http_build_query(['q'=>$q,'estado'=>$state,'page'=>$result['page']-1])) ?>" data-page="<?= $result['page']-1 ?>">← Anterior</a><?php endif; ?>
<span>Página <?= $result['page'] ?> de <?= $result['pages'] ?></span>
<?php if ($result['page']<$result['pages']): ?><a href="?<?= $esc(http_build_query(['q'=>$q,'estado'=>$state,'page'=>$result['page']+1])) ?>" data-page="<?= $result['page']+1 ?>">Siguiente →</a><?php endif; ?>
</nav>
<?php endif; ?>
