<?php
declare(strict_types=1);
namespace App\Support;
use PDO;
use PDOException;
final class RecentAccidents
{
    public static function ids(PDO $pdo): array
    {
        try {
            $st = $pdo->prepare('SELECT accidente_id FROM usuario_accidentes_recientes WHERE usuario_id=? ORDER BY abierto_en DESC, accidente_id DESC LIMIT 6');
            $st->execute([Access::id()]);
            return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S02') throw $e;
            return array_slice(array_map('intval', $_SESSION['accidentes_ultimos_abiertos'] ?? []), 0, 6);
        }
    }
    public static function record(PDO $pdo, int $case): void
    {
        if (Access::id() <= 0 || $case <= 0) return;
        try {
            $st = $pdo->prepare('INSERT INTO usuario_accidentes_recientes(usuario_id,accidente_id,abierto_en) VALUES(?,?,CURRENT_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE abierto_en=CURRENT_TIMESTAMP(6)');
            $st->execute([Access::id(), $case]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S02') throw $e;
        }
    }
}
