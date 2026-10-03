<?php
declare(strict_types=1);

namespace App\Support;

final class AccidentNavigation
{
    private const FILTERS = [
        'q', 'desde', 'hasta', 'comisaria_id', 'persona', 'distrito', 'vehiculo',
        'registro_sidpol', 'nro_informe_policial', 'anio', 'tipo_registro',
        'estado', 'orden', 'favoritos', 'ver_todos',
    ];

    public static function listUrl(string $raw): ?string
    {
        $parts = parse_url($raw);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])
            || !in_array($parts['path'] ?? '', ['accidente_listar.php', '/accidente_listar.php', '/uiatnorte/accidente_listar.php'], true)) {
            return null;
        }
        parse_str((string) ($parts['query'] ?? ''), $query);
        $filters = [];
        foreach (self::FILTERS as $key) {
            if (isset($query[$key]) && is_scalar($query[$key])) {
                $filters[$key] = substr((string) $query[$key], 0, 250);
            }
        }
        return 'accidente_listar.php' . ($filters ? '?' . http_build_query($filters) : '');
    }

    public static function districtUrl(string $district): string
    {
        return 'accidente_listar.php?' . http_build_query(['distrito' => $district, 'estado' => 'todos']);
    }

    public static function caseUrl(int $case, string $listUrl, array $extra = []): string
    {
        return 'accidente_vista_tabs.php?' . http_build_query(array_merge(
            ['accidente_id' => $case, 'lista' => $listUrl], $extra
        ));
    }

    /** Each item has a label and a local URL; the current item has no URL. */
    public static function breadcrumbs(string $listUrl, ?string $caseLabel = null): array
    {
        parse_str((string) (parse_url($listUrl, PHP_URL_QUERY) ?: ''), $query);
        $district = is_string($query['distrito'] ?? null) ? trim($query['distrito']) : '';
        $station = $district !== '' && is_scalar($query['comisaria_id'] ?? null) && (string)$query['comisaria_id'] !== '';
        $special = ($query['favoritos'] ?? '') === '1' || ($query['ver_todos'] ?? '') === '1';
        $showList = $station || $special || $caseLabel !== null;
        $breadcrumbs = [['label' => 'Gestión de la información', 'url' => 'index.php']];
        $breadcrumbs[] = ['label' => 'Distritos', 'url' => ($district !== '' || $special || $caseLabel !== null) ? 'accidente_listar.php' : null];
        if ($district !== '') {
            $breadcrumbs[] = ['label' => 'Comisarías', 'url' => $showList ? self::districtUrl($district) : null];
        }
        if ($showList) {
            $breadcrumbs[] = ['label' => 'Lista de accidentes', 'url' => $caseLabel !== null ? $listUrl : null];
        }
        if ($caseLabel !== null) {
            $breadcrumbs[] = ['label' => $caseLabel, 'url' => null];
        }
        return $breadcrumbs;
    }
}
