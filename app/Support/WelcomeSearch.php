<?php
declare(strict_types=1);
namespace App\Support;

/** Input and links for the authenticated home search; query permissions stay in the listing. */
final class WelcomeSearch
{
    public const CRITERIA = [
        'persona' => 'Nombres y apellidos',
        'vehiculo' => 'Placa vehicular',
        'distrito' => 'Distrito',
        'comisaria_id' => 'Comisaría',
    ];

    public static function input(array $query): array
    {
        $criterion = is_string($query['criterio'] ?? null) ? $query['criterio'] : 'persona';
        $error = '';
        if (!isset(self::CRITERIA[$criterion])) {
            $criterion = 'persona';
            $error = 'Selecciona un criterio de búsqueda válido.';
        }
        $raw = $query['valor'] ?? '';
        $value = is_string($raw) ? trim($raw) : '';
        if (!is_string($raw) || strlen($value) > 200) {
            $error = 'Ingresa una búsqueda de hasta 200 caracteres.';
            $value = '';
        }
        $page = filter_var($query['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>100000]]) ?: 1;
        return ['criterion'=>$criterion, 'value'=>$value, 'page'=>$page, 'error'=>$error, 'submitted'=>array_key_exists('valor', $query)];
    }

    public static function url(array $search, int $page): string
    {
        return 'index.php?' . http_build_query(['criterio'=>$search['criterion'], 'valor'=>$search['value'], 'pagina'=>$page]);
    }
}
