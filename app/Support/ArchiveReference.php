<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class ArchiveReference
{
    public const SUFFIX = 'COMOPPOL-DIRNOS-PNP/DIRTTSV-DIVPIAT-UIAT-NORTE';

    public static function report(string $storedNumber): string
    {
        $value = trim($storedNumber);
        $suffix = preg_quote(self::SUFFIX, '/');
        if (!preg_match('/^(\d{1,8})\s*[-\/]\s*(\d{4})(?:-'.$suffix.')?$/iD', $value, $match)) {
            throw new RuntimeException('Complete el N° de informe policial en el expediente con el formato N°-AÑO antes de enviarlo a Archivo.');
        }
        return self::format($match[1], $match[2]);
    }

    public static function office(string $number, string $year): string
    {
        return self::format($number, $year);
    }

    private static function format(string $number, string $year): string
    {
        $number = trim($number);
        $year = trim($year);
        if (!preg_match('/^\d{1,8}$/D', $number) || !preg_match('/^20\d{2}$/D', $year)) {
            throw new RuntimeException('Ingrese un número de oficio y un año de cuatro cifras válidos.');
        }
        return $number.'-'.$year.'-'.self::SUFFIX;
    }
}
