<?php
$soatLinkQuery=$pdo->prepare('SELECT url FROM enlace_interes WHERE activo=1 AND nombre=? ORDER BY orden,id LIMIT 1');
$soatLinkQuery->execute(['Consulta SOAT']);
$soatConsultUrl=(string)($soatLinkQuery->fetchColumn()?:'');
if(filter_var($soatConsultUrl,FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($soatConsultUrl,PHP_URL_SCHEME)),['http','https'],true)):
?>
<a class="soat-consult" href="<?= h($soatConsultUrl) ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">🛡️</span> Consultar SOAT <span aria-hidden="true">↗</span></a>
<?php endif; ?>
