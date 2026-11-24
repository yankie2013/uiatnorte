<?php
require __DIR__ . '/auth.php';
require_login();
require __DIR__ . '/db.php';

use App\Support\Access;
use App\Services\AnalisisInformeService;

header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); throw new RuntimeException('Método no permitido.'); }
    Access::checkCsrf();
    $case = (int) ($_POST['accidente_id'] ?? 0);
    Access::requireEdit($case);
    $stmt = $pdo->prepare('SELECT id FROM accidentes_activos WHERE id = ?');
    $stmt->execute([$case]);
    if (!$stmt->fetchColumn()) throw new RuntimeException('Expediente no disponible.');
    $section = (string) ($_POST['seccion'] ?? '');
    $data = json_decode((string) ($_POST['contenido'] ?? ''), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('Contenido no válido.');
    $clean = AnalisisInformeService::validate($pdo, $case, $section, $data);
    $version = (int) ($_POST['version'] ?? 0);
    $content = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if ($version === 0) {
        try {
            $stmt = $pdo->prepare('INSERT INTO accidente_analisis_informe (accidente_id, seccion, contenido) VALUES (?, ?, ?)');
            $stmt->execute([$case, $section, $content]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            http_response_code(409);
            throw new RuntimeException('Otro usuario guardó este apartado. Recarga la página antes de editarlo.');
        }
    } else {
        $stmt = $pdo->prepare('UPDATE accidente_analisis_informe SET contenido = ?, version = version + 1 WHERE accidente_id = ? AND seccion = ? AND version = ?');
        $stmt->execute([$content, $case, $section, $version]);
        if (!$stmt->rowCount()) { http_response_code(409); throw new RuntimeException('Este apartado cambió. Copia tus cambios y recarga la página.'); }
    }
    echo json_encode(['ok' => true, 'version' => $version + 1]);
} catch (Throwable $e) {
    if (http_response_code() < 400) http_response_code(422);
    $message = $e instanceof PDOException ? 'No se pudo guardar el análisis. Verifica la migración de la base de datos.' : $e->getMessage();
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
}
