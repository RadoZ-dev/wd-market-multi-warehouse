<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Frontend;

use WdMultiWarehouse\Contracts\StockRepositoryInterface;
use WdMultiWarehouse\Contracts\WarehouseRepositoryInterface;
use WdMultiWarehouse\Services\TemplateRenderer;

class CatalogFilter
{
    private WarehouseRepositoryInterface $warehouseRepository;
    private StockRepositoryInterface $stockRepository;
    private TemplateRenderer $renderer;

    public function __construct(
        WarehouseRepositoryInterface $warehouseRepository,
        StockRepositoryInterface $stockRepository,
        TemplateRenderer $renderer
    ) {
        $this->warehouseRepository = $warehouseRepository;
        $this->stockRepository     = $stockRepository;
        $this->renderer            = $renderer;
    }

    public function register(): void
    {
        add_action( 'woocommerce_before_shop_loop', [ $this, 'renderWarehouseFilter' ], 20 );
        add_action( 'woocommerce_product_query', [ $this, 'filterByWarehouse' ] );
        add_filter( 'woocommerce_shortcode_products_query', [ $this, 'filterShortcodeQuery' ] );
    }

    public function renderWarehouseFilter(): void
    {
        $warehouses        = $this->warehouseRepository->findActive();
        $selectedWarehouse = $this->getSelectedWarehouseId();

        if ( empty( $warehouses ) ) {
            return;
        }

        echo $this->renderer->render(
            'frontend/warehouse-filter.twig',
            [
                'warehouses'         => $warehouses,
                'selected_warehouse' => $selectedWarehouse,
                'filter_url'         => $this->getCurrentUrlWithoutWarehouse(),
                'label'              => __( 'Filter by warehouse:', 'wd-market-multi-warehouse' ),
                'all_label'          => __( 'All Warehouses', 'wd-market-multi-warehouse' ),
            ]
        );
    }

    public function filterByWarehouse( \WP_Query $query ): void
    {
        $warehouseId = $this->getSelectedWarehouseId();

        if ( $warehouseId === 0 ) {
            return;
        }

        $productIds = $this->stockRepository->getProductIdsInWarehouse( $warehouseId );

        if ( empty( $productIds ) ) {
            // Force empty result set
            $query->set( 'post__in', [ 0 ] );
            return;
        }

        $existingPostIn = $query->get( 'post__in' );

        if ( ! empty( $existingPostIn ) ) {
            $productIds = array_intersect( $existingPostIn, $productIds );
            if ( empty( $productIds ) ) {
                $productIds = [ 0 ];
            }
        }

        $query->set( 'post__in', $productIds );
    }

    public function filterShortcodeQuery( array $queryArgs ): array
    {
        $warehouseId = $this->getSelectedWarehouseId();

        if ( $warehouseId === 0 ) {
            return $queryArgs;
        }

        $productIds = $this->stockRepository->getProductIdsInWarehouse( $warehouseId );

        if ( empty( $productIds ) ) {
            $queryArgs['post__in'] = [ 0 ];
            return $queryArgs;
        }

        if ( ! empty( $queryArgs['post__in'] ) ) {
            $productIds = array_intersect( $queryArgs['post__in'], $productIds );
            if ( empty( $productIds ) ) {
                $productIds = [ 0 ];
            }
        }

        $queryArgs['post__in'] = $productIds;

        return $queryArgs;
    }

    private function getSelectedWarehouseId(): int
    {
        return absint( $_GET['wdmw_warehouse'] ?? 0 );
    }

    private function getCurrentUrlWithoutWarehouse(): string
    {
        $currentUrl = home_url( add_query_arg( null, null ) );

        return remove_query_arg( 'wdmw_warehouse', $currentUrl );
    }
}
