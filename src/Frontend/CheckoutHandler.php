<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Frontend;

use WdMultiWarehouse\Services\StockService;
use WdMultiWarehouse\Services\WarehouseSelectionService;

class CheckoutHandler
{
    private WarehouseSelectionService $selectionService;
    private StockService $stockService;

    public function __construct(
        WarehouseSelectionService $selectionService,
        StockService $stockService
    ) {
        $this->selectionService = $selectionService;
        $this->stockService     = $stockService;
    }

    public function register(): void
    {
        add_action( 'woocommerce_checkout_order_processed', [ $this, 'allocateWarehouseStock' ], 10, 3 );
        add_action( 'woocommerce_before_checkout_form', [ $this, 'displayWarehouseNotices' ] );
        add_filter( 'woocommerce_cart_shipping_packages', [ $this, 'addExtraShippingCost' ] );

        // Prevent WooCommerce from reducing stock globally — we handle it per warehouse
        add_filter( 'woocommerce_can_reduce_order_stock', [ $this, 'preventDefaultStockReduction' ], 10, 2 );
    }

    /**
     * After the order is created, allocate and reduce stock from the selected warehouses.
     */
    public function allocateWarehouseStock( int $orderId, array $postedData, \WC_Order $order ): void
    {
        $shippingAddress = $this->buildShippingAddress( $order );
        $cartItems       = $this->getCartProductQuantities( $order );
        $allocations     = $this->selectionService->selectWarehousesForCart( $cartItems, $shippingAddress );
        $totalExtraCost  = 0.0;

        foreach ( $order->get_items() as $item ) {
            $productId = $item->get_product_id();
            $quantity  = $item->get_quantity();

            if ( ! isset( $allocations[ $productId ] ) ) {
                $order->add_order_note(
                    sprintf(
                    /* translators: %s: product name */
                        __( 'Warning: No warehouse allocation found for product "%s".', 'wd-market-multi-warehouse' ),
                        $item->get_name()
                    )
                );
                continue;
            }

            $allocation = $allocations[ $productId ];
            $warehouse  = $allocation['warehouse'];

            $this->stockService->reduceStockFromWarehouse(
                $warehouse->getId(),
                $productId,
                $quantity
            );

            $item->add_meta_data( '_wdmw_warehouse_id', $warehouse->getId() );
            $item->add_meta_data( '_wdmw_warehouse_name', $warehouse->getName() );
            $item->save();

            if ( ! $allocation['is_closest'] && $allocation['extra_cost'] > 0 ) {
                $totalExtraCost += $allocation['extra_cost'];
            }

            $order->add_order_note(
                sprintf(
                /* translators: 1: product name 2: warehouse name */
                    __( 'Product "%1$s" allocated from warehouse "%2$s".', 'wd-market-multi-warehouse' ),
                    $item->get_name(),
                    $warehouse->getName()
                )
            );
        }

        if ( $totalExtraCost > 0 ) {
            $order->update_meta_data( '_wdmw_extra_shipping_cost', $totalExtraCost );
        }

        $order->save();
    }

    /**
     * Prevent WooCommerce from reducing stock — we handle it in allocateWarehouseStock.
     */
    public function preventDefaultStockReduction( bool $canReduce, \WC_Order $order ): bool
    {
        // Only prevent if we've done our allocation
        $items = $order->get_items();
        foreach ( $items as $item ) {
            if ( $item->get_meta( '_wdmw_warehouse_id' ) ) {
                return false;
            }
        }

        return $canReduce;
    }

    /**
     * Display a notice if extra shipping costs apply due to non-closest warehouse fulfillment.
     */
    public function displayWarehouseNotices(): void
    {
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return;
        }

        $extraShippingEnabled = get_option( 'wdmw_extra_shipping_enabled', '0' ) === '1';

        if ( ! $extraShippingEnabled ) {
            return;
        }

        $shippingAddress = $this->getCheckoutShippingAddress();

        if ( empty( $shippingAddress ) ) {
            return;
        }

        $cartItems   = $this->getCartItems();
        $allocations = $this->selectionService->selectWarehousesForCart( $cartItems, $shippingAddress );

        $nonClosestProducts = [];
        foreach ( $allocations as $productId => $allocation ) {
            if ( ! $allocation['is_closest'] && $allocation['extra_cost'] > 0 ) {
                $product = wc_get_product( $productId );
                if ( $product ) {
                    $nonClosestProducts[] = $product->get_name();
                }
            }
        }

        if ( ! empty( $nonClosestProducts ) ) {
            wc_print_notice(
                sprintf(
                    /* translators: %s: list of product names */
                    __( 'Note: An additional shipping charge may apply for the following products as they will be shipped from a more distant warehouse: %s', 'wd-market-multi-warehouse' ),
                    implode( ', ', $nonClosestProducts )
                ),
                'notice'
            );
        }
    }

    /**
     * Add extra shipping cost as a fee if products are fulfilled from non-closest warehouses.
     */
    public function addExtraShippingCost( array $packages ): array
    {
        if ( get_option( 'wdmw_extra_shipping_enabled', '0' ) !== '1' ) {
            return $packages;
        }

        $shippingAddress = $this->getCheckoutShippingAddress();

        if ( empty( $shippingAddress ) ) {
            return $packages;
        }

        $cartItems   = $this->getCartItems();
        $allocations = $this->selectionService->selectWarehousesForCart( $cartItems, $shippingAddress );

        $totalExtraCost = 0.0;
        foreach ( $allocations as $allocation ) {
            if ( ! $allocation['is_closest'] && $allocation['extra_cost'] > 0 ) {
                $totalExtraCost += $allocation['extra_cost'];
            }
        }

        if ( $totalExtraCost > 0 ) {
            add_action(
                'woocommerce_cart_calculate_fees',
                static function () use ( $totalExtraCost ): void {
                    WC()->cart->add_fee(
                        __( 'Additional warehouse shipping', 'wd-market-multi-warehouse' ),
                        $totalExtraCost
                    );
                }
            );
        }

        return $packages;
    }

    private function buildShippingAddress( \WC_Order $order ): string
    {
        $parts = array_filter(
            [
                $order->get_shipping_address_1(),
                $order->get_shipping_address_2(),
                $order->get_shipping_city(),
                $order->get_shipping_state(),
                $order->get_shipping_postcode(),
                $order->get_shipping_country(),
            ]
        );

        return implode( ', ', $parts );
    }

    /**
     * @return array<int, int> productId => quantity
     */
    private function getCartProductQuantities( \WC_Order $order ): array
    {
        $items = [];
        foreach ( $order->get_items() as $item ) {
            $productId           = $item->get_product_id();
            $items[ $productId ] = ( $items[ $productId ] ?? 0 ) + $item->get_quantity();
        }
        return $items;
    }

    /**
     * @return array<int, int> productId => quantity
     */
    private function getCartItems(): array
    {
        $items = [];
        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart() as $cartItem ) {
                $productId           = $cartItem['product_id'];
                $items[ $productId ] = ( $items[ $productId ] ?? 0 ) + $cartItem['quantity'];
            }
        }
        return $items;
    }

    private function getCheckoutShippingAddress(): string
    {
        $customer = WC()->customer;

        if ( ! $customer ) {
            return '';
        }

        $parts = array_filter(
            [
                $customer->get_shipping_address_1(),
                $customer->get_shipping_city(),
                $customer->get_shipping_state(),
                $customer->get_shipping_postcode(),
                $customer->get_shipping_country(),
            ]
        );

        return implode( ', ', $parts );
    }
}
