<?php
declare(strict_types=1);

function word_hyphen_list($value): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim((string) ($value ?? '')));
    if ($text === '') {
        return '';
    }

    $items = preg_split('/\n+/u', $text) ?: [];
    $lines = [];
    foreach ($items as $item) {
        $item = trim((string) preg_replace('/^[\-\x{2022}\*\s]+/u', '', (string) $item));
        if ($item !== '') {
            $lines[] = '- ' . $item;
        }
    }

    return implode("\n", $lines);
}
