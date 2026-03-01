<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use WdMultiWarehouse\Models\WarehouseStock;
use WdMultiWarehouse\Repositories\StockRepository;

class StockRepositoryTest extends TestCase
{
    private MockInterface $wpdb;
    private StockRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb         = Mockery::mock( 'wpdb' );
        $this->wpdb->prefix = 'wp_';

        $GLOBALS['wpdb'] = $this->wpdb;

        $this->repository = new StockRepository();
    }

    protected function tearDown(): void
    {
        unset( $GLOBALS['wpdb'] );
        Mockery::close();
        parent::tearDown();
    }

    private function createStockRow( array $overrides = [] ): object
    {
        return (object) array_merge(
            [
                'id'           => '1',
                'warehouse_id' => '10',
                'product_id'   => '100',
                'quantity'     => '25',
            ],
            $overrides
        );
    }

    // ─── findByWarehouseAndProduct ─────────────────────────────

    public function testFindByWarehouseAndProductReturnsStockWhenFound(): void
    {
        $dbRow = $this->createStockRow();

        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->with(
                "SELECT * FROM wp_wdmw_warehouse_stock WHERE warehouse_id = %d AND product_id = %d",
                10,
                100
            )
            ->andReturn( 'prepared_query' );

        $this->wpdb->shouldReceive( 'get_row' )
            ->once()
            ->with( 'prepared_query' )
            ->andReturn( $dbRow );

        $stock = $this->repository->findByWarehouseAndProduct( 10, 100 );

        $this->assertInstanceOf( WarehouseStock::class, $stock );
        $this->assertSame( 10, $stock->getWarehouseId() );
        $this->assertSame( 100, $stock->getProductId() );
        $this->assertSame( 25, $stock->getQuantity() );
    }

    public function testFindByWarehouseAndProductReturnsNullWhenNotFound(): void
    {
        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'prepared_query' );

        $this->wpdb->shouldReceive( 'get_row' )
            ->once()
            ->andReturn( null );

        $result = $this->repository->findByWarehouseAndProduct( 10, 999 );

        $this->assertNull( $result );
    }

    // ─── findByProduct ─────────────────────────────────────────

    public function testFindByProductReturnsArrayOfStock(): void
    {
        $rows = [
            $this->createStockRow( [ 'id' => '1', 'warehouse_id' => '10', 'quantity' => '5' ] ),
            $this->createStockRow( [ 'id' => '2', 'warehouse_id' => '20', 'quantity' => '15' ] ),
        ];

        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'prepared_query' );

        $this->wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( 'prepared_query' )
            ->andReturn( $rows );

        $stocks = $this->repository->findByProduct( 100 );

        $this->assertCount( 2, $stocks );
        $this->assertInstanceOf( WarehouseStock::class, $stocks[0] );
        $this->assertSame( 5, $stocks[0]->getQuantity() );
        $this->assertSame( 15, $stocks[1]->getQuantity() );
    }

    // ─── findByWarehouse ───────────────────────────────────────

    public function testFindByWarehouseReturnsArrayOfStock(): void
    {
        $rows = [
            $this->createStockRow( [ 'id' => '1', 'product_id' => '100' ] ),
            $this->createStockRow( [ 'id' => '2', 'product_id' => '200' ] ),
        ];

        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'prepared_query' );

        $this->wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( 'prepared_query' )
            ->andReturn( $rows );

        $stocks = $this->repository->findByWarehouse( 10 );

        $this->assertCount( 2, $stocks );
        $this->assertSame( 100, $stocks[0]->getProductId() );
        $this->assertSame( 200, $stocks[1]->getProductId() );
    }

    public function testFindByWarehouseReturnsEmptyArrayWhenNoRows(): void
    {
        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'prepared_query' );

        $this->wpdb->shouldReceive( 'get_results' )
            ->once()
            ->andReturn( [] );

        $this->assertSame( [], $this->repository->findByWarehouse( 10 ) );
    }

    // ─── save (insert new) ─────────────────────────────────────

    public function testSaveInsertsNewStockAndReturnsInsertId(): void
    {
        $stock = new WarehouseStock();
        $stock->setWarehouseId( 10 );
        $stock->setProductId( 100 );
        $stock->setQuantity( 30 );

        // findByWarehouseAndProduct check (id=0 path, no existing record).
        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'find_query' );

        $this->wpdb->shouldReceive( 'get_row' )
            ->once()
            ->andReturn( null );

        $expectedData    = [ 'warehouse_id' => 10, 'product_id' => 100, 'quantity' => 30 ];
        $expectedFormats = [ '%d', '%d', '%d' ];

        $this->wpdb->shouldReceive( 'insert' )
            ->once()
            ->with( 'wp_wdmw_warehouse_stock', $expectedData, $expectedFormats )
            ->andReturn( 1 );

        $this->wpdb->insert_id = 7;

        $id = $this->repository->save( $stock );

        $this->assertSame( 7, $id );
    }

    // ─── save (update by id) ───────────────────────────────────

    public function testSaveUpdatesByIdWhenStockHasId(): void
    {
        $dbRow = $this->createStockRow( [ 'id' => '5', 'quantity' => '10' ] );
        $stock = WarehouseStock::fromDbRow( $dbRow );
        $stock->setQuantity( 50 );

        $this->wpdb->shouldReceive( 'update' )
            ->once()
            ->with(
                'wp_wdmw_warehouse_stock',
                [ 'warehouse_id' => 10, 'product_id' => 100, 'quantity' => 50 ],
                [ 'id' => 5 ],
                [ '%d', '%d', '%d' ],
                [ '%d' ]
            )
            ->andReturn( 1 );

        $id = $this->repository->save( $stock );

        $this->assertSame( 5, $id );
    }

    // ─── save (upsert — existing record found) ────────────────

    public function testSaveUpdatesExistingRecordWhenDuplicateFound(): void
    {
        // New stock object (id=0) but a record already exists for this warehouse+product.
        $stock = new WarehouseStock();
        $stock->setWarehouseId( 10 );
        $stock->setProductId( 100 );
        $stock->setQuantity( 99 );

        $existingRow = $this->createStockRow( [ 'id' => '3' ] );

        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'find_query' );

        $this->wpdb->shouldReceive( 'get_row' )
            ->once()
            ->andReturn( $existingRow );

        $this->wpdb->shouldReceive( 'update' )
            ->once()
            ->with(
                'wp_wdmw_warehouse_stock',
                [ 'quantity' => 99 ],
                [ 'id' => 3 ],
                [ '%d' ],
                [ '%d' ]
            )
            ->andReturn( 1 );

        $id = $this->repository->save( $stock );

        $this->assertSame( 3, $id );
    }

    // ─── deleteByWarehouse ─────────────────────────────────────

    public function testDeleteByWarehouseReturnsTrueOnSuccess(): void
    {
        $this->wpdb->shouldReceive( 'delete' )
            ->once()
            ->with( 'wp_wdmw_warehouse_stock', [ 'warehouse_id' => 10 ], [ '%d' ] )
            ->andReturn( 3 );

        $this->assertTrue( $this->repository->deleteByWarehouse( 10 ) );
    }

    public function testDeleteByWarehouseReturnsFalseOnFailure(): void
    {
        $this->wpdb->shouldReceive( 'delete' )
            ->once()
            ->andReturn( false );

        $this->assertFalse( $this->repository->deleteByWarehouse( 10 ) );
    }

    // ─── getTotalStockForProduct ───────────────────────────────

    public function testGetTotalStockSumsQuantityFromActiveWarehouses(): void
    {
        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'prepared_total_query' );

        $this->wpdb->shouldReceive( 'get_var' )
            ->once()
            ->with( 'prepared_total_query' )
            ->andReturn( '75' );

        $total = $this->repository->getTotalStockForProduct( 100 );

        $this->assertSame( 75, $total );
    }

    public function testGetTotalStockReturnsZeroWhenNoStock(): void
    {
        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'prepared_query' );

        $this->wpdb->shouldReceive( 'get_var' )
            ->once()
            ->andReturn( '0' );

        $this->assertSame( 0, $this->repository->getTotalStockForProduct( 999 ) );
    }

    // ─── getStockMapForProduct ─────────────────────────────────

    public function testGetStockMapReturnsWarehouseIdToQuantityMap(): void
    {
        $rows = [
            (object) [ 'warehouse_id' => '10', 'quantity' => '20' ],
            (object) [ 'warehouse_id' => '20', 'quantity' => '35' ],
        ];

        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'prepared_map_query' );

        $this->wpdb->shouldReceive( 'get_results' )
            ->once()
            ->andReturn( $rows );

        $map = $this->repository->getStockMapForProduct( 100 );

        $this->assertSame( [ 10 => 20, 20 => 35 ], $map );
    }

    public function testGetStockMapReturnsEmptyArrayWhenNoStock(): void
    {
        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'prepared_query' );

        $this->wpdb->shouldReceive( 'get_results' )
            ->once()
            ->andReturn( [] );

        $this->assertSame( [], $this->repository->getStockMapForProduct( 999 ) );
    }

    // ─── getProductIdsInWarehouse ──────────────────────────────

    public function testGetProductIdsReturnsIntegerArray(): void
    {
        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'prepared_ids_query' );

        $this->wpdb->shouldReceive( 'get_col' )
            ->once()
            ->andReturn( [ '100', '200', '300' ] );

        $ids = $this->repository->getProductIdsInWarehouse( 10 );

        $this->assertSame( [ 100, 200, 300 ], $ids );
    }

    public function testGetProductIdsReturnsEmptyArrayWhenNone(): void
    {
        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'prepared_query' );

        $this->wpdb->shouldReceive( 'get_col' )
            ->once()
            ->andReturn( [] );

        $this->assertSame( [], $this->repository->getProductIdsInWarehouse( 10 ) );
    }
}
