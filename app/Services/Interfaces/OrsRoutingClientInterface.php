<?php
namespace App\Services\Interfaces;

interface OrsRoutingClientInterface {

    public function geocode(string $fullAddress): array;

    public function durationSeconds(array $from, array $to): int;
}
