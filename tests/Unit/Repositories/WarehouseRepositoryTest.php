<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use WdMultiWarehouse\Models\Warehouse;
use WdMultiWarehouse\Repositories\WarehouseRepository;

class WarehouseRepositoryTest extends TestCase
{
    private MockInterface $wpdb;
    private WarehouseRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb         = Mockery::mock( 'wpdb' );
        $this->wpdb->prefix = 'wp_';

        $GLOBALS['wpdb'] = $this->wpdb;

        $this->repository = new WarehouseRepository();
    }

    protected function tearDown(): void
    {
        unset( $GLOBALS['wpdb'] );
        Mockery::close();
        parent::tearDown();
    }

    private function createDbRow( array $overrides = [] ): object
    {
        return (object) array_merge(
            [
                'id'                  => '1',
                'name'                => 'Sofia Center',
                'address'             => 'ul. Vitosha 1',
                'latitude'            => '42.6977',
                'longitude'           => '23.3219',
                'is_active'           => '1',
                'extra_shipping_cost' => '5.50',
            ],
            $overrides
        );
    }

    // ─── findById ──────────────────────────────────────────────

    public function testFindByIdReturnsWarehouseWhenFound(): void
    {
        $dbRow = $this->createDbRow();

        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->with(
                "SELECT * FROM wp_wdmw_warehouses WHERE id = %d",
                1
            )
            ->andReturn( 'prepared_query' );

        $this->wpdb->shouldReceive( 'get_row' )
            ->once()
            ->with( 'prepared_query' )
            ->andReturn( $dbRow );

        $warehouse = $this->repository->findById( 1 );

        $this->assertInstanceOf( Warehouse::class, $warehouse );
        $this->assertSame( 1, $warehouse->getId() );
        $this->assertSame( 'Sofia Center', $warehouse->getName() );
        $this->assertSame( 'ul. Vitosha 1', $warehouse->getAddress() );
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->wpdb->shouldReceive( 'prepare' )
            ->once()
            ->andReturn( 'prepared_query' );

        $this->wpdb->shouldReceive( 'get_row' )
            ->once()
            ->andReturn( null );

        $result = $this->repository->findById( 999 );

        $this->assertNull( $result );
    }

    // ─── findAll ───────────────────────────────────────────────

    public function testFindAllReturnsArrayOfWarehouses(): void
    {
        $rows = [
            $this->createDbRow( [ 'id' => '1', 'name' => 'Alpha' ] ),
            $this->createDbRow( [ 'id' => '2', 'name' => 'Beta' ] ),
        ];

        $this->wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( "SELECT * FROM wp_wdmw_warehouses ORDER BY name ASC" )
            ->andReturn( $rows );

        $warehouses = $this->repository->findAll();

        $this->assertCount( 2, $warehouses );
        $this->assertInstanceOf( Warehouse::class, $warehouses[0] );
        $this->assertSame( 'Alpha', $warehouses[0]->getName() );
        $this->assertSame( 'Beta', $warehouses[1]->getName() );
    }

    public function testFindAllReturnsEmptyArrayWhenNoRows(): void
    {
        $this->wpdb->shouldReceive( 'get_results' )
            ->once()
            ->andReturn( [] );

        $warehouses = $this->repository->findAll();

        $this->assertSame( [], $warehouses );
    }

    // ─── findActive ────────────────────────────────────────────

    public function testFindActiveReturnsOnlyActiveWarehouses(): void
    {
        $rows = [
            $this->createDbRow( [ 'id' => '1', 'is_active' => '1' ] ),
        ];

        $this->wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( "SELECT * FROM wp_wdmw_warehouses WHERE is_active = 1 ORDER BY name ASC" )
            ->andReturn( $rows );

        $warehouses = $this->repository->findActive();

        $this->assertCount( 1, $warehouses );
        $this->assertTrue( $warehouses[0]->isActive() );
    }

    // ─── save (insert) ────────────────────────────────────────

    public function testSaveInsertsNewWarehouseAndReturnsInsertId(): void
    {
        $warehouse = new Warehouse();
        $warehouse->setName( 'New Warehouse' );
        $warehouse->setAddress( 'Some Address' );

        $expectedData = [
            'name'                => 'New Warehouse',
            'address'             => 'Some Address',
            'latitude'            => null,
            'longitude'           => null,
            'is_active'           => 1,
            'extra_shipping_cost' => 0.00,
        ];
        $expectedFormats = [ '%s', '%s', '%f', '%f', '%d', '%f' ];

        $this->wpdb->shouldReceive( 'insert' )
            ->once()
            ->with( 'wp_wdmw_warehouses', $expectedData, $expectedFormats )
            ->andReturn( 1 );

        $this->wpdb->insert_id = 42;

        $id = $this->repository->save( $warehouse );

        $this->assertSame( 42, $id );
    }

    // ─── save (update) ────────────────────────────────────────

    public function testSaveUpdatesExistingWarehouseAndReturnsId(): void
    {
        $dbRow     = $this->createDbRow( [ 'id' => '5', 'name' => 'Old Name' ] );
        $warehouse = Warehouse::fromDbRow( $dbRow );
        $warehouse->setName( 'Updated Name' );

        $expectedData = [
            'name'                => 'Updated Name',
            'address'             => 'ul. Vitosha 1',
            'latitude'            => 42.6977,
            'longitude'           => 23.3219,
            'is_active'           => 1,
            'extra_shipping_cost' => 5.50,
        ];

        $this->wpdb->shouldReceive( 'update' )
            ->once()
            ->with(
                'wp_wdmw_warehouses',
                $expectedData,
                [ 'id' => 5 ],
                [ '%s', '%s', '%f', '%f', '%d', '%f' ],
                [ '%d' ]
            )
            ->andReturn( 1 );

        $id = $this->repository->save( $warehouse );

        $this->assertSame( 5, $id );
    }

    // ─── delete ────────────────────────────────────────────────

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $this->wpdb->shouldReceive( 'delete' )
            ->once()
            ->with( 'wp_wdmw_warehouses', [ 'id' => 3 ], [ '%d' ] )
            ->andReturn( 1 );

        $this->assertTrue( $this->repository->delete( 3 ) );
    }

    public function testDeleteReturnsFalseOnFailure(): void
    {
        $this->wpdb->shouldReceive( 'delete' )
            ->once()
            ->with( 'wp_wdmw_warehouses', [ 'id' => 99 ], [ '%d' ] )
            ->andReturn( false );

        $this->assertFalse( $this->repository->delete( 99 ) );
    }

    public function testDeleteReturnsTrueWhenZeroRowsAffected(): void
    {
        $this->wpdb->shouldReceive( 'delete' )
            ->once()
            ->andReturn( 0 );

        // 0 rows deleted is not a failure — the query succeeded, just no matching row.
        $this->assertTrue( $this->repository->delete( 999 ) );
    }
}
