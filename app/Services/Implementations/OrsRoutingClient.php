<?php

namespace App\Services\Implementations;

use App\Services\Interfaces\OrsRoutingClientInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OrsRoutingClient implements OrsRoutingClientInterface
{
    public function geocode(string $fullAddress): array
    {
        $url = config('services.ors.geocode_url');
        $key = config('services.ors.key');

        if (!is_string($url) || $url === '') {
            throw new \RuntimeException('ORS geocode_url is missing (services.ors.geocode_url).');
        }
        if (!is_string($key) || $key === '') {
            throw new \RuntimeException('ORS key is missing (services.ors.key).');
        }

        $json = Http::acceptJson()
            ->withHeaders(['Authorization' => $key])
            ->get($url, [
                'text' => $fullAddress,
                'size' => 1,
            ])
            ->throw()
            ->json();

        $coords = data_get($json, 'features.0.geometry.coordinates'); // [lng, lat]

        if (!is_array($coords) || count($coords) < 2) {
            throw new \RuntimeException("Geocoding failed for: {$fullAddress}");
        }

        return ['lng' => (float) $coords[0], 'lat' => (float) $coords[1]];
    }

    public function durationSeconds(array $from, array $to): int
    {
        $url = config('services.ors.directions_url');
        $key = config('services.ors.key');

        if (!is_string($url) || $url === '') {
            throw new \RuntimeException('ORS directions_url is missing (services.ors.directions_url).');
        }
        if (!is_string($key) || $key === '') {
            throw new \RuntimeException('ORS key is missing (services.ors.key).');
        }

        // basic payload validation (avoid undefined index)
        foreach (['lng', 'lat'] as $k) {
            if (!array_key_exists($k, $from) || !array_key_exists($k, $to)) {
                throw new \InvalidArgumentException("Missing coordinate key '{$k}' (expected ['lng'=>..,'lat'=>..]).");
            }
        }

        $json = Http::acceptJson()
            ->withHeaders(['Authorization' => $key])
            ->post($url, [
                'coordinates' => [
                    [(float) $from['lng'], (float) $from['lat']],
                    [(float) $to['lng'], (float) $to['lat']],
                ],
                // optional but recommended:
                'instructions' => false,
            ])
            ->throw()
            ->json();

        // ✅ correct path for your response
        $seconds = data_get($json, 'routes.0.summary.duration');

        if (!is_numeric($seconds)) {
            throw new \RuntimeException('Routing failed: duration missing.');
        }

        return (int) round((float) $seconds);
    }
}
