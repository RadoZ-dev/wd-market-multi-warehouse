<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Services;

use WdMultiWarehouse\Contracts\DistanceCalculatorInterface;

class DistanceService implements DistanceCalculatorInterface
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Calculate distance in km between two coordinate pairs using the Haversine formula.
     */
    public function calculateDistance(
        float $latitudeFrom,
        float $longitudeFrom,
        float $latitudeTo,
        float $longitudeTo
    ): float {
        $latitudeFromRadians = deg2rad( $latitudeFrom );
        $latitudeToRadians   = deg2rad( $latitudeTo );
        $latitudeDelta       = deg2rad( $latitudeTo - $latitudeFrom );
        $longitudeDelta      = deg2rad( $longitudeTo - $longitudeFrom );

        $a = sin( $latitudeDelta / 2 ) ** 2
            + cos( $latitudeFromRadians ) * cos( $latitudeToRadians ) * sin( $longitudeDelta / 2 ) ** 2;

        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        return self::EARTH_RADIUS_KM * $c;
    }
}
