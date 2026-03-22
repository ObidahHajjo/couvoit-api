<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetRequestLocale
{
    /**
     * @var array<int, string>
     */
    private const SUPPORTED_LOCALES = ['en', 'fr', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $header = (string) $request->header('Accept-Language', '');

        foreach ($this->parseAcceptLanguage($header) as $locale) {
            $normalized = $this->normalizeLocale($locale);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        $preferred = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);

        if (is_string($preferred) && in_array($preferred, self::SUPPORTED_LOCALES, true)) {
            return $preferred;
        }

        $fallback = (string) config('app.fallback_locale', 'en');

        return in_array($fallback, self::SUPPORTED_LOCALES, true)
            ? $fallback
            : self::SUPPORTED_LOCALES[0];
    }

    /**
     * @return array<int, string>
     */
    private function parseAcceptLanguage(string $header): array
    {
        if ($header === '') {
            return [];
        }

        $locales = [];

        foreach (explode(',', $header) as $index => $part) {
            $segments = explode(';', trim($part));
            $locale = trim($segments[0] ?? '');

            if ($locale === '') {
                continue;
            }

            $quality = 1.0;

            foreach (array_slice($segments, 1) as $segment) {
                $segment = trim($segment);

                if (str_starts_with($segment, 'q=')) {
                    $quality = (float) substr($segment, 2);
                    break;
                }
            }

            $locales[] = [
                'locale' => $locale,
                'quality' => $quality,
                'index' => $index,
            ];
        }

        usort($locales, static function (array $left, array $right): int {
            if ($left['quality'] === $right['quality']) {
                return $left['index'] <=> $right['index'];
            }

            return $right['quality'] <=> $left['quality'];
        });

        return array_map(static fn (array $item): string => $item['locale'], $locales);
    }

    private function normalizeLocale(string $locale): ?string
    {
        $normalized = strtolower(str_replace('_', '-', trim($locale)));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, self::SUPPORTED_LOCALES, true)) {
            return $normalized;
        }

        $language = explode('-', $normalized)[0];

        return in_array($language, self::SUPPORTED_LOCALES, true)
            ? $language
            : null;
    }
}
