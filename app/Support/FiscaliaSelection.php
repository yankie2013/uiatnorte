<?php
declare(strict_types=1);
namespace App\Support;

final class FiscaliaSelection
{
    public const OFFICES = [
        'transito' => ['Fiscalía Corporativa de Tránsito y Seguridad Vial', 'Lima Norte'],
        'puente' => ['Fiscalía Provincial Penal Corporativa de Puente Piedra', 'Lima Noroeste'],
        'santa' => ['Fiscalía Provincial Penal Corporativa de Santa Rosa - Ancón', 'Lima Noroeste'],
        'canta' => ['Fiscalía Provincial Penal de Canta', 'Lima Norte'],
    ];
    public const DESPACHOS = [1 => 'Primer Despacho', 2 => 'Segundo Despacho', 3 => 'Tercer Despacho', 4 => 'Cuarto Despacho'];

    public static function describe(string $name): ?array
    {
        $text = strtr(mb_strtolower($name, 'UTF-8'), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);
        $office = match (true) {
            str_contains($text, 'transito') && str_contains($text, 'seguridad vial') => 'transito',
            str_contains($text, 'puente piedra') => 'puente',
            str_contains($text, 'santa rosa') || str_contains($text, 'ancon') => 'santa',
            str_contains($text, 'canta') => 'canta',
            default => null,
        };
        if ($office === null) return null;
        $dispatch = 0;
        foreach ([1=>'primer',2=>'segund',3=>'tercer',4=>'cuart'] as $number => $word) {
            if (preg_match('/\b'.$word.'\w*\s+despacho\b/u', $text)) $dispatch = $number;
        }
        $number = 0;
        if (preg_match('/\b([1-9]\d*)\s*[°ºª.]?\s*fiscalia/u', $text, $matches)) $number = (int)$matches[1];
        else foreach ([1=>'primera',2=>'segunda',3=>'tercera',4=>'cuarta'] as $n => $word) {
            if (preg_match('/\b'.$word.'\s+fiscalia\b/u', $text)) $number = $n;
        }
        return ['office'=>$office,'dispatch'=>$dispatch,'number'=>$number,'name'=>self::name($office,$dispatch,$number)];
    }

    public static function sameDispatch(?array $a, ?array $b): bool
    {
        return $a !== null && $b !== null && $a['office'] === $b['office'] && $a['dispatch'] === $b['dispatch']
            && (!in_array($a['office'], ['puente', 'santa'], true) || $a['number'] === $b['number']);
    }

    public static function name(string $office, int $dispatch, int $number): string
    {
        [$name, $district] = self::OFFICES[$office];
        return ($dispatch ? self::DESPACHOS[$dispatch].' de la ' : '')
            . ($number ? $number.'° ' : '') . $name . ' del Distrito Fiscal de '.$district;
    }

    public static function officeForDistrict(string $dep, string $prov, string $dist): ?string
    {
        if ($dep !== '15') return null;
        if ($prov === '04' && in_array($dist,['01','02','03','04','05','06','07'],true)) return 'canta';
        if ($prov !== '01') return null;
        return match ($dist) {
            '12','35','10','17','06' => 'transito',
            '25' => 'puente',
            '02','39' => 'santa',
            default => null,
        };
    }
}
