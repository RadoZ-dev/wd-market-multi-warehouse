<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Contracts;

interface DistanceCalculatorInterface
{
    /**
     * Calculate distance in kilometers between two points.
     */
    public function calculateDistance(
        float $latitudeFrom,
        float $longitudeFrom,
        float $latitudeTo,
        float $longitudeTo
    ): float;
}
