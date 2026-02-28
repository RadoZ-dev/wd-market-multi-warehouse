<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Core;

class Activator
{
    public static function activate(): void
    {
        self::createTables();
        self::addDefaultOptions();
    }

    private static function createTables(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $warehousesTable = $wpdb->prefix . 'wdmw_warehouses';
        $stockTable      = $wpdb->prefix . 'wdmw_warehouse_stock';

        $sql = "CREATE TABLE {$warehousesTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            address TEXT NOT NULL,
            latitude DECIMAL(10, 8) DEFAULT NULL,
            longitude DECIMAL(11, 8) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            extra_shipping_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_is_active (is_active)
        ) {$charsetCollate};

        CREATE TABLE {$stockTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            warehouse_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            quantity INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY idx_warehouse_product (warehouse_id, product_id),
            KEY idx_product_id (product_id),
            KEY idx_warehouse_id (warehouse_id)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'wdmw_db_version', WDMW_VERSION );
    }

    private static function addDefaultOptions(): void
    {
        add_option( 'wdmw_google_api_key', '' );
        add_option( 'wdmw_extra_shipping_enabled', '0' );
        add_option( 'wdmw_geocoding_provider', 'nominatim' );
    }
}
