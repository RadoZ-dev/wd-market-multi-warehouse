<?php

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// WordPress constants not available in unit tests.
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'WDMW_VERSION' ) ) {
    define( 'WDMW_VERSION', '1.0.0' );
}

if ( ! defined( 'WDMW_PLUGIN_URL' ) ) {
    define( 'WDMW_PLUGIN_URL', 'https://example.com/wp-content/plugins/wd-market-multi-warehouse/' );
}

// Minimal WooCommerce stubs required by unit tests.
if ( ! class_exists( 'WC_Order_Item_Fee' ) ) {
    // phpcs:ignore
    class WC_Order_Item_Fee
    {
        private string $name       = '';
        private string $total      = '0';
        private string $tax_status = 'taxable';

        public function set_name( string $name ): void
        {
            $this->name = $name;
        }

        public function get_name(): string
        {
            return $this->name;
        }

        public function set_total( string $total ): void
        {
            $this->total = $total;
        }

        public function get_total(): string
        {
            return $this->total;
        }

        public function set_tax_status( string $status ): void
        {
            $this->tax_status = $status;
        }

        public function save(): void {}
    }
}
