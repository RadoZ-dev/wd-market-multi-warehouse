<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Core;

/**
 * Enqueue admin and frontend CSS/JS assets.
 */
class AssetLoader
{
    public function register(): void
    {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueueFrontendAssets' ] );
    }

    /**
     * Enqueue admin styles and scripts on relevant admin pages only.
     */
    public function enqueueAdminAssets( string $hook ): void
    {
        // Load on our plugin pages and WooCommerce product screens.
        $pluginPages  = [ 'toplevel_page_wdmw-warehouses', 'warehouses_page_wdmw-settings' ];
        $productPages = [ 'post.php', 'post-new.php' ];

        $isPluginPage  = in_array( $hook, $pluginPages, true );
        $isProductPage = in_array( $hook, $productPages, true ) && $this->isProductScreen();
        $isOrderPage   = $this->isOrderScreen( $hook );

        if ( ! $isPluginPage && ! $isProductPage && ! $isOrderPage ) {
            return;
        }

        wp_enqueue_style(
            'wdmw-admin',
            WDMW_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WDMW_VERSION
        );

        wp_enqueue_script(
            'wdmw-admin',
            WDMW_PLUGIN_URL . 'assets/js/admin.js',
            [],
            WDMW_VERSION,
            true
        );
    }

    /**
     * Enqueue frontend styles and scripts on shop/product pages.
     */
    public function enqueueFrontendAssets(): void
    {
        if ( ! $this->isWooCommerceShopPage() ) {
            return;
        }

        wp_enqueue_style(
            'wdmw-frontend',
            WDMW_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            WDMW_VERSION
        );

        wp_enqueue_script(
            'wdmw-frontend',
            WDMW_PLUGIN_URL . 'assets/js/frontend.js',
            [],
            WDMW_VERSION,
            true
        );
    }

    private function isProductScreen(): bool
    {
        $screen = get_current_screen();

        return $screen && $screen->post_type === 'product';
    }

    /**
     * Detect WooCommerce order screens (legacy post type + HPOS).
     */
    private function isOrderScreen( string $hook ): bool
    {
        // HPOS order pages.
        if ( strpos( $hook, 'woocommerce_page_wc-orders' ) !== false ) {
            return true;
        }

        // Legacy order edit.
        if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            $screen = get_current_screen();
            return $screen && $screen->post_type === 'shop_order';
        }

        return false;
    }

    private function isWooCommerceShopPage(): bool
    {
        return function_exists( 'is_shop' )
            && ( is_shop() || is_product_category() || is_product_tag() || is_product() );
    }
}
