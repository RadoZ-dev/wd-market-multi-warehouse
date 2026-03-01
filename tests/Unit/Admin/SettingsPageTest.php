<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use WdMultiWarehouse\Admin\SettingsPage;
use WdMultiWarehouse\Services\TemplateRenderer;

class SettingsPageTest extends TestCase
{
    private MockInterface $renderer;
    private SettingsPage $settingsPage;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->renderer     = Mockery::mock( TemplateRenderer::class );
        $this->settingsPage = new SettingsPage( $this->renderer );
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
            ->with( 'admin_menu', Mockery::type( 'array' ) );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'admin_init', [ $this->settingsPage, 'registerSettings' ] );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'admin_init', [ $this->settingsPage, 'handleSettingsSave' ] );

        $this->settingsPage->register();

        $this->assertTrue( true );
    }

    // ─── addSubmenuPage ────────────────────────────────────────

    public function testAddSubmenuPageCallsWordPressFunction(): void
    {
        Functions\expect( '__' )->andReturnFirstArg();

        Functions\expect( 'add_submenu_page' )
            ->once()
            ->with(
                'wdmw-warehouses',
                'Settings',
                'Settings',
                'manage_woocommerce',
                'wdmw-settings',
                Mockery::type( 'array' )
            );

        $this->settingsPage->addSubmenuPage();

        $this->assertTrue( true );
    }

    // ─── registerSettings ──────────────────────────────────────

    public function testRegisterSettingsRegistersThreeOptions(): void
    {
        Functions\expect( 'register_setting' )
            ->once()
            ->with( 'wdmw_settings', 'wdmw_google_api_key', Mockery::type( 'array' ) );

        Functions\expect( 'register_setting' )
            ->once()
            ->with( 'wdmw_settings', 'wdmw_extra_shipping_enabled', Mockery::type( 'array' ) );

        Functions\expect( 'register_setting' )
            ->once()
            ->with( 'wdmw_settings', 'wdmw_geocoding_provider', Mockery::type( 'array' ) );

        $this->settingsPage->registerSettings();

        $this->assertTrue( true );
    }

    // ─── renderSettingsPage ────────────────────────────────────

    public function testRenderSettingsPagePassesCorrectContext(): void
    {
        Functions\expect( '__' )->andReturnFirstArg();
        Functions\expect( 'get_option' )->andReturnUsing( function ( $option, $default = '' ) {
            $map = [
                'wdmw_google_api_key'         => 'test-api-key',
                'wdmw_extra_shipping_enabled'  => '1',
                'wdmw_geocoding_provider'      => 'google',
            ];
            return $map[ $option ] ?? $default;
        } );
        Functions\expect( 'wp_nonce_field' )->andReturn( '<nonce>' );

        $_GET = [];

        $this->renderer
            ->shouldReceive( 'display' )
            ->once()
            ->with( 'admin/settings.twig', Mockery::on( function ( array $context ) {
                return $context['google_api_key'] === 'test-api-key'
                    && $context['extra_shipping_enabled'] === '1'
                    && $context['geocoding_provider'] === 'google'
                    && $context['saved'] === false;
            } ) );

        $this->settingsPage->renderSettingsPage();

        $this->assertTrue( true );
    }

    public function testRenderSettingsPageShowsSavedFlagWhenPresent(): void
    {
        Functions\expect( '__' )->andReturnFirstArg();
        Functions\expect( 'get_option' )->andReturn( '' );
        Functions\expect( 'wp_nonce_field' )->andReturn( '<nonce>' );

        $_GET['settings-updated'] = '1';

        $this->renderer
            ->shouldReceive( 'display' )
            ->once()
            ->with( 'admin/settings.twig', Mockery::on( function ( array $context ) {
                return $context['saved'] === true;
            } ) );

        $this->settingsPage->renderSettingsPage();

        $this->assertTrue( true );
    }

    // ─── handleSettingsSave ────────────────────────────────────

    public function testHandleSettingsSaveBailsWithoutNonce(): void
    {
        $_POST = [];

        // Nothing should happen — no wp_die, no update_option.
        $this->settingsPage->handleSettingsSave();

        $this->assertTrue( true );
    }

    public function testHandleSettingsSaveDiesOnInvalidNonce(): void
    {
        $_POST['wdmw_settings_nonce'] = 'bad';

        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->with( 'bad', 'wdmw_save_settings' )
            ->andReturn( false );

        Functions\expect( '__' )->andReturnFirstArg();

        Functions\expect( 'wp_die' )
            ->once()
            ->with( 'Security check failed.' )
            ->andReturnUsing( function () {
                throw new \RuntimeException( 'wp_die called' );
            } );

        $this->expectException( \RuntimeException::class );

        $this->settingsPage->handleSettingsSave();
    }

    public function testHandleSettingsSaveReturnsEarlyWithoutCapability(): void
    {
        $_POST['wdmw_settings_nonce'] = 'valid';

        Functions\expect( 'wp_verify_nonce' )->andReturn( 1 );
        Functions\expect( 'current_user_can' )
            ->with( 'manage_woocommerce' )
            ->andReturn( false );

        // update_option should NOT be called.
        $this->settingsPage->handleSettingsSave();

        $this->assertTrue( true );
    }

    public function testHandleSettingsSaveUpdatesAllOptionsAndRedirects(): void
    {
        $_POST = [
            'wdmw_settings_nonce'         => 'valid',
            'wdmw_google_api_key'         => 'my-key',
            'wdmw_extra_shipping_enabled' => '1',
            'wdmw_geocoding_provider'     => 'google',
        ];

        Functions\expect( 'wp_verify_nonce' )->andReturn( 1 );
        Functions\expect( 'current_user_can' )->andReturn( true );
        Functions\expect( 'sanitize_text_field' )->andReturnUsing( function ( $val ) {
            return $val;
        } );

        Functions\expect( 'update_option' )
            ->once()
            ->with( 'wdmw_google_api_key', 'my-key' );

        Functions\expect( 'update_option' )
            ->once()
            ->with( 'wdmw_extra_shipping_enabled', '1' );

        Functions\expect( 'update_option' )
            ->once()
            ->with( 'wdmw_geocoding_provider', 'google' );

        Functions\expect( 'admin_url' )->andReturn( 'http://example.com/' );

        // Throw before exit is reached so PHPUnit survives.
        Functions\expect( 'wp_safe_redirect' )
            ->once()
            ->with( 'http://example.com/' )
            ->andReturnUsing( function () {
                throw new \RuntimeException( 'Redirect triggered' );
            } );

        $this->expectException( \RuntimeException::class );
        $this->expectExceptionMessage( 'Redirect triggered' );

        $this->settingsPage->handleSettingsSave();
    }

    public function testHandleSettingsSaveSetsExtraShippingToZeroWhenUnchecked(): void
    {
        $_POST = [
            'wdmw_settings_nonce'     => 'valid',
            'wdmw_geocoding_provider' => 'nominatim',
        ];

        Functions\expect( 'wp_verify_nonce' )->andReturn( 1 );
        Functions\expect( 'current_user_can' )->andReturn( true );
        Functions\expect( 'sanitize_text_field' )->andReturnUsing( function ( $val ) {
            return $val;
        } );

        Functions\expect( 'update_option' )
            ->once()
            ->with( 'wdmw_google_api_key', '' );

        Functions\expect( 'update_option' )
            ->once()
            ->with( 'wdmw_extra_shipping_enabled', '0' );

        Functions\expect( 'update_option' )
            ->once()
            ->with( 'wdmw_geocoding_provider', 'nominatim' );

        Functions\expect( 'admin_url' )->andReturn( 'http://example.com/' );

        Functions\expect( 'wp_safe_redirect' )
            ->once()
            ->andReturnUsing( function () {
                throw new \RuntimeException( 'Redirect triggered' );
            } );

        $this->expectException( \RuntimeException::class );

        $this->settingsPage->handleSettingsSave();
    }
}
