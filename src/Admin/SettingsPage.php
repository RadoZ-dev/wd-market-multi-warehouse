<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Admin;

use WdMultiWarehouse\Services\TemplateRenderer;

class SettingsPage
{
    private TemplateRenderer $renderer;

    public function __construct( TemplateRenderer $renderer )
    {
        $this->renderer = $renderer;
    }

    public function register(): void
    {
        add_action( 'admin_menu', [ $this, 'addSubmenuPage' ] );
        add_action( 'admin_init', [ $this, 'registerSettings' ] );
        add_action( 'admin_init', [ $this, 'handleSettingsSave' ] );
    }

    public function addSubmenuPage(): void
    {
        add_submenu_page(
            'wdmw-warehouses',
            __( 'Settings', 'wd-market-multi-warehouse' ),
            __( 'Settings', 'wd-market-multi-warehouse' ),
            'manage_woocommerce',
            'wdmw-settings',
            [ $this, 'renderSettingsPage' ]
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            'wdmw_settings',
            'wdmw_google_api_key',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ]
        );

        register_setting(
            'wdmw_settings',
            'wdmw_extra_shipping_enabled',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '0',
            ]
        );

        register_setting(
            'wdmw_settings',
            'wdmw_geocoding_provider',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'nominatim',
            ]
        );
    }

    public function renderSettingsPage(): void
    {
        $this->renderer->display(
            'admin/settings.twig',
            [
                'page_title'             => __( 'Multi-Warehouse Settings', 'wd-market-multi-warehouse' ),
                'google_api_key'         => get_option( 'wdmw_google_api_key', '' ),
                'extra_shipping_enabled' => get_option( 'wdmw_extra_shipping_enabled', '0' ),
                'geocoding_provider'     => get_option( 'wdmw_geocoding_provider', 'nominatim' ),
                'nonce'                  => wp_nonce_field( 'wdmw_save_settings', 'wdmw_settings_nonce', true, false ),
                'saved'                  => isset( $_GET['settings-updated'] ),
            ]
        );
    }

    public function handleSettingsSave(): void
    {
        if ( ! isset( $_POST['wdmw_settings_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['wdmw_settings_nonce'], 'wdmw_save_settings' ) ) {
            wp_die( __( 'Security check failed.', 'wd-market-multi-warehouse' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        update_option(
            'wdmw_google_api_key',
            sanitize_text_field( $_POST['wdmw_google_api_key'] ?? '' )
        );

        update_option(
            'wdmw_extra_shipping_enabled',
            isset( $_POST['wdmw_extra_shipping_enabled'] ) ? '1' : '0'
        );

        update_option(
            'wdmw_geocoding_provider',
            sanitize_text_field( $_POST['wdmw_geocoding_provider'] ?? 'nominatim' )
        );

        wp_safe_redirect( admin_url( 'admin.php?page=wdmw-settings&settings-updated=1' ) );
        exit;
    }
}
