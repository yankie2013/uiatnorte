<?php
namespace App\Services;

use PDO;
use InvalidArgumentException;

final class AnalisisInformeService
{
    public static function validate(PDO $pdo, int $case, string $section, array $data): array
    {
        if ($section === 'descripcion') {
            if (!isset($data['contenido']) || !is_array($data['contenido'])) throw new InvalidArgumentException('Descripción analítica no válida.');
            return ['contenido' => DescripcionAnaliticaService::validate($data['contenido'])];
        }
        $schemas = [
            'velocidad' => ['riesgos' => ['texto'], 'parrafos' => ['texto'], 'estimaciones' => ['vehiculo_id', 'desplazamiento']],
            'danos-lesiones' => ['consideraciones' => ['texto'], 'parrafos' => ['texto'], 'determinaciones' => ['texto']],
            'evidencias' => ['evidencias' => ['tipo', 'detalle', 'caracteristicas', 'ubicacion', 'consecuencia', 'determinacion']],
            'integral' => ['parrafos' => ['texto']],
            'causalidad' => ['factores' => ['tipo', 'involucrado_id', 'descripcion']],
            'infracciones' => ['informacion' => ['texto'], 'anexos' => ['texto']],
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
                    if ($section === 'infracciones' && $field === 'vehiculo_id' && ($value === '' || $value === '0')) {
                        $item[$field] = '';
                    } elseif (isset($ids[$field])) {
                        if (!in_array((int) $value, $ids[$field], true)) throw new InvalidArgumentException('Selecciona un vehículo o involucrado vigente de este expediente.');
                        $item[$field] = (int) $value;
                    } else {
                        if (($value === '' && !($section === 'infracciones' && in_array($field, ['introduccion', 'norma'], true))) || mb_strlen($value) > 20000) throw new InvalidArgumentException('Completa todos los campos (máximo 20 000 caracteres por campo).');
                        $item[$field] = $value;
                    }
                }
                if ($list === 'evidencias' && !in_array($item['tipo'], ['Biológica', 'Física', 'Material'], true)) throw new InvalidArgumentException('Tipo de evidencia no válido.');
                if ($list === 'factores' && !in_array($item['tipo'], ['Determinante', 'Predominante', 'Contributivo'], true)) throw new InvalidArgumentException('Tipo de factor no válido.');
                $clean[$list][] = $item;
            }
        }
        if ($section === 'infracciones') {
            $evaluations = $data['evaluaciones'] ?? [];
            if (!is_array($evaluations) || !array_is_list($evaluations) || count($evaluations) > 200) throw new InvalidArgumentException('Lista de involucrados no válida.');
            $clean['evaluaciones'] = [];
            $seen = [];
            foreach ($evaluations as $evaluation) {
                if (!is_array($evaluation)) throw new InvalidArgumentException('Evaluación no válida.');
                $person = $evaluation['involucrado_id'] ?? null;
                $vehicle = $evaluation['vehiculo_id'] ?? '';
                if (!is_scalar($person) || !ctype_digit((string) $person) || !in_array((int) $person, $ids['involucrado_id'], true) || isset($seen[(int) $person])) throw new InvalidArgumentException('Selecciona un involucrado vigente, sin duplicarlo.');
                if (!is_scalar($vehicle) || ($vehicle !== '' && (!ctype_digit((string) $vehicle) || !in_array((int) $vehicle, $ids['vehiculo_id'], true)))) throw new InvalidArgumentException('Vehículo no válido para este expediente.');
                $status = $evaluation['incurso'] ?? '';
                if (!in_array($status, ['si', 'no'], true)) throw new InvalidArgumentException('Indica si el involucrado se encuentra incurso.');
                $articles = $evaluation['articulos'] ?? [];
                if (!is_array($articles) || !array_is_list($articles) || count($articles) > 200 || ($status === 'si' && !$articles) || ($status === 'no' && $articles)) throw new InvalidArgumentException('Añade artículos solo cuando el involucrado se encuentre incurso.');
                $items = [];
                foreach ($articles as $article) {
                    if (!is_array($article)) throw new InvalidArgumentException('Artículo no válido.');
                    $item = [];
                    foreach (['numero', 'titulo', 'contenido'] as $field) {
                        $value = $article[$field] ?? '';
                        if (!is_scalar($value) || trim((string) $value) === '' || mb_strlen((string) $value) > 20000) throw new InvalidArgumentException('Completa número, título y contenido del artículo.');
                        $item[$field] = trim((string) $value);
                    }
                    $items[] = $item;
                }
                $seen[(int) $person] = true;
                $clean['evaluaciones'][] = ['involucrado_id' => (int) $person, 'vehiculo_id' => $vehicle === '' ? '' : (int) $vehicle, 'incurso' => $status, 'articulos' => $items];
            }
        }
        return $clean;
    }
}
