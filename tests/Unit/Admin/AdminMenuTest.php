<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use WdMultiWarehouse\Admin\AdminMenu;
use WdMultiWarehouse\Contracts\GeocoderInterface;
use WdMultiWarehouse\Contracts\WarehouseRepositoryInterface;
use WdMultiWarehouse\Models\Warehouse;
use WdMultiWarehouse\Services\TemplateRenderer;

class AdminMenuTest extends TestCase
{
    private MockInterface $renderer;
    private MockInterface $warehouseRepository;
    private MockInterface $geocoder;
    private AdminMenu $adminMenu;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->renderer            = Mockery::mock( TemplateRenderer::class );
        $this->warehouseRepository = Mockery::mock( WarehouseRepositoryInterface::class );
        $this->geocoder            = Mockery::mock( GeocoderInterface::class );

        $this->adminMenu = new AdminMenu(
            $this->renderer,
            $this->warehouseRepository,
            $this->geocoder
        );
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ─── register ──────────────────────────────────────────────

    public function testRegisterAddsTwoHooks(): void
    {
        Functions\expect( 'add_action' )
            ->once()
            ->with( 'admin_menu', Mockery::type( 'array' ) );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'admin_init', Mockery::type( 'array' ) );

        $this->adminMenu->register();

        $this->assertTrue( true );
    }

    // ─── addMenuPages ──────────────────────────────────────────

    public function testAddMenuPagesCreatesTopLevelPage(): void
    {
        Functions\expect( '__' )->andReturnFirstArg();

        Functions\expect( 'add_menu_page' )
            ->once()
            ->with(
                'Warehouses',
                'Warehouses',
                'manage_woocommerce',
                'wdmw-warehouses',
                Mockery::type( 'array' ),
                'dashicons-building',
                56
            );

        $this->adminMenu->addMenuPages();

        $this->assertTrue( true );
    }

    // ─── renderWarehousesPage ──────────────────────────────────

    public function testRenderWarehousesPageShowsListByDefault(): void
    {
        $_GET = [];

        Functions\expect( 'sanitize_text_field' )->andReturnUsing( function ( $val ) {
            return $val;
        } );
        Functions\expect( 'absint' )->andReturn( 0 );

        $warehouses = [ new Warehouse() ];

        $this->warehouseRepository
            ->shouldReceive( 'findAll' )
            ->once()
            ->andReturn( $warehouses );

        Functions\expect( 'admin_url' )->andReturn( 'http://example.com/admin.php?page=wdmw-warehouses&action=add' );
        Functions\expect( '__' )->andReturnFirstArg();

        $this->renderer
            ->shouldReceive( 'display' )
            ->once()
            ->with( 'admin/warehouse-list.twig', Mockery::type( 'array' ) );

        $this->adminMenu->renderWarehousesPage();

        $this->assertTrue( true );
    }

    public function testRenderWarehousesPageShowsFormOnAddAction(): void
    {
        $_GET['action'] = 'add';

        Functions\expect( 'sanitize_text_field' )->andReturn( 'add' );
        Functions\expect( 'absint' )->andReturn( 0 );
        Functions\expect( 'admin_url' )->andReturn( 'http://example.com/admin.php?page=wdmw-warehouses' );
        Functions\expect( 'wp_nonce_field' )->andReturn( '<input type="hidden">' );
        Functions\expect( '__' )->andReturnFirstArg();

        $this->renderer
            ->shouldReceive( 'display' )
            ->once()
            ->with( 'admin/warehouse-form.twig', Mockery::on( function ( array $context ) {
                return $context['is_edit'] === false && $context['warehouse'] === null;
            } ) );

        $this->adminMenu->renderWarehousesPage();

        $this->assertTrue( true );
    }

    public function testRenderWarehousesPageShowsFormOnEditAction(): void
    {
        $_GET['action']       = 'edit';
        $_GET['warehouse_id'] = '5';

        Functions\expect( 'sanitize_text_field' )->andReturn( 'edit' );
        Functions\expect( 'absint' )->andReturn( 5 );

        $warehouse = new Warehouse();
        $warehouse->setName( 'Test' );

        $this->warehouseRepository
            ->shouldReceive( 'findById' )
            ->with( 5 )
            ->andReturn( $warehouse );

        Functions\expect( 'admin_url' )->andReturn( 'http://example.com/' );
        Functions\expect( 'wp_nonce_field' )->andReturn( '<input>' );
        Functions\expect( '__' )->andReturnFirstArg();

        $this->renderer
            ->shouldReceive( 'display' )
            ->once()
            ->with( 'admin/warehouse-form.twig', Mockery::on( function ( array $context ) {
                return $context['is_edit'] === true;
            } ) );

        $this->adminMenu->renderWarehousesPage();

        $this->assertTrue( true );
    }

    // ─── handleFormSubmissions ──────────────────────────────────

    public function testHandleFormSubmissionsBailsWithoutNonce(): void
    {
        $_POST = [];

        // No wp_verify_nonce call should happen.
        $this->adminMenu->handleFormSubmissions();

        $this->assertTrue( true );
    }

    public function testHandleFormSubmissionsDiesOnInvalidNonce(): void
    {
        $_POST['wdmw_warehouse_nonce'] = 'bad_nonce';

        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->with( 'bad_nonce', 'wdmw_save_warehouse' )
            ->andReturn( false );

        Functions\expect( '__' )->andReturnFirstArg();

        Functions\expect( 'wp_die' )
            ->once()
            ->with( 'Security check failed.' )
            ->andReturnUsing( function () {
                throw new \RuntimeException( 'wp_die called' );
            } );

        $this->expectException( \RuntimeException::class );

        $this->adminMenu->handleFormSubmissions();
    }

    public function testHandleFormSubmissionsDiesOnInsufficientCapability(): void
    {
        $_POST['wdmw_warehouse_nonce'] = 'valid';

        Functions\expect( 'wp_verify_nonce' )->andReturn( 1 );
        Functions\expect( 'current_user_can' )->with( 'manage_woocommerce' )->andReturn( false );
        Functions\expect( '__' )->andReturnFirstArg();

        Functions\expect( 'wp_die' )
            ->once()
            ->with( 'Unauthorized access.' )
            ->andReturnUsing( function () {
                throw new \RuntimeException( 'wp_die called' );
            } );

        $this->expectException( \RuntimeException::class );

        $this->adminMenu->handleFormSubmissions();
    }

    // ─── addWarehouseTab (on ProductStockAdmin, not here) ──────
    // ─── warehouse list template receives correct data ─────────

    public function testWarehouseListReceivesWarehousesArray(): void
    {
        $_GET = [];

        Functions\expect( 'sanitize_text_field' )->andReturn( 'list' );
        Functions\expect( 'absint' )->andReturn( 0 );

        $warehouses = [
            Warehouse::fromDbRow( (object) [
                'id' => '1', 'name' => 'WH1', 'address' => 'Addr1',
                'latitude' => '42.0', 'longitude' => '23.0',
                'is_active' => '1', 'extra_shipping_cost' => '0',
            ] ),
        ];

        $this->warehouseRepository
            ->shouldReceive( 'findAll' )
            ->andReturn( $warehouses );

        Functions\expect( 'admin_url' )->andReturn( 'http://example.com/' );
        Functions\expect( '__' )->andReturnFirstArg();

        $this->renderer
            ->shouldReceive( 'display' )
            ->once()
            ->with( 'admin/warehouse-list.twig', Mockery::on( function ( array $context ) {
                return count( $context['warehouses'] ) === 1
                    && $context['warehouses'][0]->getName() === 'WH1';
            } ) );

        $this->adminMenu->renderWarehousesPage();

        $this->assertTrue( true );
    }
}
