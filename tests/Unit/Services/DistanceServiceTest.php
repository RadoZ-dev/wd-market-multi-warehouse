<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WdMultiWarehouse\Services\DistanceService;

class DistanceServiceTest extends TestCase
{
    private DistanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DistanceService();
    }

    public function testSamePointReturnsZero(): void
    {
        $distance = $this->service->calculateDistance(
            42.6977,
            23.3219,
            42.6977,
            23.3219
        );

        $this->assertEqualsWithDelta( 0.0, $distance, 0.001 );
    }

    public function testSofiaToPlovdiv(): void
    {
        // Sofia (42.6977, 23.3219) → Plovdiv (42.1500, 24.7500)
        // Expected ~132 km
        $distance = $this->service->calculateDistance(
            42.6977,
            23.3219,
            42.1500,
            24.7500
        );

        $this->assertEqualsWithDelta( 132.0, $distance, 5.0 );
    }

    public function testLondonToNewYork(): void
    {
        // London (51.5074, -0.1278) → New York (40.7128, -74.0060)
        // Expected ~5570 km
        $distance = $this->service->calculateDistance(
            51.5074,
            -0.1278,
            40.7128,
            -74.0060
        );

        $this->assertEqualsWithDelta( 5570.0, $distance, 30.0 );
    }

    public function testDistanceIsSymmetric(): void
    {
        $aToB = $this->service->calculateDistance( 42.6977, 23.3219, 42.1500, 24.7500 );
        $bToA = $this->service->calculateDistance( 42.1500, 24.7500, 42.6977, 23.3219 );

        $this->assertEqualsWithDelta( $aToB, $bToA, 0.001 );
    }

    public function testDistanceIsAlwaysPositive(): void
    {
        $distance = $this->service->calculateDistance(
            -33.8688,
            151.2093,
            51.5074,
            -0.1278
        );

        $this->assertGreaterThan( 0.0, $distance );
    }

    public function testNegativeCoordinatesWork(): void
    {
        // Sydney (-33.8688, 151.2093) → Buenos Aires (-34.6037, -58.3816)
        // Expected ~11800 km
        $distance = $this->service->calculateDistance(
            -33.8688,
            151.2093,
            -34.6037,
            -58.3816
        );

        $this->assertEqualsWithDelta( 11800.0, $distance, 100.0 );
    }
}
