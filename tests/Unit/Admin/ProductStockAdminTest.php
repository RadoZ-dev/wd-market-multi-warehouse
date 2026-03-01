<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use WdMultiWarehouse\Admin\ProductStockAdmin;
use WdMultiWarehouse\Contracts\StockRepositoryInterface;
use WdMultiWarehouse\Contracts\WarehouseRepositoryInterface;
use WdMultiWarehouse\Models\Warehouse;
use WdMultiWarehouse\Services\StockService;
use WdMultiWarehouse\Services\TemplateRenderer;

class ProductStockAdminTest extends TestCase
{
    private MockInterface $renderer;
    private MockInterface $warehouseRepository;
    private MockInterface $stockRepository;
    private MockInterface $stockService;
    private ProductStockAdmin $productStockAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->renderer            = Mockery::mock( TemplateRenderer::class );
        $this->warehouseRepository = Mockery::mock( WarehouseRepositoryInterface::class );
        $this->stockRepository     = Mockery::mock( StockRepositoryInterface::class );
        $this->stockService        = Mockery::mock( StockService::class );

        $this->productStockAdmin = new ProductStockAdmin(
            $this->renderer,
            $this->warehouseRepository,
            $this->stockRepository,
            $this->stockService
        );
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ─── register ──────────────────────────────────────────────

    public function testRegisterAddsFiveHooks(): void
    {
        Functions\expect( 'add_filter' )
            ->once()
            ->with( 'woocommerce_product_data_tabs', Mockery::type( 'array' ) );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'woocommerce_product_data_panels', Mockery::type( 'array' ) );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'woocommerce_process_product_meta', Mockery::type( 'array' ) );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'woocommerce_product_after_variable_attributes', Mockery::type( 'array' ), 10, 3 );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'woocommerce_save_product_variation', Mockery::type( 'array' ), 10, 2 );

        $this->productStockAdmin->register();

        $this->assertTrue( true );
    }

    // ─── addWarehouseTab ───────────────────────────────────────

    public function testAddWarehouseTabAddsNewTab(): void
    {
        Functions\expect( '__' )->andReturnFirstArg();

        $existingTabs = [
            'general'   => [ 'label' => 'General' ],
            'inventory' => [ 'label' => 'Inventory' ],
        ];

        $result = $this->productStockAdmin->addWarehouseTab( $existingTabs );

        $this->assertArrayHasKey( 'wdmw_warehouse', $result );
        $this->assertCount( 3, $result );
    }

    public function testAddWarehouseTabPreservesExistingTabs(): void
    {
        Functions\expect( '__' )->andReturnFirstArg();

        $existingTabs = [
            'general' => [ 'label' => 'General' ],
        ];

        $result = $this->productStockAdmin->addWarehouseTab( $existingTabs );

        $this->assertArrayHasKey( 'general', $result );
        $this->assertArrayHasKey( 'wdmw_warehouse', $result );
    }

    public function testAddWarehouseTabHasCorrectStructure(): void
    {
        Functions\expect( '__' )->andReturnFirstArg();

        $result = $this->productStockAdmin->addWarehouseTab( [] );

        $tab = $result['wdmw_warehouse'];

        $this->assertEquals( 'Warehouse', $tab['label'] );
        $this->assertEquals( 'wdmw_warehouse_panel', $tab['target'] );
        $this->assertContains( 'show_if_simple', $tab['class'] );
        $this->assertContains( 'show_if_variable', $tab['class'] );
        $this->assertEquals( 65, $tab['priority'] );
    }

    // ─── renderWarehousePanel ──────────────────────────────────

    public function testRenderWarehousePanelPassesCorrectContext(): void
    {
        global $post;
        $post     = Mockery::mock( \stdClass::class );
        $post->ID = 42;

        $warehouse = Warehouse::fromDbRow( (object) [
            'id' => '1', 'name' => 'WH-1', 'address' => 'Test',
            'latitude' => '42.0', 'longitude' => '23.0',
            'is_active' => '1', 'extra_shipping_cost' => '0',
        ] );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->once()
            ->andReturn( [ $warehouse ] );

        $this->stockRepository
            ->shouldReceive( 'getStockMapForProduct' )
            ->once()
            ->with( 42 )
            ->andReturn( [ 1 => 10 ] );

        $this->renderer
            ->shouldReceive( 'render' )
            ->once()
            ->with( 'admin/product-stock.twig', Mockery::on( function ( array $context ) {
                return $context['product_id'] === 42
                    && count( $context['warehouses'] ) === 1
                    && $context['stock_map'] === [ 1 => 10 ]
                    && $context['total_stock'] === 10;
            } ) )
            ->andReturn( '<html>' );

        $this->productStockAdmin->renderWarehousePanel();

        $this->assertTrue( true );
    }

    public function testRenderWarehousePanelHandlesEmptyStock(): void
    {
        global $post;
        $post     = Mockery::mock( \stdClass::class );
        $post->ID = 99;

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->andReturn( [] );

        $this->stockRepository
            ->shouldReceive( 'getStockMapForProduct' )
            ->with( 99 )
            ->andReturn( [] );

        $this->renderer
            ->shouldReceive( 'render' )
            ->once()
            ->with( 'admin/product-stock.twig', Mockery::on( function ( array $context ) {
                return $context['total_stock'] === 0
                    && $context['warehouses'] === [];
            } ) )
            ->andReturn( '' );

        $this->productStockAdmin->renderWarehousePanel();

        $this->assertTrue( true );
    }

    // ─── saveStockFields ───────────────────────────────────────

    public function testSaveStockFieldsProcessesPostData(): void
    {
        $_POST['wdmw_warehouse_stock'] = [
            '1' => '25',
            '3' => '50',
        ];

        Functions\expect( 'absint' )->andReturnUsing( function ( $val ) {
            return abs( (int) $val );
        } );

        $this->stockService
            ->shouldReceive( 'setStockForWarehouse' )
            ->once()
            ->with( 1, 100, 25 );

        $this->stockService
            ->shouldReceive( 'setStockForWarehouse' )
            ->once()
            ->with( 3, 100, 50 );

        $this->productStockAdmin->saveStockFields( 100 );

        $this->assertTrue( true );
    }

    public function testSaveStockFieldsBailsWithoutPostData(): void
    {
        $_POST = [];

        // setStockForWarehouse should never be called.
        $this->stockService
            ->shouldNotReceive( 'setStockForWarehouse' );

        $this->productStockAdmin->saveStockFields( 100 );

        $this->assertTrue( true );
    }

    public function testSaveStockFieldsBailsWhenStockNotArray(): void
    {
        $_POST['wdmw_warehouse_stock'] = 'not_an_array';

        $this->stockService
            ->shouldNotReceive( 'setStockForWarehouse' );

        $this->productStockAdmin->saveStockFields( 100 );

        $this->assertTrue( true );
    }

    // ─── renderVariationStockFields ────────────────────────────

    public function testRenderVariationStockFieldsPassesCorrectContext(): void
    {
        $variation     = Mockery::mock( \WP_Post::class );
        $variation->ID = 55;

        $warehouse = Warehouse::fromDbRow( (object) [
            'id' => '2', 'name' => 'WH-2', 'address' => 'Addr',
            'latitude' => '0', 'longitude' => '0',
            'is_active' => '1', 'extra_shipping_cost' => '5',
        ] );

        $this->warehouseRepository
            ->shouldReceive( 'findActive' )
            ->once()
            ->andReturn( [ $warehouse ] );

        $this->stockRepository
            ->shouldReceive( 'getStockMapForProduct' )
            ->once()
            ->with( 55 )
            ->andReturn( [ 2 => 30 ] );

        $this->renderer
            ->shouldReceive( 'render' )
            ->once()
            ->with( 'admin/variation-stock.twig', Mockery::on( function ( array $context ) {
                return $context['variation_id'] === 55
                    && $context['loop'] === 0
                    && $context['total_stock'] === 30;
            } ) )
            ->andReturn( '<div>' );

        $this->productStockAdmin->renderVariationStockFields( 0, [], $variation );

        $this->assertTrue( true );
    }

    // ─── saveVariationStockFields ──────────────────────────────

    public function testSaveVariationStockFieldsProcessesPostData(): void
    {
        $_POST['wdmw_variation_stock'] = [
            0 => [ '2' => '15', '3' => '20' ],
        ];

        Functions\expect( 'absint' )->andReturnUsing( function ( $val ) {
            return abs( (int) $val );
        } );

        $this->stockService
            ->shouldReceive( 'setStockForWarehouse' )
            ->once()
            ->with( 2, 77, 15 );

        $this->stockService
            ->shouldReceive( 'setStockForWarehouse' )
            ->once()
            ->with( 3, 77, 20 );

        $this->productStockAdmin->saveVariationStockFields( 77, 0 );

        $this->assertTrue( true );
    }

    public function testSaveVariationStockFieldsBailsForMissingLoop(): void
    {
        $_POST['wdmw_variation_stock'] = [
            0 => [ '2' => '15' ],
        ];

        $this->stockService
            ->shouldNotReceive( 'setStockForWarehouse' );

        // Loop 5 not in POST.
        $this->productStockAdmin->saveVariationStockFields( 77, 5 );

        $this->assertTrue( true );
    }

    public function testSaveVariationStockFieldsBailsWithoutPostData(): void
    {
        $_POST = [];

        $this->stockService
            ->shouldNotReceive( 'setStockForWarehouse' );

        $this->productStockAdmin->saveVariationStockFields( 77, 0 );

        $this->assertTrue( true );
    }
}
