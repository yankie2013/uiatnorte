<?php
declare(strict_types=1);

namespace App\Support;

final class GeoSearch
{
    public static function searchPeruLima(string $query, int $limit = 6): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(10, $limit));

        $results = self::searchPhoton($query, $limit);
        if ($results !== []) {
            return $results;
        }

        return self::searchNominatim($query, $limit);
    }

    private static function searchPhoton(string $query, int $limit): array
    {
        $url = 'https://photon.komoot.io/api/?q=' . rawurlencode($query . ' Lima Peru') . '&limit=' . $limit;
        $json = self::fetchJson($url);
        $features = is_array($json['features'] ?? null) ? $json['features'] : [];
        if ($features === []) {
            return [];
        }

        $items = [];
        foreach ($features as $feature) {
            $coords = $feature['geometry']['coordinates'] ?? null;
            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            if (!is_array($coords) || count($coords) < 2) {
                continue;
            }

            $lng = isset($coords[0]) ? (float) $coords[0] : null;
            $lat = isset($coords[1]) ? (float) $coords[1] : null;
            if (!is_float($lat) || !is_float($lng)) {
                continue;
            }

            $countryCode = strtoupper(trim((string) ($props['countrycode'] ?? '')));
            if ($countryCode !== 'PE') {
                continue;
            }

            $primary = trim((string) ($props['name'] ?? ''));
            if ($primary === '') {
                $primary = trim((string) ($props['street'] ?? ''));
            }
            if ($primary === '') {
                $primary = 'Ubicación sugerida';
            }

            $secondaryParts = array_filter([
                trim((string) ($props['street'] ?? '')),
                trim((string) ($props['district'] ?? '')),
                trim((string) ($props['city'] ?? '')),
                trim((string) ($props['state'] ?? '')),
            ], static fn (string $value): bool => $value !== '' && $value !== $primary);

            $items[] = [
                'provider' => 'photon',
                'lat' => round($lat, 7),
                'lng' => round($lng, 7),
                'primary' => $primary,
                'secondary' => implode(' · ', array_slice(array_values(array_unique($secondaryParts)), 0, 4)),
            ];
        }

        return array_slice($items, 0, $limit);
    }

    private static function searchNominatim(string $query, int $limit): array
    {
        $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=' . $limit
            . '&countrycodes=pe&accept-language=es&q=' . rawurlencode($query . ', Lima, Perú');

        $rows = self::fetchJson($url);
        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lat = isset($row['lat']) ? (float) $row['lat'] : null;
            $lng = isset($row['lon']) ? (float) $row['lon'] : null;
            if (!is_float($lat) || !is_float($lng)) {
                continue;
            }

            $address = is_array($row['address'] ?? null) ? $row['address'] : [];
            $primary = trim((string) ($address['shop'] ?? $address['amenity'] ?? $address['road'] ?? $row['name'] ?? ''));
            if ($primary === '') {
                $primary = 'Ubicación sugerida';
            }

            $secondaryParts = array_filter([
                trim((string) ($address['road'] ?? '')),
                trim((string) ($address['suburb'] ?? '')),
                trim((string) ($address['city'] ?? '')),
                trim((string) ($address['state'] ?? '')),
            ], static fn (string $value): bool => $value !== '' && $value !== $primary);

            $items[] = [
                'provider' => 'nominatim',
                'lat' => round($lat, 7),
                'lng' => round($lng, 7),
                'primary' => $primary,
                'secondary' => implode(' · ', array_slice(array_values(array_unique($secondaryParts)), 0, 4)),
            ];
        }

        return array_slice($items, 0, $limit);
    }

    private static function fetchJson(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2.5,
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'User-Agent: UIATNorte/1.0',
                ]),
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }
}
