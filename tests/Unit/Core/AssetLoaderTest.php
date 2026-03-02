<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Mockery;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use WdMultiWarehouse\Core\AssetLoader;

class AssetLoaderTest extends TestCase
{
    private AssetLoader $assetLoader;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->assetLoader = new AssetLoader();
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
            ->with( 'admin_enqueue_scripts', Mockery::type( 'array' ) );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'wp_enqueue_scripts', Mockery::type( 'array' ) );

        $this->assetLoader->register();

        $this->assertTrue( true );
    }

    // ─── enqueueAdminAssets ────────────────────────────────────

    public function testEnqueueAdminAssetsOnPluginPage(): void
    {
        Functions\expect( 'wp_enqueue_style' )
            ->once()
            ->with( 'wdmw-admin', Mockery::type( 'string' ), [], Mockery::any() );

        Functions\expect( 'wp_enqueue_script' )
            ->once()
            ->with( 'wdmw-admin', Mockery::type( 'string' ), [], Mockery::any(), true );

        $this->assetLoader->enqueueAdminAssets( 'toplevel_page_wdmw-warehouses' );

        $this->assertTrue( true );
    }

    public function testEnqueueAdminAssetsOnSettingsPage(): void
    {
        Functions\expect( 'wp_enqueue_style' )->once();
        Functions\expect( 'wp_enqueue_script' )->once();

        $this->assetLoader->enqueueAdminAssets( 'warehouses_page_wdmw-settings' );

        $this->assertTrue( true );
    }

    public function testEnqueueAdminAssetsSkipsIrrelevantPages(): void
    {
        Functions\expect( 'wp_enqueue_style' )->never();
        Functions\expect( 'wp_enqueue_script' )->never();

        $this->assetLoader->enqueueAdminAssets( 'index.php' );

        $this->assertTrue( true );
    }

    public function testEnqueueAdminAssetsOnProductEditPage(): void
    {
        $screen            = Mockery::mock( \stdClass::class );
        $screen->post_type = 'product';

        Functions\expect( 'get_current_screen' )->andReturn( $screen );
        Functions\expect( 'wp_enqueue_style' )->once();
        Functions\expect( 'wp_enqueue_script' )->once();

        $this->assetLoader->enqueueAdminAssets( 'post.php' );

        $this->assertTrue( true );
    }

    public function testEnqueueAdminAssetsSkipsNonProductPostPage(): void
    {
        $screen            = Mockery::mock( \stdClass::class );
        $screen->post_type = 'page';

        Functions\expect( 'get_current_screen' )->andReturn( $screen );
        Functions\expect( 'wp_enqueue_style' )->never();
        Functions\expect( 'wp_enqueue_script' )->never();

        $this->assetLoader->enqueueAdminAssets( 'post.php' );

        $this->assertTrue( true );
    }

    public function testEnqueueAdminAssetsOnHposOrderPage(): void
    {
        Functions\expect( 'wp_enqueue_style' )->once();
        Functions\expect( 'wp_enqueue_script' )->once();

        $this->assetLoader->enqueueAdminAssets( 'woocommerce_page_wc-orders' );

        $this->assertTrue( true );
    }

    // ─── enqueueFrontendAssets ──────────────────────────────────

    public function testEnqueueFrontendAssetsOnShopPage(): void
    {
        Functions\expect( 'is_shop' )->andReturn( true );
        Functions\expect( 'is_product_category' )->andReturn( false );
        Functions\expect( 'is_product_tag' )->andReturn( false );
        Functions\expect( 'is_product' )->andReturn( false );

        Functions\expect( 'wp_enqueue_style' )
            ->once()
            ->with( 'wdmw-frontend', Mockery::type( 'string' ), [], Mockery::any() );

        Functions\expect( 'wp_enqueue_script' )
            ->once()
            ->with( 'wdmw-frontend', Mockery::type( 'string' ), [], Mockery::any(), true );

        $this->assetLoader->enqueueFrontendAssets();

        $this->assertTrue( true );
    }

    public function testEnqueueFrontendAssetsSkipsNonShopPage(): void
    {
        Functions\expect( 'is_shop' )->andReturn( false );
        Functions\expect( 'is_product_category' )->andReturn( false );
        Functions\expect( 'is_product_tag' )->andReturn( false );
        Functions\expect( 'is_product' )->andReturn( false );

        Functions\expect( 'wp_enqueue_style' )->never();
        Functions\expect( 'wp_enqueue_script' )->never();

        $this->assetLoader->enqueueFrontendAssets();

        $this->assertTrue( true );
    }

    public function testEnqueueFrontendAssetsOnProductPage(): void
    {
        Functions\expect( 'is_shop' )->andReturn( false );
        Functions\expect( 'is_product_category' )->andReturn( false );
        Functions\expect( 'is_product_tag' )->andReturn( false );
        Functions\expect( 'is_product' )->andReturn( true );

        Functions\expect( 'wp_enqueue_style' )->once();
        Functions\expect( 'wp_enqueue_script' )->once();

        $this->assetLoader->enqueueFrontendAssets();

        $this->assertTrue( true );
    }
}
