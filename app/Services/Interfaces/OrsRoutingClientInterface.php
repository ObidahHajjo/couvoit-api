<?php

namespace App\Services\Interfaces;

use Throwable;

/**
 * Contract for OpenRouteService routing client.
 */
interface OrsRoutingClientInterface
{
    /**
     * Geocode a full address string into geographic coordinates.
     *
     * Expected return format:
     * [
     *     'lng' => float,
     *     'lat' => float,
     * ]
     *
     * @param string $fullAddress
     *
     * @return array{lng: float, lat: float}
     *
     * @throws Throwable If the external API call fails or response is invalid.
     */
    public function geocode(string $fullAddress): array;

    /**
     * Calculate route duration in seconds between two coordinates.
     *
     * Expected input format:
     * [
     *     'lng' => float,
     *     'lat' => float,
     * ]
     *
     * @param array{lng: float, lat: float} $from
     * @param array{lng: float, lat: float} $to
     *
     * @return int Duration in seconds.
     *
     * @throws Throwable If the external API call fails or response is invalid.
     */
    public function durationSeconds(array $from, array $to): int;
}
