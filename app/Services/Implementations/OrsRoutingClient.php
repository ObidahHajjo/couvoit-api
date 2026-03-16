<?php

namespace App\Services\Implementations;

use App\Services\Interfaces\OrsRoutingClientInterface;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

final class OrsRoutingClient implements OrsRoutingClientInterface
{
    public function geocode(string $fullAddress): array
    {
        $url = config('services.ors.geocode_url');
        $key = config('services.ors.key');

        if (!is_string($url) || $url === '') {
            throw new RuntimeException('ORS geocode_url is missing (services.ors.geocode_url).');
        }

        if (!is_string($key) || $key === '') {
            throw new RuntimeException('ORS key is missing (services.ors.key).');
        }

        $json = Http::acceptJson()
            ->withHeaders(['Authorization' => $key])
            ->get($url, [
                'text' => $fullAddress,
                'size' => 1,
            ])
            ->throw()
            ->json();

        $coords = data_get($json, 'features.0.geometry.coordinates');

        if (!is_array($coords) || count($coords) < 2) {
            throw new RuntimeException("Geocoding failed for: $fullAddress");
        }

        return [
            'lng' => (float) $coords[0],
            'lat' => (float) $coords[1],
        ];
    }

    public function routeSummary(array $from, array $to): array
    {
        $url = config('services.ors.directions_url');
        $key = config('services.ors.key');

        if (!is_string($url) || $url === '') {
            throw new RuntimeException('ORS directions_url is missing (services.ors.directions_url).');
        }

        if (!is_string($key) || $key === '') {
            throw new RuntimeException('ORS key is missing (services.ors.key).');
        }

        foreach (['lng', 'lat'] as $k) {
            if (!array_key_exists($k, $from) || !array_key_exists($k, $to)) {
                throw new InvalidArgumentException("Missing coordinate key '$k' (expected ['lng'=>..,'lat'=>..]).");
            }
        }

        $json = Http::acceptJson()
            ->withHeaders(['Authorization' => $key])
            ->post($url, [
                'coordinates' => [
                    [(float) $from['lng'], (float) $from['lat']],
                    [(float) $to['lng'], (float) $to['lat']],
                ],
                'instructions' => false,
            ])
            ->throw()
            ->json();

        $durationSeconds = data_get($json, 'routes.0.summary.duration');
        $distanceMeters = data_get($json, 'routes.0.summary.distance');

        if (!is_numeric($durationSeconds)) {
            throw new RuntimeException('Routing failed: duration missing.');
        }

        if (!is_numeric($distanceMeters)) {
            throw new RuntimeException('Routing failed: distance missing.');
        }

        return [
            'duration_seconds' => (int) round((float) $durationSeconds),
            'distance_meters' => (float) $distanceMeters,
            'distance_km' => round(((float) $distanceMeters) / 1000, 2),
        ];
    }

    public function durationSeconds(array $from, array $to): int
    {
        return $this->routeSummary($from, $to)['duration_seconds'];
    }
}
