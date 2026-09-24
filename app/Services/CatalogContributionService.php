<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Access;
use PDO;
use RuntimeException;

final class CatalogContributionService
{
    private const TABLES = [
        'oficio_categoria_entidad', 'oficio_entidad', 'oficio_oficial_ano', 'grado_cargo',
        'oficio_asunto', 'oficio_subentidad', 'oficio_persona_entidad', 'categoria_vehiculos',
        'marcas_vehiculo', 'modelos_vehiculo', 'tipos_vehiculo', 'carroceria_vehiculo', 'enlace_interes',
    ];

    public static function record(PDO $pdo, string $table, int $recordId): void
    {
        if (!in_array($table, self::TABLES, true) || $recordId <= 0 || Access::id() <= 0) {
            throw new RuntimeException('No se pudo identificar el autor del nuevo valor.');
        }
        $st = $pdo->prepare('INSERT INTO catalogo_aportaciones (tabla, registro_id, usuario_id) VALUES (?, ?, ?)');
        $st->execute([$table, $recordId, Access::id()]);
    }
}
