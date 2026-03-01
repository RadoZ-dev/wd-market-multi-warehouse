<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Core;

use WdMultiWarehouse\Admin\AdminMenu;
use WdMultiWarehouse\Admin\ProductStockAdmin;
use WdMultiWarehouse\Admin\SettingsPage;
use WdMultiWarehouse\Frontend\CatalogFilter;
use WdMultiWarehouse\Frontend\CheckoutHandler;
use WdMultiWarehouse\Repositories\StockRepository;
use WdMultiWarehouse\Repositories\WarehouseRepository;
use WdMultiWarehouse\Services\DistanceService;
use WdMultiWarehouse\Services\GeocodingService;
use WdMultiWarehouse\Services\StockService;
use WdMultiWarehouse\Services\TemplateRenderer;
use WdMultiWarehouse\Services\WarehouseSelectionService;
use WdMultiWarehouse\Traits\Singleton;

final class Plugin
{
    use Singleton;

    /**
     * Bootstrap all plugin dependencies and register hooks.
     *
     * This is the Composition Root — the single place where concrete
     * implementations are wired to their interfaces via constructor injection.
     */
    public function init(): void
    {
        // Infrastructure.
        $renderer            = new TemplateRenderer();
        $warehouseRepository = new WarehouseRepository();
        $stockRepository     = new StockRepository();

        // Services.
        $geocoder         = new GeocodingService();
        $distanceService  = new DistanceService();
        $stockService     = new StockService( $stockRepository, $warehouseRepository );
        $selectionService = new WarehouseSelectionService(
            $warehouseRepository,
            $stockRepository,
            $distanceService,
            $geocoder
        );

        // Admin (only load in wp-admin context).
        if ( is_admin() ) {
            $adminMenu = new AdminMenu( $renderer, $warehouseRepository, $geocoder );
            $adminMenu->register();

            $settingsPage = new SettingsPage( $renderer );
            $settingsPage->register();

            $productStockAdmin = new ProductStockAdmin(
                $renderer,
                $warehouseRepository,
                $stockRepository,
                $stockService
            );
            $productStockAdmin->register();
        }

        // Frontend (checkout + catalog).
        $checkoutHandler = new CheckoutHandler( $selectionService, $stockService );
        $checkoutHandler->register();

        $catalogFilter = new CatalogFilter( $warehouseRepository, $stockRepository, $renderer );
        $catalogFilter->register();

        // WooCommerce stock integration (both admin and frontend).
        $stockService->registerHooks();
    }
}
