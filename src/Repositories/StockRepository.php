<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Repositories;

use WdMultiWarehouse\Contracts\StockRepositoryInterface;
use WdMultiWarehouse\Models\WarehouseStock;

class StockRepository implements StockRepositoryInterface
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wdmw_warehouse_stock';
    }

    public function findByWarehouseAndProduct( int $warehouseId, int $productId ): ?WarehouseStock
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE warehouse_id = %d AND product_id = %d",
                $warehouseId,
                $productId
            )
        );

        return $row ? WarehouseStock::fromDbRow( $row ) : null;
    }

    public function findByProduct( int $productId ): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE product_id = %d",
                $productId
            )
        );

        return array_map( [ WarehouseStock::class, 'fromDbRow' ], $rows );
    }

    public function findByWarehouse( int $warehouseId ): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE warehouse_id = %d",
                $warehouseId
            )
        );

        return array_map( [ WarehouseStock::class, 'fromDbRow' ], $rows );
    }

    public function save( WarehouseStock $stock ): int
    {
        global $wpdb;

        $columnValues = [
            'warehouse_id' => $stock->getWarehouseId(),
            'product_id'   => $stock->getProductId(),
            'quantity'     => $stock->getQuantity(),
        ];

        $columnFormats = [ '%d', '%d', '%d' ];

        if ( $stock->getId() > 0 ) {
            $wpdb->update( $this->table, $columnValues, [ 'id' => $stock->getId() ], $columnFormats, [ '%d' ] );
            return $stock->getId();
        }

        $existingStock = $this->findByWarehouseAndProduct(
            $stock->getWarehouseId(),
            $stock->getProductId()
        );

        if ( $existingStock !== null ) {
            $wpdb->update(
                $this->table,
                [ 'quantity' => $stock->getQuantity() ],
                [ 'id' => $existingStock->getId() ],
                [ '%d' ],
                [ '%d' ]
            );
            return $existingStock->getId();
        }

        $wpdb->insert( $this->table, $columnValues, $columnFormats );

        return (int) $wpdb->insert_id;
    }

    public function deleteByWarehouse( int $warehouseId ): bool
    {
        global $wpdb;

        $rowsDeleted = $wpdb->delete( $this->table, [ 'warehouse_id' => $warehouseId ], [ '%d' ] );

        return $rowsDeleted !== false;
    }

    public function getTotalStockForProduct( int $productId ): int
    {
        global $wpdb;

        $warehousesTable = $wpdb->prefix . 'wdmw_warehouses';

        $totalQuantity = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(s.quantity), 0)
             FROM {$this->table} s
             INNER JOIN {$warehousesTable} w ON s.warehouse_id = w.id
             WHERE s.product_id = %d AND w.is_active = 1",
                $productId
            )
        );

        return (int) $totalQuantity;
    }

    public function getStockMapForProduct( int $productId ): array
    {
        global $wpdb;

        $warehousesTable = $wpdb->prefix . 'wdmw_warehouses';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.warehouse_id, s.quantity
             FROM {$this->table} s
             INNER JOIN {$warehousesTable} w ON s.warehouse_id = w.id
             WHERE s.product_id = %d AND w.is_active = 1",
                $productId
            )
        );

        $stockByWarehouse = [];
        foreach ( $rows as $row ) {
            $stockByWarehouse[ (int) $row->warehouse_id ] = (int) $row->quantity;
        }

        return $stockByWarehouse;
    }

    public function getProductIdsInWarehouse( int $warehouseId ): array
    {
        global $wpdb;

        $productIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT product_id FROM {$this->table} WHERE warehouse_id = %d AND quantity > 0",
                $warehouseId
            )
        );

        return array_map( 'intval', $productIds );
    }
}
