<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use WdMultiWarehouse\Contracts\StockRepositoryInterface;
use WdMultiWarehouse\Contracts\WarehouseRepositoryInterface;
use WdMultiWarehouse\Models\WarehouseStock;
use WdMultiWarehouse\Services\StockService;

class StockServiceTest extends TestCase
{
    private MockInterface $stockRepository;
    private MockInterface $warehouseRepository;
    private StockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->stockRepository     = Mockery::mock( StockRepositoryInterface::class );
        $this->warehouseRepository = Mockery::mock( WarehouseRepositoryInterface::class );

        $this->service = new StockService(
            $this->stockRepository,
            $this->warehouseRepository
        );
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ─── registerHooks ─────────────────────────────────────────

    public function testRegisterHooksAddsThreeFilters(): void
    {
        Functions\expect( 'add_filter' )->times( 3 );

        $this->service->registerHooks();

        $this->assertTrue( true ); // Verification handled by Mockery expectations.
    }

    // ─── filterStockQuantity ───────────────────────────────────

    public function testFilterStockQuantityReturnsTotalFromWarehouses(): void
    {
        $product = Mockery::mock( 'WC_Product' );
        $product->shouldReceive( 'get_id' )->andReturn( 100 );

        $this->stockRepository
            ->shouldReceive( 'getTotalStockForProduct' )
            ->with( 100 )
            ->andReturn( 75 );

        $result = $this->service->filterStockQuantity( 10, $product );

        $this->assertSame( 75, $result );
    }

    public function testFilterStockQuantityReturnsOriginalForNonProduct(): void
    {
        $result = $this->service->filterStockQuantity( 10, 'not_a_product' );

        $this->assertSame( 10, $result );
    }

    // ─── filterStockStatus ─────────────────────────────────────

    public function testFilterStockStatusReturnsInstockWhenPositive(): void
    {
        $product = Mockery::mock( 'WC_Product' );
        $product->shouldReceive( 'get_id' )->andReturn( 100 );

        $this->stockRepository
            ->shouldReceive( 'getTotalStockForProduct' )
            ->with( 100 )
            ->andReturn( 25 );

        $this->assertSame( 'instock', $this->service->filterStockStatus( 'outofstock', $product ) );
    }

    public function testFilterStockStatusReturnsOutofstockWhenZero(): void
    {
        $product = Mockery::mock( 'WC_Product' );
        $product->shouldReceive( 'get_id' )->andReturn( 100 );

        $this->stockRepository
            ->shouldReceive( 'getTotalStockForProduct' )
            ->with( 100 )
            ->andReturn( 0 );

        $this->assertSame( 'outofstock', $this->service->filterStockStatus( 'instock', $product ) );
    }

    // ─── forceManageStock ──────────────────────────────────────

    public function testForceManageStockReturnsTrueWhenStockEntriesExist(): void
    {
        $product = Mockery::mock( 'WC_Product' );
        $product->shouldReceive( 'get_id' )->andReturn( 100 );

        $stock = new WarehouseStock( 1, 10, 100, 50 );

        $this->stockRepository
            ->shouldReceive( 'findByProduct' )
            ->with( 100 )
            ->andReturn( [ $stock ] );

        $this->assertTrue( $this->service->forceManageStock( false, $product ) );
    }

    public function testForceManageStockReturnsOriginalWhenNoEntries(): void
    {
        $product = Mockery::mock( 'WC_Product' );
        $product->shouldReceive( 'get_id' )->andReturn( 100 );

        $this->stockRepository
            ->shouldReceive( 'findByProduct' )
            ->with( 100 )
            ->andReturn( [] );

        $this->assertFalse( $this->service->forceManageStock( false, $product ) );
    }

    // ─── getTotalStock ─────────────────────────────────────────

    public function testGetTotalStockDelegatesToRepository(): void
    {
        $this->stockRepository
            ->shouldReceive( 'getTotalStockForProduct' )
            ->with( 100 )
            ->andReturn( 42 );

        $this->assertSame( 42, $this->service->getTotalStock( 100 ) );
    }

    // ─── getStockForWarehouse ──────────────────────────────────

    public function testGetStockForWarehouseReturnsQuantity(): void
    {
        $stock = new WarehouseStock( 1, 10, 100, 30 );

        $this->stockRepository
            ->shouldReceive( 'findByWarehouseAndProduct' )
            ->with( 10, 100 )
            ->andReturn( $stock );

        $this->assertSame( 30, $this->service->getStockForWarehouse( 10, 100 ) );
    }

    public function testGetStockForWarehouseReturnsZeroWhenNotFound(): void
    {
        $this->stockRepository
            ->shouldReceive( 'findByWarehouseAndProduct' )
            ->with( 10, 100 )
            ->andReturn( null );

        $this->assertSame( 0, $this->service->getStockForWarehouse( 10, 100 ) );
    }

    // ─── setStockForWarehouse ──────────────────────────────────

    public function testSetStockCreatesNewEntryWhenNoneExists(): void
    {
        $this->stockRepository
            ->shouldReceive( 'findByWarehouseAndProduct' )
            ->with( 10, 100 )
            ->andReturn( null );

        $this->stockRepository
            ->shouldReceive( 'save' )
            ->once()
            ->with( Mockery::on( function ( WarehouseStock $stock ) {
                return $stock->getWarehouseId() === 10
                    && $stock->getProductId() === 100
                    && $stock->getQuantity() === 50;
            } ) )
            ->andReturn( 1 );

        // syncWooCommerceStock calls
        $this->stockRepository
            ->shouldReceive( 'getTotalStockForProduct' )
            ->with( 100 )
            ->andReturn( 50 );

        $product = Mockery::mock( 'WC_Product' );
        $product->shouldReceive( 'set_stock_quantity' )->with( 50 );
        $product->shouldReceive( 'set_stock_status' )->with( 'instock' );
        $product->shouldReceive( 'set_manage_stock' )->with( true );
        $product->shouldReceive( 'save' );

        Functions\expect( 'wc_get_product' )->with( 100 )->andReturn( $product );
        Functions\expect( 'remove_filter' )->once();
        Functions\expect( 'add_filter' )->once();

        $this->service->setStockForWarehouse( 10, 100, 50 );

        $this->assertTrue( true );
    }

    public function testSetStockUpdatesExistingEntry(): void
    {
        $existingStock = new WarehouseStock( 5, 10, 100, 20 );

        $this->stockRepository
            ->shouldReceive( 'findByWarehouseAndProduct' )
            ->with( 10, 100 )
            ->andReturn( $existingStock );

        $this->stockRepository
            ->shouldReceive( 'save' )
            ->once()
            ->with( Mockery::on( function ( WarehouseStock $stock ) {
                return $stock->getId() === 5
                    && $stock->getQuantity() === 80;
            } ) )
            ->andReturn( 5 );

        $this->stockRepository
            ->shouldReceive( 'getTotalStockForProduct' )
            ->with( 100 )
            ->andReturn( 80 );

        $product = Mockery::mock( 'WC_Product' );
        $product->shouldReceive( 'set_stock_quantity' )->with( 80 );
        $product->shouldReceive( 'set_stock_status' )->with( 'instock' );
        $product->shouldReceive( 'set_manage_stock' )->with( true );
        $product->shouldReceive( 'save' );

        Functions\expect( 'wc_get_product' )->with( 100 )->andReturn( $product );
        Functions\expect( 'remove_filter' )->once();
        Functions\expect( 'add_filter' )->once();

        $this->service->setStockForWarehouse( 10, 100, 80 );

        $this->assertTrue( true );
    }

    // ─── reduceStockFromWarehouse ──────────────────────────────

    public function testReduceStockThrowsWhenNoEntry(): void
    {
        $this->stockRepository
            ->shouldReceive( 'findByWarehouseAndProduct' )
            ->with( 10, 100 )
            ->andReturn( null );

        $this->expectException( \RuntimeException::class );

        $this->service->reduceStockFromWarehouse( 10, 100, 5 );
    }

    // ─── syncWooCommerceStock ──────────────────────────────────

    public function testSyncDoesNothingWhenProductNotFound(): void
    {
        $this->stockRepository
            ->shouldReceive( 'getTotalStockForProduct' )
            ->with( 999 )
            ->andReturn( 0 );

        Functions\expect( 'wc_get_product' )->with( 999 )->andReturn( false );

        $this->service->syncWooCommerceStock( 999 );

        $this->assertTrue( true ); // Verifies no exception was thrown.
    }
}
