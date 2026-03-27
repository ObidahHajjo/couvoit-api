<?php

namespace App\Support\Car;

final class LicensePlateFormatter
{
    public const DISPLAY_PATTERN = '/^[A-Z]{2}-\d{3}-[A-Z]{2}$/';

    public static function normalize(string $value): string
    {
        $trimmed = strtoupper(trim($value));
        $condensed = preg_replace('/[^A-Z0-9]+/', '', $trimmed) ?? '';

        if (preg_match('/^[A-Z]{2}\d{3}[A-Z]{2}$/', $condensed) === 1) {
            return substr($condensed, 0, 2).'-'.substr($condensed, 2, 3).'-'.substr($condensed, 5, 2);
        }

        return $trimmed;
    }
}
