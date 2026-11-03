<?php
declare(strict_types=1);
namespace App\Api;

use RuntimeException;

/** Shared local budget before session creation or any PDO connection. */
final class RequestLimiter
{
    public static function consume(string $directory, string $address, string $secret, ?int $now = null): bool
    {
        if (!filter_var($address, FILTER_VALIDATE_IP) || strlen($secret) < 32) throw new RuntimeException('Request limiter configuration unavailable');
        // Normalize IPv6 and store only a keyed digest, never the IP or credential.
        $key = hash_hmac('sha256', (string)inet_pton($address), $secret);
        $path = $directory . '/request-rate.json';
        if (is_link($path)) throw new RuntimeException('Request limiter unavailable');
        $file = fopen($path, 'c+');
        if (!$file) throw new RuntimeException('Request limiter unavailable');
        try {
            if (!chmod($path, 0600) || !flock($file, LOCK_EX)) throw new RuntimeException('Request limiter unavailable');
            $raw = stream_get_contents($file, 1048577);
            if ($raw === false || strlen($raw) > 1048576) throw new RuntimeException('Request limiter unavailable');
            $window = intdiv($now ?? time(), 60);
            if ($raw === '') {
                $state = ['window'=>$window, 'counts'=>[]];
            } else {
                $state = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($state) || !isset($state['window'],$state['counts']) || !is_int($state['window']) || !is_array($state['counts']) || count($state['counts']) > 4096) throw new RuntimeException('Request limiter unavailable');
                if ($state['window'] !== $window) $state = ['window'=>$window, 'counts'=>[]];
            }
            $count = $state['counts'][$key] ?? 0;
            if (!is_int($count) || $count < 0) throw new RuntimeException('Request limiter unavailable');
            if ($count >= 300 || ($count === 0 && count($state['counts']) >= 4096)) return false;
            $state['counts'][$key] = $count + 1;
            $encoded = json_encode($state, JSON_THROW_ON_ERROR);
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $encoded) !== strlen($encoded) || !fflush($file)) throw new RuntimeException('Request limiter unavailable');
            return true;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
