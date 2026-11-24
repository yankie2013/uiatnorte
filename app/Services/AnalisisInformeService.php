<?php
namespace App\Services;

use PDO;
use InvalidArgumentException;

final class AnalisisInformeService
{
    public static function validate(PDO $pdo, int $case, string $section, array $data): array
    {
        $schemas = [
            'velocidad' => ['riesgos' => ['texto'], 'parrafos' => ['texto'], 'estimaciones' => ['vehiculo_id', 'desplazamiento']],
            'evidencias' => ['evidencias' => ['tipo', 'detalle', 'caracteristicas', 'ubicacion', 'consecuencia', 'determinacion']],
            'integral' => ['parrafos' => ['texto']],
            'causalidad' => ['factores' => ['tipo', 'involucrado_id', 'descripcion'], 'infracciones' => ['involucrado_id', 'codigo', 'descripcion']],
        ];
        if (!isset($schemas[$section])) throw new InvalidArgumentException('Apartado no válido.');
        $ids = [];
        foreach (['vehiculo_id' => ['involucrados_vehiculos_activos', 'vehiculo_id'], 'involucrado_id' => ['involucrados_personas_activos', 'id']] as $key => [$table, $column]) {
            $stmt = $pdo->prepare("SELECT $column FROM $table WHERE accidente_id = ?");
            $stmt->execute([$case]);
            $ids[$key] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        $clean = [];
        foreach ($schemas[$section] as $list => $fields) {
            $rows = $data[$list] ?? [];
            if (!is_array($rows) || count($rows) > 200) throw new InvalidArgumentException('La lista debe contener como máximo 200 elementos.');
            $clean[$list] = [];
            foreach ($rows as $row) {
                if (!is_array($row)) throw new InvalidArgumentException('Registro no válido.');
                $item = [];
                foreach ($fields as $field) {
                    $value = $row[$field] ?? '';
                    if (!is_scalar($value)) throw new InvalidArgumentException('Campo no válido.');
                    $value = trim((string) $value);
                    if (isset($ids[$field])) {
                        if (!in_array((int) $value, $ids[$field], true)) throw new InvalidArgumentException('Selecciona un vehículo o involucrado vigente de este expediente.');
                        $item[$field] = (int) $value;
                    } else {
                        if ($value === '' || mb_strlen($value) > 20000) throw new InvalidArgumentException('Completa todos los campos (máximo 20 000 caracteres por campo).');
                        $item[$field] = $value;
                    }
                }
                if ($list === 'evidencias' && !in_array($item['tipo'], ['Biológica', 'Física', 'Material'], true)) throw new InvalidArgumentException('Tipo de evidencia no válido.');
                if ($list === 'factores' && !in_array($item['tipo'], ['Determinante', 'Predominante', 'Contributivo'], true)) throw new InvalidArgumentException('Tipo de factor no válido.');
                $clean[$list][] = $item;
            }
        }
        return $clean;
    }
}
