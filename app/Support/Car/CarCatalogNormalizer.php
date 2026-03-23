<?php

namespace App\Support\Car;

/**
 * Normalizes car catalog values for storage and lookup.
 */
final class CarCatalogNormalizer
{
    /**
     * Normalize a car catalog label for display.
     */
    public function normalizeDisplayName(string $value): string
    {
        $value = trim($value);
        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    /**
     * Normalize a car catalog label for search.
     */
    public function normalizeSearchKey(string $value): string
    {
        $value = $this->normalizeDisplayName($value);
        $value = mb_strtolower($value);

        // Remove anything that is not a letter or number.
        // This makes:
        // "Mercedes-Benz" => "mercedesbenz"
        // "C-Class" => "cclass"
        // "MX 5" => "mx5"
        $value = preg_replace('/[^\pL\pN]+/u', '', $value) ?? $value;

        return $value;
    }

    /**
     * Determine whether the normalized haystack contains the normalized needle.
     */
    public function containsNormalized(string $needle, string $haystack): bool
    {
        $needleKey = $this->normalizeSearchKey($needle);
        $haystackKey = $this->normalizeSearchKey($haystack);

        if ($needleKey === '' || $haystackKey === '') {
            return false;
        }

        return str_contains($haystackKey, $needleKey);
    }
}
