<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Contracts;

interface GeocoderInterface
{
    /**
     * Geocode an address and return [latitude, longitude] or null on failure.
     *
     * @return array{0: float, 1: float}|null
     */
    public function geocode( string $address ): ?array;
}
