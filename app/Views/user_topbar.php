<?php
use App\Support\Access;
$topbarUser=Access::actor();
$topbarName=trim((string)($topbarUser['nombre']??'Usuario'));
$topbarRole=Access::ROLES[(string)($topbarUser['rol']??'')]??'Personal autorizado';
$topbarInitial=function_exists('mb_substr')?mb_strtoupper(mb_substr($topbarName,0,1,'UTF-8'),'UTF-8'):strtoupper(substr($topbarName,0,1));
$topbarEscape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$topbarSection=(string)($userTopbarSection??'Panel general');
$topbarBreadcrumbs=$userTopbarBreadcrumbs??[
    ['label'=>'Gestión de la información','url'=>'index.php'],
    ['label'=>$topbarSection,'url'=>null],
];
?>
<header class="uiat-user-topbar"<?= isset($userTopbarBreadcrumbs) ? ' data-custom-breadcrumb="1"' : '' ?>>
  <nav class="uiat-user-breadcrumb" aria-label="Ruta de navegación">
    <?php foreach($topbarBreadcrumbs as $index=>$crumb): ?>
      <?php if($index>0): ?><i aria-hidden="true">/</i><?php endif; ?>
      <?php if(!empty($crumb['url'])): ?><a href="<?= $topbarEscape($crumb['url']) ?>"<?= ($crumb['label']??'')==='Lista de accidentes' ? ' data-restore-list="1"' : '' ?>><?= $topbarEscape($crumb['label']) ?></a>
      <?php else: ?><strong aria-current="page"><?= $topbarEscape($crumb['label']) ?></strong><?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="uiat-user-topbar-right">
    <a class="uiat-user-search" href="gestion_expedientes.php" aria-label="Buscar expediente">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg><span>Buscar expediente</span>
    </a>
    <details class="uiat-user-menu">
      <summary class="uiat-user-profile" aria-label="Cuenta: <?= $topbarEscape($topbarName.' · '.$topbarRole) ?>">
        <span class="uiat-user-avatar"><?= $topbarEscape($topbarInitial) ?></span>
        <span class="uiat-user-copy"><strong><?= $topbarEscape($topbarName) ?></strong><small><?= $topbarEscape($topbarRole) ?></small></span>
      </summary>
      <div class="uiat-user-menu-panel"><a href="logout.php">Cerrar sesión</a></div>
    </details>
  </div>
</header>
