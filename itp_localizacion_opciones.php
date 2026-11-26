<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $caseId = (int) ($_GET['accidente_id'] ?? 0);
    if ($caseId <= 0) throw new InvalidArgumentException('Selecciona un accidente.');
    \App\Support\Access::requireEdit($caseId);
    $repository = new \App\Repositories\ItpRepository($pdo);
    echo json_encode(['ok' => true, 'items' => $repository->localizationCandidates($caseId)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'No se pudieron consultar los involucrados de este expediente.']);
}
