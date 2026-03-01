<?php

declare(strict_types=1);

namespace Tests\Unit\Frontend;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use WdMultiWarehouse\Contracts\StockRepositoryInterface;
use WdMultiWarehouse\Contracts\WarehouseRepositoryInterface;
use WdMultiWarehouse\Frontend\CatalogFilter;
use WdMultiWarehouse\Models\Warehouse;
use WdMultiWarehouse\Services\TemplateRenderer;

class CatalogFilterTest extends TestCase
{
    private MockInterface $warehouseRepository;
    private MockInterface $stockRepository;
    private MockInterface $renderer;
    private CatalogFilter $catalogFilter;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->warehouseRepository = Mockery::mock( WarehouseRepositoryInterface::class );
        $this->stockRepository     = Mockery::mock( StockRepositoryInterface::class );
        $this->renderer            = Mockery::mock( TemplateRenderer::class );

        $this->catalogFilter = new CatalogFilter(
            $this->warehouseRepository,
            $this->stockRepository,
            $this->renderer
        );
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ─── register ──────────────────────────────────────────────

    public function testRegisterAddsThreeHooks(): void
    {
        Functions\expect( 'add_action' )
            ->once()
            ->with( 'woocommerce_before_shop_loop', Mockery::type( 'array' ), 20 );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'woocommerce_product_query', Mockery::type( 'array' ) );

        Functions\expect( 'add_filter' )
            ->once()
            ->with( 'woocommerce_shortcode_products_query', Mockery::type( 'array' ) );

        $this->catalogFilter->register();

        $this->assertTrue( true );
    }

    // ─── renderWarehouseFilter ─────────────────────────────────

    public function testRenderWarehouseFilterRendersWhenWarehousesExist(): void
    {
        $_GET = [];

        Functions\expect( 'absint' )->andReturn( 0 );
        Functions\expect( '__' )->andReturnFirstArg();
        Functions\expect( 'add_query_arg' )->andReturn( '/shop' );
        Functions\expect( 'home_url' )->andReturn( 'http://example.com/shop' );
        Functions\expect( 'remove_query_arg' )->andReturn( 'http://example.com/shop' );

        $warehouse = Warehouse::fromDbRow( (object) [
            'id' => '1', 'name' => 'WH-1', 'address' => 'Sofia',
            'latitude' => '42.0', 'longitude' => '23.0',
            'is_active' => '1', 'extra_shipping_cost' => '0',
        ] );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->once()
            ->andReturn( [ $warehouse ] );

        $this->renderer
            ->shouldReceive( 'render' )
            ->once()
            ->with( 'frontend/warehouse-filter.twig', Mockery::on( function ( array $context ) {
                return count( $context['warehouses'] ) === 1
                    && $context['selected_warehouse'] === 0
                    && $context['label'] === 'Filter by warehouse:';
            } ) )
            ->andReturn( '<div>filter</div>' );

        ob_start();
        $this->catalogFilter->renderWarehouseFilter();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'filter', $output );
    }

    public function testRenderWarehouseFilterSkipsWhenNoWarehouses(): void
    {
        $_GET = [];

        Functions\expect( 'absint' )->andReturn( 0 );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->once()
            ->andReturn( [] );

        // renderer->render should NOT be called.
        $this->renderer->shouldNotReceive( 'render' );

        $this->catalogFilter->renderWarehouseFilter();

        $this->assertTrue( true );
    }

    public function testRenderWarehouseFilterPassesSelectedWarehouse(): void
    {
        $_GET['wdmw_warehouse'] = '3';

        Functions\expect( 'absint' )->andReturn( 3 );
        Functions\expect( '__' )->andReturnFirstArg();
        Functions\expect( 'add_query_arg' )->andReturn( '/shop' );
        Functions\expect( 'home_url' )->andReturn( 'http://example.com/shop' );
        Functions\expect( 'remove_query_arg' )->andReturn( 'http://example.com/shop' );

        $warehouse = Warehouse::fromDbRow( (object) [
            'id' => '3', 'name' => 'WH-3', 'address' => 'Test',
            'latitude' => '0', 'longitude' => '0',
            'is_active' => '1', 'extra_shipping_cost' => '0',
        ] );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->andReturn( [ $warehouse ] );

        $this->renderer
            ->shouldReceive( 'render' )
            ->once()
            ->with( 'frontend/warehouse-filter.twig', Mockery::on( function ( array $context ) {
                return $context['selected_warehouse'] === 3;
            } ) )
            ->andReturn( '' );

        $this->catalogFilter->renderWarehouseFilter();

        $this->assertTrue( true );
    }

    // ─── filterByWarehouse ─────────────────────────────────────

    public function testFilterByWarehouseDoesNothingWhenNoWarehouseSelected(): void
    {
        $_GET = [];

        Functions\expect( 'absint' )->andReturn( 0 );

        $query = Mockery::mock( \WP_Query::class );
        $query->shouldNotReceive( 'set' );

        $this->catalogFilter->filterByWarehouse( $query );

        $this->assertTrue( true );
    }

    public function testFilterByWarehouseSetsPostInToProductIds(): void
    {
        $_GET['wdmw_warehouse'] = '2';

        Functions\expect( 'absint' )->andReturn( 2 );

        $this->stockRepository
            ->shouldReceive( 'getProductIdsInWarehouse' )
            ->with( 2 )
            ->andReturn( [ 10, 20, 30 ] );

        $query = Mockery::mock( \WP_Query::class );
        $query->shouldReceive( 'get' )->with( 'post__in' )->andReturn( [] );
        $query->shouldReceive( 'set' )->once()->with( 'post__in', [ 10, 20, 30 ] );

        $this->catalogFilter->filterByWarehouse( $query );

        $this->assertTrue( true );
    }

    public function testFilterByWarehouseForcesEmptyWhenNoProducts(): void
    {
        $_GET['wdmw_warehouse'] = '5';

        Functions\expect( 'absint' )->andReturn( 5 );

        $this->stockRepository
            ->shouldReceive( 'getProductIdsInWarehouse' )
            ->with( 5 )
            ->andReturn( [] );

        $query = Mockery::mock( \WP_Query::class );
        $query->shouldReceive( 'set' )->once()->with( 'post__in', [ 0 ] );

        $this->catalogFilter->filterByWarehouse( $query );

        $this->assertTrue( true );
    }

    public function testFilterByWarehouseIntersectsWithExistingPostIn(): void
    {
        $_GET['wdmw_warehouse'] = '2';

        Functions\expect( 'absint' )->andReturn( 2 );

        $this->stockRepository
            ->shouldReceive( 'getProductIdsInWarehouse' )
            ->with( 2 )
            ->andReturn( [ 10, 20, 30 ] );

        $query = Mockery::mock( \WP_Query::class );
        $query->shouldReceive( 'get' )->with( 'post__in' )->andReturn( [ 20, 40 ] );
        $query->shouldReceive( 'set' )->once()->with( 'post__in', Mockery::on( function ( $ids ) {
            return in_array( 20, $ids, true ) && ! in_array( 40, $ids, true );
        } ) );

        $this->catalogFilter->filterByWarehouse( $query );

        $this->assertTrue( true );
    }

    public function testFilterByWarehouseForcesEmptyOnDisjointIntersection(): void
    {
        $_GET['wdmw_warehouse'] = '2';

        Functions\expect( 'absint' )->andReturn( 2 );

        $this->stockRepository
            ->shouldReceive( 'getProductIdsInWarehouse' )
            ->with( 2 )
            ->andReturn( [ 10, 20 ] );

        $query = Mockery::mock( \WP_Query::class );
        $query->shouldReceive( 'get' )->with( 'post__in' )->andReturn( [ 50, 60 ] );
        $query->shouldReceive( 'set' )->once()->with( 'post__in', [ 0 ] );

        $this->catalogFilter->filterByWarehouse( $query );

        $this->assertTrue( true );
    }

    // ─── filterShortcodeQuery ──────────────────────────────────

    public function testFilterShortcodeQueryReturnsUnchangedWhenNoWarehouseSelected(): void
    {
        $_GET = [];

        Functions\expect( 'absint' )->andReturn( 0 );

        $queryArgs = [ 'post_type' => 'product' ];
        $result    = $this->catalogFilter->filterShortcodeQuery( $queryArgs );

        $this->assertSame( $queryArgs, $result );
    }

    public function testFilterShortcodeQuerySetsPostInToProductIds(): void
    {
        $_GET['wdmw_warehouse'] = '4';

        Functions\expect( 'absint' )->andReturn( 4 );

        $this->stockRepository
            ->shouldReceive( 'getProductIdsInWarehouse' )
            ->with( 4 )
            ->andReturn( [ 100, 200 ] );

        $result = $this->catalogFilter->filterShortcodeQuery( [] );

        $this->assertSame( [ 100, 200 ], $result['post__in'] );
    }

    public function testFilterShortcodeQueryForcesEmptyWhenNoProducts(): void
    {
        $_GET['wdmw_warehouse'] = '4';

        Functions\expect( 'absint' )->andReturn( 4 );

        $this->stockRepository
            ->shouldReceive( 'getProductIdsInWarehouse' )
            ->with( 4 )
            ->andReturn( [] );

        $result = $this->catalogFilter->filterShortcodeQuery( [] );

        $this->assertSame( [ 0 ], $result['post__in'] );
    }

    public function testFilterShortcodeQueryIntersectsWithExistingPostIn(): void
    {
        $_GET['wdmw_warehouse'] = '1';

        Functions\expect( 'absint' )->andReturn( 1 );

        $this->stockRepository
            ->shouldReceive( 'getProductIdsInWarehouse' )
            ->with( 1 )
            ->andReturn( [ 10, 20, 30 ] );

        $result = $this->catalogFilter->filterShortcodeQuery( [ 'post__in' => [ 20, 40 ] ] );

        $this->assertContains( 20, $result['post__in'] );
        $this->assertNotContains( 40, $result['post__in'] );
    }

    public function testFilterShortcodeQueryForcesEmptyOnDisjointIntersection(): void
    {
        $_GET['wdmw_warehouse'] = '1';

        Functions\expect( 'absint' )->andReturn( 1 );

        $this->stockRepository
            ->shouldReceive( 'getProductIdsInWarehouse' )
            ->with( 1 )
            ->andReturn( [ 10, 20 ] );

        $result = $this->catalogFilter->filterShortcodeQuery( [ 'post__in' => [ 50 ] ] );

        $this->assertSame( [ 0 ], $result['post__in'] );
    }
}
