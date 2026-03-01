<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use WdMultiWarehouse\Contracts\WarehouseRepositoryInterface;
use WdMultiWarehouse\Contracts\StockRepositoryInterface;
use WdMultiWarehouse\Contracts\GeocoderInterface;
use WdMultiWarehouse\Models\Warehouse;
use WdMultiWarehouse\Services\DistanceService;
use WdMultiWarehouse\Services\WarehouseSelectionService;

class WarehouseSelectionServiceTest extends TestCase
{
    private MockInterface $warehouseRepository;
    private MockInterface $stockRepository;
    private DistanceService $distanceService;
    private MockInterface $geocoder;
    private WarehouseSelectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->warehouseRepository = Mockery::mock( WarehouseRepositoryInterface::class );
        $this->stockRepository     = Mockery::mock( StockRepositoryInterface::class );
        $this->distanceService     = new DistanceService();
        $this->geocoder            = Mockery::mock( GeocoderInterface::class );

        $this->service = new WarehouseSelectionService(
            $this->warehouseRepository,
            $this->stockRepository,
            $this->distanceService,
            $this->geocoder
        );
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function createWarehouse( int $id, float $lat, float $lon, float $extraCost = 0.0 ): Warehouse
    {
        $row = (object) [
            'id'                  => (string) $id,
            'name'                => "Warehouse {$id}",
            'address'             => "Address {$id}",
            'latitude'            => (string) $lat,
            'longitude'           => (string) $lon,
            'is_active'           => '1',
            'extra_shipping_cost' => (string) $extraCost,
        ];

        return Warehouse::fromDbRow( $row );
    }

    // ─── selectWarehouse ───────────────────────────────────────

    public function testReturnsEmptyWhenNoActiveWarehouses(): void
    {
        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->andReturn( [] );

        $result = $this->service->selectWarehouse( 100, 1, 'Sofia, Bulgaria' );

        $this->assertEmpty( $result );
    }

    public function testSelectsClosestWarehouseWithStock(): void
    {
        // Customer near Sofia (42.70, 23.32)
        // Warehouse 1: Sofia (42.70, 23.32) — closest
        // Warehouse 2: Plovdiv (42.15, 24.75) — farther
        $warehouse1 = $this->createWarehouse( 1, 42.70, 23.32 );
        $warehouse2 = $this->createWarehouse( 2, 42.15, 24.75 );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->andReturn( [ $warehouse1, $warehouse2 ] );

        $this->geocoder
            ->shouldReceive( 'geocode' )
            ->with( 'Sofia, Bulgaria' )
            ->andReturn( [ 42.6977, 23.3219 ] );

        $this->stockRepository
            ->shouldReceive( 'getStockMapForProduct' )
            ->with( 100 )
            ->andReturn( [ 1 => 10, 2 => 20 ] );

        Functions\expect( 'get_option' )->andReturn( '0' );

        $result = $this->service->selectWarehouse( 100, 5, 'Sofia, Bulgaria' );

        $this->assertCount( 1, $result );
        $this->assertSame( 1, $result[0]['warehouse']->getId() );
        $this->assertSame( 5, $result[0]['quantity'] );
        $this->assertTrue( $result[0]['is_closest'] );
        $this->assertSame( 0.0, $result[0]['extra_cost'] );
    }

    public function testSkipsClosestIfNoStockAndPicksNext(): void
    {
        $warehouse1 = $this->createWarehouse( 1, 42.70, 23.32 );
        $warehouse2 = $this->createWarehouse( 2, 42.15, 24.75, 5.00 );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->andReturn( [ $warehouse1, $warehouse2 ] );

        $this->geocoder
            ->shouldReceive( 'geocode' )
            ->andReturn( [ 42.6977, 23.3219 ] );

        // Warehouse 1 has 0 stock, warehouse 2 has 20.
        $this->stockRepository
            ->shouldReceive( 'getStockMapForProduct' )
            ->with( 100 )
            ->andReturn( [ 2 => 20 ] );

        // Extra shipping enabled.
        Functions\expect( 'get_option' )
            ->with( 'wdmw_extra_shipping_enabled', '0' )
            ->andReturn( '1' );

        $result = $this->service->selectWarehouse( 100, 5, 'Sofia, Bulgaria' );

        $this->assertCount( 1, $result );
        $this->assertSame( 2, $result[0]['warehouse']->getId() );
        $this->assertSame( 5, $result[0]['quantity'] );
        $this->assertFalse( $result[0]['is_closest'] );
        $this->assertSame( 5.00, $result[0]['extra_cost'] );
    }

    public function testNoExtraCostWhenSettingDisabled(): void
    {
        $warehouse1 = $this->createWarehouse( 1, 42.70, 23.32 );
        $warehouse2 = $this->createWarehouse( 2, 42.15, 24.75, 5.00 );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->andReturn( [ $warehouse1, $warehouse2 ] );

        $this->geocoder
            ->shouldReceive( 'geocode' )
            ->andReturn( [ 42.6977, 23.3219 ] );

        $this->stockRepository
            ->shouldReceive( 'getStockMapForProduct' )
            ->andReturn( [ 2 => 20 ] );

        // Extra shipping DISABLED.
        Functions\expect( 'get_option' )
            ->with( 'wdmw_extra_shipping_enabled', '0' )
            ->andReturn( '0' );

        $result = $this->service->selectWarehouse( 100, 5, 'Sofia, Bulgaria' );

        $this->assertSame( 0.0, $result[0]['extra_cost'] );
    }

    public function testReturnsEmptyWhenNoWarehouseHasStock(): void
    {
        $warehouse1 = $this->createWarehouse( 1, 42.70, 23.32 );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->andReturn( [ $warehouse1 ] );

        $this->geocoder
            ->shouldReceive( 'geocode' )
            ->andReturn( [ 42.6977, 23.3219 ] );

        $this->stockRepository
            ->shouldReceive( 'getStockMapForProduct' )
            ->andReturn( [] );

        $result = $this->service->selectWarehouse( 100, 1, 'Sofia, Bulgaria' );

        $this->assertEmpty( $result );
    }

    // ─── Split allocation ─────────────────────────────────────

    public function testSplitsQuantityAcrossWarehousesWhenNeeded(): void
    {
        // Warehouse 1 (closest): has 5 items.
        // Warehouse 2 (farther): has 7 items.
        // Customer orders 10 → should take 5 from WH1 + 5 from WH2.
        $warehouse1 = $this->createWarehouse( 1, 42.70, 23.32 );
        $warehouse2 = $this->createWarehouse( 2, 42.15, 24.75, 3.00 );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->andReturn( [ $warehouse1, $warehouse2 ] );

        $this->geocoder
            ->shouldReceive( 'geocode' )
            ->andReturn( [ 42.6977, 23.3219 ] );

        $this->stockRepository
            ->shouldReceive( 'getStockMapForProduct' )
            ->with( 100 )
            ->andReturn( [ 1 => 5, 2 => 7 ] );

        Functions\expect( 'get_option' )
            ->with( 'wdmw_extra_shipping_enabled', '0' )
            ->andReturn( '1' );

        $result = $this->service->selectWarehouse( 100, 10, 'Sofia, Bulgaria' );

        $this->assertCount( 2, $result );

        // First allocation: closest warehouse, 5 items.
        $this->assertSame( 1, $result[0]['warehouse']->getId() );
        $this->assertSame( 5, $result[0]['quantity'] );
        $this->assertTrue( $result[0]['is_closest'] );
        $this->assertSame( 0.0, $result[0]['extra_cost'] );

        // Second allocation: farther warehouse, 5 items (remainder).
        $this->assertSame( 2, $result[1]['warehouse']->getId() );
        $this->assertSame( 5, $result[1]['quantity'] );
        $this->assertFalse( $result[1]['is_closest'] );
        $this->assertSame( 3.00, $result[1]['extra_cost'] );
    }

    // ─── Geocoding fallback ────────────────────────────────────

    public function testFallsBackToFirstAvailableWhenGeocodingFails(): void
    {
        $warehouse1 = $this->createWarehouse( 1, 42.70, 23.32 );
        $warehouse2 = $this->createWarehouse( 2, 42.15, 24.75 );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->andReturn( [ $warehouse1, $warehouse2 ] );

        $this->geocoder
            ->shouldReceive( 'geocode' )
            ->andReturn( null );

        // Warehouse 1 has no stock, warehouse 2 does.
        $this->stockRepository
            ->shouldReceive( 'getStockMapForProduct' )
            ->with( 100 )
            ->andReturn( [ 2 => 15 ] );

        $result = $this->service->selectWarehouse( 100, 3, 'Bad Address' );

        $this->assertCount( 1, $result );
        $this->assertSame( 2, $result[0]['warehouse']->getId() );
        $this->assertSame( 3, $result[0]['quantity'] );
        $this->assertTrue( $result[0]['is_closest'] );
        $this->assertSame( 0.0, $result[0]['extra_cost'] );
    }

    public function testFallbackSplitsAcrossWarehouses(): void
    {
        $warehouse1 = $this->createWarehouse( 1, 42.70, 23.32 );
        $warehouse2 = $this->createWarehouse( 2, 42.15, 24.75, 4.00 );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->andReturn( [ $warehouse1, $warehouse2 ] );

        $this->geocoder
            ->shouldReceive( 'geocode' )
            ->andReturn( null );

        $this->stockRepository
            ->shouldReceive( 'getStockMapForProduct' )
            ->with( 100 )
            ->andReturn( [ 1 => 4, 2 => 8 ] );

        Functions\expect( 'get_option' )
            ->with( 'wdmw_extra_shipping_enabled', '0' )
            ->andReturn( '1' );

        $result = $this->service->selectWarehouse( 100, 7, 'Bad Address' );

        $this->assertCount( 2, $result );

        // First allocation: primary warehouse, 4 items.
        $this->assertSame( 4, $result[0]['quantity'] );
        $this->assertTrue( $result[0]['is_closest'] );
        $this->assertSame( 0.0, $result[0]['extra_cost'] );

        // Second allocation: non-primary, 3 items, extra cost applied.
        $this->assertSame( 3, $result[1]['quantity'] );
        $this->assertFalse( $result[1]['is_closest'] );
        $this->assertSame( 4.00, $result[1]['extra_cost'] );
    }

    // ─── Warehouses without coordinates ────────────────────────

    public function testSkipsWarehousesWithoutCoordinates(): void
    {
        // Warehouse without coordinates.
        $noCoords = Warehouse::fromDbRow( (object) [
            'id'                  => '1',
            'name'                => 'No Coords',
            'address'             => 'Somewhere',
            'latitude'            => null,
            'longitude'           => null,
            'is_active'           => '1',
            'extra_shipping_cost' => '0',
        ] );

        $withCoords = $this->createWarehouse( 2, 42.15, 24.75 );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->andReturn( [ $noCoords, $withCoords ] );

        $this->geocoder
            ->shouldReceive( 'geocode' )
            ->andReturn( [ 42.6977, 23.3219 ] );

        $this->stockRepository
            ->shouldReceive( 'getStockMapForProduct' )
            ->andReturn( [ 2 => 10 ] );

        Functions\expect( 'get_option' )->andReturn( '0' );

        $result = $this->service->selectWarehouse( 100, 3, 'Sofia' );

        $this->assertCount( 1, $result );
        $this->assertSame( 2, $result[0]['warehouse']->getId() );
    }

    // ─── selectWarehousesForCart ────────────────────────────────

    public function testSelectWarehousesForCartAllocatesPerProduct(): void
    {
        $warehouse1 = $this->createWarehouse( 1, 42.70, 23.32 );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->andReturn( [ $warehouse1 ] );

        $this->geocoder
            ->shouldReceive( 'geocode' )
            ->andReturn( [ 42.6977, 23.3219 ] );

        // Product 100 has stock, product 200 does not.
        $this->stockRepository
            ->shouldReceive( 'getStockMapForProduct' )
            ->with( 100 )
            ->andReturn( [ 1 => 10 ] );

        $this->stockRepository
            ->shouldReceive( 'getStockMapForProduct' )
            ->with( 200 )
            ->andReturn( [] );

        Functions\expect( 'get_option' )->andReturn( '0' );

        $cartItems   = [ 100 => 2, 200 => 1 ];
        $allocations = $this->service->selectWarehousesForCart( $cartItems, 'Sofia' );

        $this->assertArrayHasKey( 100, $allocations );
        $this->assertArrayNotHasKey( 200, $allocations );
        $this->assertSame( 2, $allocations[100][0]['quantity'] );
    }
}
