<?php
/**
 * Plugin Name:       WD Market Multi-Warehouse
 * Plugin URI:        https://github.com/RadoZ-dev/wd-market-multi-warehouse
 * Description:       Multi-warehouse inventory management for WooCommerce — per-warehouse stock, proximity-based allocation at checkout, and catalog filtering.
 * Version:           1.0.0
 * Author:            Radoslav Zdravkovic
 * Author URI:        https://github.com/RadoZ-dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wd-market-multi-warehouse
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:   8.0
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'WDMW_VERSION', '1.0.0' );
define( 'WDMW_PLUGIN_FILE', __FILE__ );
define( 'WDMW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WDMW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WDMW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WDMW_PLUGIN_DIR . 'vendor/autoload.php';

use WdMultiWarehouse\Core\Plugin;

/**
 * Halt activation when WooCommerce is not active.
 */
register_activation_hook(
    __FILE__,
    static function (): void {
        if ( ! class_exists( 'WooCommerce' )
        && ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true )
        ) {
            deactivate_plugins( WDMW_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'WD Market Multi-Warehouse requires WooCommerce to be installed and active.', 'wd-market-multi-warehouse' ),
                esc_html__( 'Plugin Activation Error', 'wd-market-multi-warehouse' ),
                [ 'back_link' => true ]
            );
        }
    }
);

/**
 * Declare compatibility with WooCommerce features (HPOS, Cart/Checkout Blocks).
 */
add_action(
    'before_woocommerce_init',
    static function (): void {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                __FILE__,
                true
            );
        }
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action(
                'admin_notices',
                static function (): void {
                    echo '<div class="notice notice-error"><p>';
                    echo esc_html__( 'WD Market Multi-Warehouse requires WooCommerce to be installed and active.', 'wd-market-multi-warehouse' );
                    echo '</p></div>';
                }
            );
            return;
        }

        Plugin::getInstance()->init();
    }
);
