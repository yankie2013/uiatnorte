<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Repositories/OficioRepository.php';

use App\Repositories\OficioRepository;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE oficios (id INTEGER PRIMARY KEY, anio INTEGER NOT NULL, numero INTEGER NOT NULL)');
$pdo->exec('INSERT INTO oficios (anio, numero) VALUES (2025, 1001), (2026, 943)');
$repository = new OficioRepository($pdo);

if ($repository->nextNumero(2026) !== 944 || $repository->nextNumero(2027) !== 1) {
    throw new RuntimeException('El correlativo no se reinició al cambiar de año.');
}
if (!$repository->numeroExists(2026, 943) || $repository->numeroExists(2027, 943)) {
    throw new RuntimeException('La comprobación del número no respeta el año.');
}
if ($repository->availableYears() !== [2026, 2025]) {
    throw new RuntimeException('El filtro no ofrece los años históricos.');
}

echo "PASS: numeración independiente por año e historial disponible.\n";
