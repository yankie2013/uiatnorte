<?php
use App\Support\Access;
$topbarUser=Access::actor();
$topbarName=trim((string)($topbarUser['nombre']??'Usuario'));
$topbarRole=Access::ROLES[(string)($topbarUser['rol']??'')]??'Personal autorizado';
$topbarInitial=function_exists('mb_substr')?mb_strtoupper(mb_substr($topbarName,0,1,'UTF-8'),'UTF-8'):strtoupper(substr($topbarName,0,1));
$topbarEscape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$topbarSection=(string)($userTopbarSection??'Panel general');
?>
<header class="uiat-user-topbar">
  <div class="uiat-user-breadcrumb"><span>Gestión de la información</span><i>/</i><strong><?= $topbarEscape($topbarSection) ?></strong></div>
  <div class="uiat-user-topbar-right">
    <a class="uiat-user-search" href="gestion_expedientes.php" aria-label="Buscar expediente">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg><span>Buscar expediente</span>
    </a>
    <div class="uiat-user-profile" aria-label="<?= $topbarEscape($topbarName.' · '.$topbarRole) ?>">
      <span class="uiat-user-avatar"><?= $topbarEscape($topbarInitial) ?></span>
      <span class="uiat-user-copy"><strong><?= $topbarEscape($topbarName) ?></strong><small><?= $topbarEscape($topbarRole) ?></small></span>
    </div>
  </div>
</header>
