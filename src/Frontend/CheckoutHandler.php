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
        // Allocate per-warehouse stock when the order reaches a paid status.
        // These hooks fire reliably for both classic and block checkout flows.
        add_action( 'woocommerce_order_status_processing', [ $this, 'maybeAllocateWarehouseStock' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'maybeAllocateWarehouseStock' ] );

        // Prevent WooCommerce from reducing stock globally — we handle it per warehouse.
        add_filter( 'woocommerce_can_reduce_order_stock', [ $this, 'preventDefaultStockReduction' ], 10, 2 );

        add_action( 'woocommerce_before_checkout_form', [ $this, 'displayWarehouseNotices' ] );
        add_action( 'woocommerce_cart_calculate_fees', [ $this, 'addExtraShippingFee' ] );

        // Admin order meta box — show warehouse allocations & extra cost.
        add_action( 'add_meta_boxes', [ $this, 'addWarehouseMetaBox' ] );
    }

    /**
     * Idempotent wrapper — allocate only once per order.
     */
    public function maybeAllocateWarehouseStock( int $orderId ): void
    {
        $order = wc_get_order( $orderId );

        if ( ! $order ) {
            return;
        }

        // Already processed — skip.
        if ( $order->get_meta( '_wdmw_stock_allocated' ) ) {
            return;
        }

        $this->allocateWarehouseStock( $order );
    }

    /**
     * Allocate and reduce stock from the selected warehouses.
     */
    public function allocateWarehouseStock( \WC_Order $order ): void
    {
        $shippingAddress = $this->buildShippingAddress( $order );
        $cartItems       = $this->getCartProductQuantities( $order );
        $allocations     = $this->selectionService->selectWarehousesForCart( $cartItems, $shippingAddress );
        $totalExtraCost  = 0.0;

        foreach ( $order->get_items() as $item ) {
            $productId = $item->get_product_id();

            if ( ! isset( $allocations[ $productId ] ) || empty( $allocations[ $productId ] ) ) {
                $order->add_order_note(
                    sprintf(
                    /* translators: %s: product name */
                        __( 'Warning: No warehouse allocation found for product "%s".', 'wd-market-multi-warehouse' ),
                        $item->get_name()
                    )
                );
                continue;
            }

            $productAllocations = $allocations[ $productId ];
            $warehouseNames     = [];

            foreach ( $productAllocations as $allocation ) {
                $warehouse   = $allocation['warehouse'];
                $allocateQty = $allocation['quantity'];

                $this->stockService->reduceStockFromWarehouse(
                    $warehouse->getId(),
                    $productId,
                    $allocateQty
                );

                $warehouseNames[] = sprintf( '%s (%d)', $warehouse->getName(), $allocateQty );

                if ( ! $allocation['is_closest'] && $allocation['extra_cost'] > 0 ) {
                    $totalExtraCost += $allocation['extra_cost'];
                }
            }

            // Store the primary (first) warehouse on the line item.
            $primaryWarehouse = $productAllocations[0]['warehouse'];
            $item->add_meta_data( '_wdmw_warehouse_id', $primaryWarehouse->getId() );
            $item->add_meta_data( '_wdmw_warehouse_name', $primaryWarehouse->getName() );
            $item->save();

            $order->add_order_note(
                sprintf(
                /* translators: 1: product name 2: warehouse allocation details */
                    __( 'Product "%1$s" allocated from: %2$s.', 'wd-market-multi-warehouse' ),
                    $item->get_name(),
                    implode( ', ', $warehouseNames )
                )
            );
        }

        // Mark as allocated so we don't process again and WC doesn't double-reduce.
        $order->update_meta_data( '_wdmw_stock_allocated', '1' );

        if ( $totalExtraCost > 0 ) {
            $order->update_meta_data( '_wdmw_extra_shipping_cost', $totalExtraCost );
            $order->add_order_note(
                sprintf(
                /* translators: %s: formatted extra shipping cost */
                    __( 'Additional warehouse shipping fee of %s applied.', 'wd-market-multi-warehouse' ),
                    wc_price( $totalExtraCost )
                )
            );
            $this->ensureExtraShippingFeeOnOrder( $order, $totalExtraCost );
            $order->calculate_totals(); // Recalculates & saves.
        } else {
            $order->save();
        }
    }

    /**
     * Ensure the extra-shipping fee line exists on the order.
     *
     * If the cart-time fee (addExtraShippingFee) was already transferred to the
     * order we leave it alone; otherwise we create a new WC_Order_Item_Fee so
     * the charge is never silently dropped (e.g. in block checkout).
     */
    private function ensureExtraShippingFeeOnOrder( \WC_Order $order, float $amount ): void
    {
        $feeName = __( 'Additional warehouse shipping', 'wd-market-multi-warehouse' );

        foreach ( $order->get_fees() as $feeItem ) {
            if ( $feeItem->get_name() === $feeName ) {
                // Fee already present — update amount if the actual allocation differs.
                if ( (float) $feeItem->get_total() !== $amount ) {
                    $feeItem->set_total( (string) $amount );
                    $feeItem->save();
                }
                return;
            }
        }

        // Fee not yet on the order — add it.
        $fee = new \WC_Order_Item_Fee();
        $fee->set_name( $feeName );
        $fee->set_total( (string) $amount );
        $fee->set_tax_status( 'none' );
        $order->add_item( $fee );
    }

    /**
     * Admin meta box: show warehouse allocations and extra cost on the order edit page.
     */
    public function addWarehouseMetaBox(): void
    {
        $screens = [ 'shop_order', 'woocommerce_page_wc-orders' ];

        foreach ( $screens as $screen ) {
            add_meta_box(
                'wdmw-warehouse-allocations',
                __( 'Warehouse Allocations', 'wd-market-multi-warehouse' ),
                [ $this, 'renderWarehouseMetaBox' ],
                $screen,
                'side',
                'default'
            );
        }
    }

    /**
     * Render the warehouse allocation details inside the meta box.
     *
     * @param \WP_Post|\WC_Order $postOrOrder Post object (legacy) or Order object (HPOS).
     */
    public function renderWarehouseMetaBox( $postOrOrder ): void
    {
        if ( $postOrOrder instanceof \WP_Post ) {
            $order = wc_get_order( $postOrOrder->ID );
        } else {
            $order = $postOrOrder;
        }

        if ( ! $order ) {
            return;
        }

        $hasAllocations = false;

        echo '<table class="widefat fixed striped" style="margin:0">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Product', 'wd-market-multi-warehouse' ) . '</th>';
        echo '<th>' . esc_html__( 'Warehouse', 'wd-market-multi-warehouse' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $order->get_items() as $item ) {
            $warehouseName = $item->get_meta( '_wdmw_warehouse_name' );

            if ( $warehouseName ) {
                $hasAllocations = true;
                echo '<tr>';
                echo '<td>' . esc_html( $item->get_name() ) . '</td>';
                echo '<td>' . esc_html( $warehouseName ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        if ( ! $hasAllocations ) {
            echo '<p>' . esc_html__( 'No warehouse allocations recorded.', 'wd-market-multi-warehouse' ) . '</p>';
        }

        $extraCost = (float) $order->get_meta( '_wdmw_extra_shipping_cost' );

        if ( $extraCost > 0 ) {
            echo '<p style="margin-top:8px"><strong>'
                . esc_html__( 'Extra warehouse shipping:', 'wd-market-multi-warehouse' )
                . '</strong> ' . wp_kses_post( wc_price( $extraCost ) ) . '</p>';
        }
    }

    /**
     * Prevent WooCommerce from reducing stock — we handle it in allocateWarehouseStock.
     */
    public function preventDefaultStockReduction( bool $canReduce, \WC_Order $order ): bool
    {
        if ( $order->get_meta( '_wdmw_stock_allocated' ) ) {
            return false;
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
        foreach ( $allocations as $productId => $productAllocations ) {
            foreach ( $productAllocations as $allocation ) {
                if ( ! $allocation['is_closest'] && $allocation['extra_cost'] > 0 ) {
                    $product = wc_get_product( $productId );
                    if ( $product ) {
                        $nonClosestProducts[] = $product->get_name();
                    }
                    break; // Only list the product name once.
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
     * Add extra shipping fee if products are fulfilled from non-closest warehouses.
     *
     * Hooked directly to woocommerce_cart_calculate_fees so the fee is included
     * in the cart/order totals (shipping packages are calculated after fees).
     */
    public function addExtraShippingFee(): void
    {
        if ( get_option( 'wdmw_extra_shipping_enabled', '0' ) !== '1' ) {
            return;
        }

        $shippingAddress = $this->getCheckoutShippingAddress();

        if ( empty( $shippingAddress ) ) {
            return;
        }

        $cartItems   = $this->getCartItems();
        $allocations = $this->selectionService->selectWarehousesForCart( $cartItems, $shippingAddress );

        $totalExtraCost = 0.0;
        foreach ( $allocations as $productAllocations ) {
            foreach ( $productAllocations as $allocation ) {
                if ( ! $allocation['is_closest'] && $allocation['extra_cost'] > 0 ) {
                    $totalExtraCost += $allocation['extra_cost'];
                }
            }
        }

        if ( $totalExtraCost > 0 ) {
            WC()->cart->add_fee(
                __( 'Additional warehouse shipping', 'wd-market-multi-warehouse' ),
                $totalExtraCost
            );
        }
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
