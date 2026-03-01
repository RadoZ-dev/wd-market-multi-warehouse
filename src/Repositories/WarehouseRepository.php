<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Repositories;

use WdMultiWarehouse\Contracts\WarehouseRepositoryInterface;
use WdMultiWarehouse\Models\Warehouse;

class WarehouseRepository implements WarehouseRepositoryInterface
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wdmw_warehouses';
    }

    public function findById( int $id ): ?Warehouse
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id )
        );

        return $row ? Warehouse::fromDbRow( $row ) : null;
    }

    public function findAll(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results( "SELECT * FROM {$this->table} ORDER BY name ASC" );

        return array_map( [ Warehouse::class, 'fromDbRow' ], $rows );
    }

    public function findActive(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name ASC"
        );

        return array_map( [ Warehouse::class, 'fromDbRow' ], $rows );
    }

    public function save( Warehouse $warehouse ): int
    {
        global $wpdb;

        $columnValues = [
            'name'                => $warehouse->getName(),
            'address'             => $warehouse->getAddress(),
            'latitude'            => $warehouse->getLatitude(),
            'longitude'           => $warehouse->getLongitude(),
            'is_active'           => $warehouse->isActive() ? 1 : 0,
            'extra_shipping_cost' => $warehouse->getExtraShippingCost(),
        ];

        $columnFormats = [ '%s', '%s', '%f', '%f', '%d', '%f' ];

        if ( $warehouse->getId() > 0 ) {
            $wpdb->update( $this->table, $columnValues, [ 'id' => $warehouse->getId() ], $columnFormats, [ '%d' ] );
            return $warehouse->getId();
        }

        $wpdb->insert( $this->table, $columnValues, $columnFormats );

        return (int) $wpdb->insert_id;
    }

    public function delete( int $id ): bool
    {
        global $wpdb;

        $rowsDeleted = $wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );

        return $rowsDeleted !== false;
    }
}
