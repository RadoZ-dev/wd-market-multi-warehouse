<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Services;

use WdMultiWarehouse\Contracts\StockRepositoryInterface;
use WdMultiWarehouse\Contracts\WarehouseRepositoryInterface;
use WdMultiWarehouse\Models\WarehouseStock;

class StockService
{
    private StockRepositoryInterface $stockRepository;
    private WarehouseRepositoryInterface $warehouseRepository;

    public function __construct(
        StockRepositoryInterface $stockRepository,
        WarehouseRepositoryInterface $warehouseRepository
    ) {
        $this->stockRepository     = $stockRepository;
        $this->warehouseRepository = $warehouseRepository;
    }

    public function registerHooks(): void
    {
        add_filter( 'woocommerce_product_get_stock_quantity', [ $this, 'filterStockQuantity' ], 10, 2 );
        add_filter( 'woocommerce_product_get_stock_status', [ $this, 'filterStockStatus' ], 10, 2 );
        add_filter( 'woocommerce_product_get_manage_stock', [ $this, 'forceManageStock' ], 10, 2 );
    }

    /**
     * Override WooCommerce stock quantity with the sum across all active warehouses.
     */
    public function filterStockQuantity( $quantity, $product ): int
    {
        if ( ! $product instanceof \WC_Product ) {
            return (int) $quantity;
        }

        return $this->getTotalStock( $product->get_id() );
    }

    /**
     * Override WooCommerce stock status based on total warehouse stock.
     */
    public function filterStockStatus( $status, $product ): string
    {
        if ( ! $product instanceof \WC_Product ) {
            return $status;
        }

        $total = $this->getTotalStock( $product->get_id() );

        return $total > 0 ? 'instock' : 'outofstock';
    }

    /**
     * Force "manage stock" to true for products that have warehouse stock entries.
     */
    public function forceManageStock( $manageStock, $product )
    {
        if ( ! $product instanceof \WC_Product ) {
            return $manageStock;
        }

        $stocks = $this->stockRepository->findByProduct( $product->get_id() );

        return ! empty( $stocks ) ? true : $manageStock;
    }

    public function getTotalStock( int $productId ): int
    {
        return $this->stockRepository->getTotalStockForProduct( $productId );
    }

    public function getStockForWarehouse( int $warehouseId, int $productId ): int
    {
        $stock = $this->stockRepository->findByWarehouseAndProduct( $warehouseId, $productId );

        return $stock !== null ? $stock->getQuantity() : 0;
    }

    public function setStockForWarehouse( int $warehouseId, int $productId, int $quantity ): void
    {
        $stock = $this->stockRepository->findByWarehouseAndProduct( $warehouseId, $productId );

        if ( $stock === null ) {
            $stock = new WarehouseStock( 0, $warehouseId, $productId, $quantity );
        } else {
            $stock->setQuantity( $quantity );
        }

        $this->stockRepository->save( $stock );
        $this->syncWooCommerceStock( $productId );
    }

    public function reduceStockFromWarehouse( int $warehouseId, int $productId, int $amount ): void
    {
        $stock = $this->stockRepository->findByWarehouseAndProduct( $warehouseId, $productId );

        if ( $stock === null ) {
            throw new \RuntimeException(
                sprintf( 'No stock entry found for warehouse %d, product %d.', $warehouseId, $productId )
            );
        }

        $stock->reduceStock( $amount );
        $this->stockRepository->save( $stock );
        $this->syncWooCommerceStock( $productId );
    }

    /**
     * Keep WooCommerce's internal _stock meta in sync with warehouse totals.
     */
    public function syncWooCommerceStock( int $productId ): void
    {
        $total   = $this->getTotalStock( $productId );
        $product = wc_get_product( $productId );

        if ( ! $product ) {
            return;
        }

        // Temporarily unhook our filter to prevent recursion
        remove_filter( 'woocommerce_product_get_stock_quantity', [ $this, 'filterStockQuantity' ], 10 );

        $product->set_stock_quantity( $total );
        $product->set_stock_status( $total > 0 ? 'instock' : 'outofstock' );
        $product->set_manage_stock( true );
        $product->save();

        add_filter( 'woocommerce_product_get_stock_quantity', [ $this, 'filterStockQuantity' ], 10, 2 );
    }
}
