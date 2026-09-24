<?php
declare(strict_types=1);

// Isolated rendering test. Authentication is stubbed; no application DB is used.
namespace App\Support {
    final class Access {
        public static function admin(): bool { return true; }
        public static function canEdit(int $id): bool { return true; }
        public static function csrf(): string { return 'test-token'; }
    }
}

namespace {
    require dirname(__DIR__) . '/app/Support/WorkspacePage.php';
    require dirname(__DIR__) . '/app/Support/CaseStatePanel.php';

    function check(bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    }

    $pdo = new \PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    $level = ob_get_level();

    // Previously, this error stopped the page before any bottom scripts.
    ob_start();
    $failed = \App\Support\CaseStatePanel::render($pdo, 1);
    echo '<script src="assets/js/accidente-vista-tabs-layout.js"></script>';
    $tail = ob_get_clean();
    check($failed['error'] !== null, 'Missing schema must be reported.');
    check(str_contains($tail, '<script'), 'Rendering must reach the page scripts.');
    check(!str_contains($tail, 'SQLSTATE'), 'Errors must not leak into the output buffer.');
    check(ob_get_level() === $level, 'Caller output buffer must be preserved.');

    $pdo->exec("CREATE TABLE usuarios (id INTEGER, nombre TEXT, grado TEXT, rol TEXT, activo INTEGER);
        CREATE TABLE accidentes (id INTEGER, responsable_id INTEGER, eliminado_en TEXT,
        registro_sidpol TEXT, lugar TEXT, ubicacion_verificada INTEGER, latitud REAL, longitud REAL, estado TEXT);
        INSERT INTO accidentes VALUES (1, NULL, NULL, 'test', 'Test', 1, NULL, NULL, 'Pendiente');");
    $partial = \App\Support\CaseStatePanel::render($pdo, 1);
    check($partial['error'] !== null, 'Failure after case details must be reported.');
    check(!str_contains($partial['html'], '<form'), 'Discard forms rendered before a later query fails.');
    check(!str_contains($partial['html'], 'state-workspace-grid'), 'Discard unfinished panel markup.');
    check(ob_get_level() === $level, 'Late failure must restore buffer depth.');

    $pdo->exec("CREATE TABLE expediente_colaboradores (accidente_id INTEGER, usuario_id INTEGER, revocado_en TEXT);
        CREATE TABLE expediente_transferencias (id INTEGER, accidente_id INTEGER, destino_id INTEGER);
        CREATE TABLE auditoria (id INTEGER, accidente_id INTEGER, usuario_id INTEGER);");
    $complete = \App\Support\CaseStatePanel::render($pdo, 1);
    check($complete['error'] === null, 'A complete schema must render normally.');
    foreach (['Adjuntos con colaboración activa', 'Transferencias', 'Historial reciente'] as $label) {
        check(str_contains($complete['html'], $label), 'Missing healthy panel section: ' . $label);
    }
    check(ob_get_level() === $level, 'Success must restore buffer depth.');
    $pdo->exec('ALTER TABLE usuarios DROP COLUMN grado');
    $legacy = \App\Support\CaseStatePanel::render($pdo, 1);
    check($legacy['error'] === null, 'Missing optional grade must not break state rendering.');
    check(str_contains($legacy['html'], 'Transferencias'), 'Legacy schema must render all sections.');
    echo "PASS: early failure, partial failure, complete render; in-memory database only.\n";
}
