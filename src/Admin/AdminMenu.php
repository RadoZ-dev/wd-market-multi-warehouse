<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Admin;

use WdMultiWarehouse\Contracts\GeocoderInterface;
use WdMultiWarehouse\Contracts\WarehouseRepositoryInterface;
use WdMultiWarehouse\Models\Warehouse;
use WdMultiWarehouse\Services\TemplateRenderer;

class AdminMenu
{
    private TemplateRenderer $renderer;
    private WarehouseRepositoryInterface $warehouseRepository;
    private GeocoderInterface $geocoder;

    public function __construct(
        TemplateRenderer $renderer,
        WarehouseRepositoryInterface $warehouseRepository,
        GeocoderInterface $geocoder
    ) {
        $this->renderer            = $renderer;
        $this->warehouseRepository = $warehouseRepository;
        $this->geocoder            = $geocoder;
    }

    public function register(): void
    {
        add_action( 'admin_menu', [ $this, 'addMenuPages' ] );
        add_action( 'admin_init', [ $this, 'handleFormSubmissions' ] );
    }

    public function addMenuPages(): void
    {
        add_menu_page(
            __( 'Warehouses', 'wd-market-multi-warehouse' ),
            __( 'Warehouses', 'wd-market-multi-warehouse' ),
            'manage_woocommerce',
            'wdmw-warehouses',
            [ $this, 'renderWarehousesPage' ],
            'dashicons-building',
            56
        );
    }

    public function renderWarehousesPage(): void
    {
        $action = sanitize_text_field( $_GET['action'] ?? 'list' );
        $id     = absint( $_GET['warehouse_id'] ?? 0 );

        switch ( $action ) {
            case 'add':
            case 'edit':
                $this->renderWarehouseForm( $id );
                break;
            default:
                $this->renderWarehouseList();
                break;
        }
    }

    public function handleFormSubmissions(): void
    {
        if ( ! isset( $_POST['wdmw_warehouse_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['wdmw_warehouse_nonce'], 'wdmw_save_warehouse' ) ) {
            wp_die( __( 'Security check failed.', 'wd-market-multi-warehouse' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Unauthorized access.', 'wd-market-multi-warehouse' ) );
        }

        $action = sanitize_text_field( $_POST['wdmw_action'] ?? '' );

        if ( $action === 'save_warehouse' ) {
            $this->saveWarehouse();
        } elseif ( $action === 'delete_warehouse' ) {
            $this->deleteWarehouse();
        }
    }

    private function renderWarehouseList(): void
    {
        $warehouses = $this->warehouseRepository->findAll();

        $this->renderer->display(
            'admin/warehouse-list.twig',
            [
                'warehouses' => $warehouses,
                'add_url'    => admin_url( 'admin.php?page=wdmw-warehouses&action=add' ),
                'page_title' => __( 'Warehouses', 'wd-market-multi-warehouse' ),
            ]
        );
    }

    private function renderWarehouseForm( int $id ): void
    {
        $warehouse = $id > 0 ? $this->warehouseRepository->findById( $id ) : null;

        $this->renderer->display(
            'admin/warehouse-form.twig',
            [
                'warehouse'  => $warehouse,
                'is_edit'    => $warehouse !== null,
                'nonce'      => wp_nonce_field( 'wdmw_save_warehouse', 'wdmw_warehouse_nonce', true, false ),
                'back_url'   => admin_url( 'admin.php?page=wdmw-warehouses' ),
                'page_title' => $warehouse
                    ? __( 'Edit Warehouse', 'wd-market-multi-warehouse' )
                    : __( 'Add Warehouse', 'wd-market-multi-warehouse' ),
            ]
        );
    }

    private function saveWarehouse(): void
    {
        $id      = absint( $_POST['warehouse_id'] ?? 0 );
        $name    = sanitize_text_field( $_POST['warehouse_name'] ?? '' );
        $address = sanitize_textarea_field( $_POST['warehouse_address'] ?? '' );
        $active  = isset( $_POST['warehouse_active'] ) ? true : false;
        $extra   = floatval( $_POST['warehouse_extra_shipping'] ?? 0 );

        $warehouse = $id > 0 ? $this->warehouseRepository->findById( $id ) : null;

        if ( $warehouse === null ) {
            $warehouse = new Warehouse();
        }

        $warehouse->setName( $name );
        $warehouse->setAddress( $address );
        $warehouse->setIsActive( $active );
        $warehouse->setExtraShippingCost( $extra );

        $this->geocodeWarehouse( $warehouse );

        $this->warehouseRepository->save( $warehouse );

        wp_safe_redirect( admin_url( 'admin.php?page=wdmw-warehouses&saved=1' ) );
        exit;
    }

    private function deleteWarehouse(): void
    {
        $id = absint( $_POST['warehouse_id'] ?? 0 );

        if ( $id > 0 ) {
            $this->warehouseRepository->delete( $id );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=wdmw-warehouses&deleted=1' ) );
        exit;
    }

    private function geocodeWarehouse( Warehouse $warehouse ): void
    {
        if ( empty( $warehouse->getAddress() ) ) {
            return;
        }

        $coordinates = $this->geocoder->geocode( $warehouse->getAddress() );

        if ( $coordinates !== null ) {
            $warehouse->setLatitude( $coordinates[0] );
            $warehouse->setLongitude( $coordinates[1] );
        }
    }
}
