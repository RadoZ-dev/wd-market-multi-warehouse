<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Admin;

use WdMultiWarehouse\Contracts\StockRepositoryInterface;
use WdMultiWarehouse\Contracts\WarehouseRepositoryInterface;
use WdMultiWarehouse\Services\StockService;
use WdMultiWarehouse\Services\TemplateRenderer;

class ProductStockAdmin
{
    private TemplateRenderer $renderer;
    private WarehouseRepositoryInterface $warehouseRepository;
    private StockRepositoryInterface $stockRepository;
    private StockService $stockService;

    public function __construct(
        TemplateRenderer $renderer,
        WarehouseRepositoryInterface $warehouseRepository,
        StockRepositoryInterface $stockRepository,
        StockService $stockService
    ) {
        $this->renderer            = $renderer;
        $this->warehouseRepository = $warehouseRepository;
        $this->stockRepository     = $stockRepository;
        $this->stockService        = $stockService;
    }

    public function register(): void
    {
        add_filter( 'woocommerce_product_data_tabs', [ $this, 'addWarehouseTab' ] );
        add_action( 'woocommerce_product_data_panels', [ $this, 'renderWarehousePanel' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'saveStockFields' ], 20 );
        add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'renderVariationStockFields' ], 10, 3 );
        add_action( 'woocommerce_save_product_variation', [ $this, 'saveVariationStockFields' ], 20, 2 );
    }

    /**
     * Add a dedicated "Warehouse" tab to the Product Data metabox.
     */
    public function addWarehouseTab( array $tabs ): array
    {
        $tabs['wdmw_warehouse'] = [
            'label'    => __( 'Warehouse', 'wd-market-multi-warehouse' ),
            'target'   => 'wdmw_warehouse_panel',
            'class'    => [ 'show_if_simple', 'show_if_variable', 'show_if_external' ],
            'priority' => 65,
        ];

        return $tabs;
    }

    /**
     * Render the Warehouse tab panel content.
     */
    public function renderWarehousePanel(): void
    {
        global $post;

        $productId  = $post->ID;
        $warehouses = $this->warehouseRepository->findActive();
        $stockMap   = $this->stockRepository->getStockMapForProduct( $productId );

        echo $this->renderer->render(
            'admin/product-stock.twig',
            [
                'warehouses'  => $warehouses,
                'stock_map'   => $stockMap,
                'product_id'  => $productId,
                'total_stock' => array_sum( $stockMap ),
            ]
        );
    }

    public function renderStockFields(): void
    {
        // Kept for backward compatibility — main UI now in Warehouse tab
    }

    public function saveStockFields( int $productId ): void
    {
        if ( ! isset( $_POST['wdmw_warehouse_stock'] ) || ! is_array( $_POST['wdmw_warehouse_stock'] ) ) {
            return;
        }

        $stockData = array_map( 'absint', $_POST['wdmw_warehouse_stock'] );

        foreach ( $stockData as $warehouseId => $quantity ) {
            $this->stockService->setStockForWarehouse(
                (int) $warehouseId,
                $productId,
                $quantity
            );
        }
    }

    public function renderVariationStockFields( int $loop, array $variationData, \WP_Post $variation ): void
    {
        $variationId = $variation->ID;
        $warehouses  = $this->warehouseRepository->findActive();
        $stockMap    = $this->stockRepository->getStockMapForProduct( $variationId );

        echo $this->renderer->render(
            'admin/variation-stock.twig',
            [
                'warehouses'   => $warehouses,
                'stock_map'    => $stockMap,
                'variation_id' => $variationId,
                'loop'         => $loop,
                'total_stock'  => array_sum( $stockMap ),
            ]
        );
    }

    public function saveVariationStockFields( int $variationId, int $loop ): void
    {
        if ( ! isset( $_POST['wdmw_variation_stock'][ $loop ] ) || ! is_array( $_POST['wdmw_variation_stock'][ $loop ] ) ) {
            return;
        }

        $stockData = array_map( 'absint', $_POST['wdmw_variation_stock'][ $loop ] );

        foreach ( $stockData as $warehouseId => $quantity ) {
            $this->stockService->setStockForWarehouse(
                (int) $warehouseId,
                $variationId,
                $quantity
            );
        }
    }
}
