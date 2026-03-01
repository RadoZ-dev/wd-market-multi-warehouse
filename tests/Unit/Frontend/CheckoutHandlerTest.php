<?php

declare(strict_types=1);

namespace Tests\Unit\Frontend;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use WdMultiWarehouse\Frontend\CheckoutHandler;
use WdMultiWarehouse\Models\Warehouse;
use WdMultiWarehouse\Services\StockService;
use WdMultiWarehouse\Services\WarehouseSelectionService;

class CheckoutHandlerTest extends TestCase
{
    private MockInterface $selectionService;
    private MockInterface $stockService;
    private CheckoutHandler $checkoutHandler;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->selectionService = Mockery::mock( WarehouseSelectionService::class );
        $this->stockService     = Mockery::mock( StockService::class );

        $this->checkoutHandler = new CheckoutHandler(
            $this->selectionService,
            $this->stockService
        );
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ─── register ──────────────────────────────────────────────

    public function testRegisterAddsSixHooks(): void
    {
        Functions\expect( 'add_action' )
            ->once()
            ->with( 'woocommerce_order_status_processing', Mockery::type( 'array' ) );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'woocommerce_order_status_completed', Mockery::type( 'array' ) );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'woocommerce_before_checkout_form', Mockery::type( 'array' ) );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'woocommerce_cart_calculate_fees', Mockery::type( 'array' ) );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'add_meta_boxes', Mockery::type( 'array' ) );

        Functions\expect( 'add_filter' )
            ->once()
            ->with( 'woocommerce_can_reduce_order_stock', Mockery::type( 'array' ), 10, 2 );

        $this->checkoutHandler->register();

        $this->assertTrue( true );
    }

    // ─── allocateWarehouseStock ────────────────────────────────

    public function testAllocateWarehouseStockReducesStockAndSavesMeta(): void
    {
        $warehouse = Warehouse::fromDbRow( (object) [
            'id' => '1', 'name' => 'Sofia WH', 'address' => 'Sofia',
            'latitude' => '42.0', 'longitude' => '23.0',
            'is_active' => '1', 'extra_shipping_cost' => '5.00',
        ] );

        $item = Mockery::mock( \stdClass::class );
        $item->shouldReceive( 'get_product_id' )->andReturn( 100 );
        $item->shouldReceive( 'get_quantity' )->andReturn( 2 );
        $item->shouldReceive( 'get_name' )->andReturn( 'Test Product' );
        $item->shouldReceive( 'add_meta_data' )->with( '_wdmw_warehouse_id', 1 )->once();
        $item->shouldReceive( 'add_meta_data' )->with( '_wdmw_warehouse_name', 'Sofia WH' )->once();
        $item->shouldReceive( 'save' )->once();

        $order = Mockery::mock( \WC_Order::class );
        $order->shouldReceive( 'get_shipping_address_1' )->andReturn( 'Street 1' );
        $order->shouldReceive( 'get_shipping_address_2' )->andReturn( '' );
        $order->shouldReceive( 'get_shipping_city' )->andReturn( 'Plovdiv' );
        $order->shouldReceive( 'get_shipping_state' )->andReturn( '' );
        $order->shouldReceive( 'get_shipping_postcode' )->andReturn( '4000' );
        $order->shouldReceive( 'get_shipping_country' )->andReturn( 'BG' );
        $order->shouldReceive( 'get_items' )->andReturn( [ $item ] );
        $order->shouldReceive( 'add_order_note' )->once();
        $order->shouldReceive( 'update_meta_data' )
            ->once()
            ->with( '_wdmw_stock_allocated', '1' );
        $order->shouldReceive( 'save' )->once();

        Functions\expect( '__' )->andReturnFirstArg();

        $this->selectionService
            ->shouldReceive( 'selectWarehousesForCart' )
            ->once()
            ->with( [ 100 => 2 ], 'Street 1, Plovdiv, 4000, BG' )
            ->andReturn( [
                100 => [
                    [
                        'warehouse'  => $warehouse,
                        'quantity'   => 2,
                        'is_closest' => true,
                        'extra_cost' => 0.0,
                    ],
                ],
            ] );

        $this->stockService
            ->shouldReceive( 'reduceStockFromWarehouse' )
            ->once()
            ->with( 1, 100, 2 );

        $this->checkoutHandler->allocateWarehouseStock( $order );

        $this->assertTrue( true );
    }

    public function testAllocateWarehouseStockAddsExtraCostForNonClosest(): void
    {
        $warehouse = Warehouse::fromDbRow( (object) [
            'id' => '2', 'name' => 'Remote WH', 'address' => 'Varna',
            'latitude' => '43.0', 'longitude' => '28.0',
            'is_active' => '1', 'extra_shipping_cost' => '10.00',
        ] );

        $item = Mockery::mock( \stdClass::class );
        $item->shouldReceive( 'get_product_id' )->andReturn( 50 );
        $item->shouldReceive( 'get_quantity' )->andReturn( 1 );
        $item->shouldReceive( 'get_name' )->andReturn( 'Widget' );
        $item->shouldReceive( 'add_meta_data' )->twice();
        $item->shouldReceive( 'save' )->once();

        $order = Mockery::mock( \WC_Order::class );
        $order->shouldReceive( 'get_shipping_address_1' )->andReturn( 'Addr' );
        $order->shouldReceive( 'get_shipping_address_2' )->andReturn( '' );
        $order->shouldReceive( 'get_shipping_city' )->andReturn( 'Sofia' );
        $order->shouldReceive( 'get_shipping_state' )->andReturn( '' );
        $order->shouldReceive( 'get_shipping_postcode' )->andReturn( '1000' );
        $order->shouldReceive( 'get_shipping_country' )->andReturn( 'BG' );
        $order->shouldReceive( 'get_items' )->andReturn( [ $item ] );
        $order->shouldReceive( 'add_order_note' )->twice();
        $order->shouldReceive( 'update_meta_data' )
            ->once()
            ->with( '_wdmw_extra_shipping_cost', 10.0 );
        $order->shouldReceive( 'update_meta_data' )
            ->once()
            ->with( '_wdmw_stock_allocated', '1' );
        // No existing fee → ensureExtraShippingFeeOnOrder adds one.
        $order->shouldReceive( 'get_fees' )->andReturn( [] );
        $order->shouldReceive( 'add_item' )
            ->once()
            ->with( Mockery::type( \WC_Order_Item_Fee::class ) );
        $order->shouldReceive( 'calculate_totals' )->once();

        Functions\expect( '__' )->andReturnFirstArg();
        Functions\expect( 'wc_price' )->andReturn( '$10.00' );

        $this->selectionService
            ->shouldReceive( 'selectWarehousesForCart' )
            ->andReturn( [
                50 => [
                    [
                        'warehouse'  => $warehouse,
                        'quantity'   => 1,
                        'is_closest' => false,
                        'extra_cost' => 10.0,
                    ],
                ],
            ] );

        $this->stockService
            ->shouldReceive( 'reduceStockFromWarehouse' )
            ->once()
            ->with( 2, 50, 1 );

        $this->checkoutHandler->allocateWarehouseStock( $order );

        $this->assertTrue( true );
    }

    public function testAllocateWarehouseStockAddsNoteWhenNoAllocation(): void
    {
        $item = Mockery::mock( \stdClass::class );
        $item->shouldReceive( 'get_product_id' )->andReturn( 99 );
        $item->shouldReceive( 'get_quantity' )->andReturn( 1 );
        $item->shouldReceive( 'get_name' )->andReturn( 'Orphan Product' );

        $order = Mockery::mock( \WC_Order::class );
        $order->shouldReceive( 'get_shipping_address_1' )->andReturn( 'Addr' );
        $order->shouldReceive( 'get_shipping_address_2' )->andReturn( '' );
        $order->shouldReceive( 'get_shipping_city' )->andReturn( 'City' );
        $order->shouldReceive( 'get_shipping_state' )->andReturn( '' );
        $order->shouldReceive( 'get_shipping_postcode' )->andReturn( '1000' );
        $order->shouldReceive( 'get_shipping_country' )->andReturn( 'BG' );
        $order->shouldReceive( 'get_items' )->andReturn( [ $item ] );
        $order->shouldReceive( 'add_order_note' )
            ->once()
            ->with( Mockery::pattern( '/Warning.*Orphan Product/' ) );
        $order->shouldReceive( 'update_meta_data' )
            ->once()
            ->with( '_wdmw_stock_allocated', '1' );
        $order->shouldReceive( 'save' )->once();

        Functions\expect( '__' )->andReturnFirstArg();

        $this->selectionService
            ->shouldReceive( 'selectWarehousesForCart' )
            ->andReturn( [] ); // No allocations.

        $this->stockService->shouldNotReceive( 'reduceStockFromWarehouse' );

        $this->checkoutHandler->allocateWarehouseStock( $order );

        $this->assertTrue( true );
    }

    // ─── preventDefaultStockReduction ──────────────────────────

    public function testPreventDefaultStockReductionReturnsFalseWhenAllocated(): void
    {
        $order = Mockery::mock( \WC_Order::class );
        $order->shouldReceive( 'get_meta' )
            ->with( '_wdmw_stock_allocated' )
            ->andReturn( '1' );

        $result = $this->checkoutHandler->preventDefaultStockReduction( true, $order );

        $this->assertFalse( $result );
    }

    public function testPreventDefaultStockReductionReturnsOriginalWhenNotAllocated(): void
    {
        $order = Mockery::mock( \WC_Order::class );
        $order->shouldReceive( 'get_meta' )
            ->with( '_wdmw_stock_allocated' )
            ->andReturn( '' );

        $this->assertTrue( $this->checkoutHandler->preventDefaultStockReduction( true, $order ) );
    }

    public function testPreventDefaultStockReductionReturnsOriginalForEmptyOrder(): void
    {
        $order = Mockery::mock( \WC_Order::class );
        $order->shouldReceive( 'get_meta' )
            ->with( '_wdmw_stock_allocated' )
            ->andReturn( '' );

        $this->assertTrue( $this->checkoutHandler->preventDefaultStockReduction( true, $order ) );
    }

    // ─── addExtraShippingFee ─────────────────────────────────

    public function testAddExtraShippingFeeReturnsEarlyWhenDisabled(): void
    {
        Functions\expect( 'get_option' )
            ->with( 'wdmw_extra_shipping_enabled', '0' )
            ->andReturn( '0' );

        // Nothing else should be called.
        $this->checkoutHandler->addExtraShippingFee();

        $this->assertTrue( true );
    }

    public function testAddExtraShippingFeeReturnsEarlyWhenNoShippingAddress(): void
    {
        Functions\expect( 'get_option' )->andReturn( '1' );

        // WC() returns object with null customer.
        $wcMock = Mockery::mock( \stdClass::class );
        $wcMock->customer = null;

        Functions\expect( 'WC' )->andReturn( $wcMock );

        $this->checkoutHandler->addExtraShippingFee();

        $this->assertTrue( true );
    }

    public function testAddExtraShippingFeeAddsFeeWhenCostExists(): void
    {
        Functions\expect( 'get_option' )->andReturn( '1' );
        Functions\expect( '__' )->andReturnFirstArg();

        $customer = Mockery::mock( \stdClass::class );
        $customer->shouldReceive( 'get_shipping_address_1' )->andReturn( 'Street' );
        $customer->shouldReceive( 'get_shipping_city' )->andReturn( 'Sofia' );
        $customer->shouldReceive( 'get_shipping_state' )->andReturn( '' );
        $customer->shouldReceive( 'get_shipping_postcode' )->andReturn( '1000' );
        $customer->shouldReceive( 'get_shipping_country' )->andReturn( 'BG' );

        $cartMock = Mockery::mock( \stdClass::class );
        $cartMock->shouldReceive( 'get_cart' )->andReturn( [
            [ 'product_id' => 10, 'quantity' => 1 ],
        ] );
        $cartMock->shouldReceive( 'add_fee' )
            ->once()
            ->with( 'Additional warehouse shipping', 7.50 );

        $wcMock           = Mockery::mock( \stdClass::class );
        $wcMock->cart     = $cartMock;
        $wcMock->customer = $customer;

        Functions\expect( 'WC' )->andReturn( $wcMock );

        $warehouse = Warehouse::fromDbRow( (object) [
            'id' => '5', 'name' => 'Far WH', 'address' => 'Varna',
            'latitude' => '43.0', 'longitude' => '28.0',
            'is_active' => '1', 'extra_shipping_cost' => '7.50',
        ] );

        $this->selectionService
            ->shouldReceive( 'selectWarehousesForCart' )
            ->andReturn( [
                10 => [
                    [
                        'warehouse'  => $warehouse,
                        'quantity'   => 1,
                        'is_closest' => false,
                        'extra_cost' => 7.50,
                    ],
                ],
            ] );

        $this->checkoutHandler->addExtraShippingFee();

        $this->assertTrue( true );
    }

    public function testAddExtraShippingFeeSkipsFeeWhenAllClosest(): void
    {
        Functions\expect( 'get_option' )->andReturn( '1' );

        $customer = Mockery::mock( \stdClass::class );
        $customer->shouldReceive( 'get_shipping_address_1' )->andReturn( 'Street' );
        $customer->shouldReceive( 'get_shipping_city' )->andReturn( 'Sofia' );
        $customer->shouldReceive( 'get_shipping_state' )->andReturn( '' );
        $customer->shouldReceive( 'get_shipping_postcode' )->andReturn( '1000' );
        $customer->shouldReceive( 'get_shipping_country' )->andReturn( 'BG' );

        $cartMock = Mockery::mock( \stdClass::class );
        $cartMock->shouldReceive( 'get_cart' )->andReturn( [
            [ 'product_id' => 10, 'quantity' => 1 ],
        ] );
        $cartMock->shouldNotReceive( 'add_fee' );

        $wcMock           = Mockery::mock( \stdClass::class );
        $wcMock->cart     = $cartMock;
        $wcMock->customer = $customer;

        Functions\expect( 'WC' )->andReturn( $wcMock );

        $warehouse = Warehouse::fromDbRow( (object) [
            'id' => '1', 'name' => 'Near WH', 'address' => 'Sofia',
            'latitude' => '42.0', 'longitude' => '23.0',
            'is_active' => '1', 'extra_shipping_cost' => '0',
        ] );

        $this->selectionService
            ->shouldReceive( 'selectWarehousesForCart' )
            ->andReturn( [
                10 => [
                    [
                        'warehouse'  => $warehouse,
                        'quantity'   => 1,
                        'is_closest' => true,
                        'extra_cost' => 0.0,
                    ],
                ],
            ] );

        $this->checkoutHandler->addExtraShippingFee();

        $this->assertTrue( true );
    }

    // ─── displayWarehouseNotices ───────────────────────────────

    public function testDisplayWarehouseNoticesBailsWhenCartEmpty(): void
    {
        $wcMock       = Mockery::mock( \stdClass::class );
        $wcMock->cart = Mockery::mock( \stdClass::class );
        $wcMock->cart->shouldReceive( 'is_empty' )->andReturn( true );

        Functions\expect( 'WC' )->andReturn( $wcMock );

        // Nothing else should be called.
        $this->checkoutHandler->displayWarehouseNotices();

        $this->assertTrue( true );
    }

    public function testDisplayWarehouseNoticesBailsWhenFeatureDisabled(): void
    {
        $wcMock       = Mockery::mock( \stdClass::class );
        $wcMock->cart = Mockery::mock( \stdClass::class );
        $wcMock->cart->shouldReceive( 'is_empty' )->andReturn( false );

        Functions\expect( 'WC' )->andReturn( $wcMock );
        Functions\expect( 'get_option' )->andReturn( '0' );

        $this->checkoutHandler->displayWarehouseNotices();

        $this->assertTrue( true );
    }

    public function testDisplayWarehouseNoticesPrintsNoticeForNonClosestProducts(): void
    {
        $wcMock       = Mockery::mock( \stdClass::class );
        $wcMock->cart = Mockery::mock( \stdClass::class );
        $wcMock->cart->shouldReceive( 'is_empty' )->andReturn( false );
        $wcMock->cart->shouldReceive( 'get_cart' )->andReturn( [
            [ 'product_id' => 10, 'quantity' => 1 ],
        ] );

        $customer = Mockery::mock( \stdClass::class );
        $customer->shouldReceive( 'get_shipping_address_1' )->andReturn( 'Street' );
        $customer->shouldReceive( 'get_shipping_city' )->andReturn( 'Sofia' );
        $customer->shouldReceive( 'get_shipping_state' )->andReturn( '' );
        $customer->shouldReceive( 'get_shipping_postcode' )->andReturn( '1000' );
        $customer->shouldReceive( 'get_shipping_country' )->andReturn( 'BG' );

        $wcMock->customer = $customer;

        Functions\expect( 'WC' )->andReturn( $wcMock );
        Functions\expect( 'get_option' )->andReturn( '1' );
        Functions\expect( '__' )->andReturnFirstArg();

        $warehouse = Warehouse::fromDbRow( (object) [
            'id' => '3', 'name' => 'Far WH', 'address' => 'Varna',
            'latitude' => '43.0', 'longitude' => '28.0',
            'is_active' => '1', 'extra_shipping_cost' => '5.00',
        ] );

        $this->selectionService
            ->shouldReceive( 'selectWarehousesForCart' )
            ->andReturn( [
                10 => [
                    [
                        'warehouse'  => $warehouse,
                        'quantity'   => 1,
                        'is_closest' => false,
                        'extra_cost' => 5.0,
                    ],
                ],
            ] );

        $product = Mockery::mock( \stdClass::class );
        $product->shouldReceive( 'get_name' )->andReturn( 'Expensive Widget' );

        Functions\expect( 'wc_get_product' )->with( 10 )->andReturn( $product );

        Functions\expect( 'wc_print_notice' )
            ->once()
            ->with( Mockery::type( 'string' ), 'notice' );

        $this->checkoutHandler->displayWarehouseNotices();

        $this->assertTrue( true );
    }

    public function testDisplayWarehouseNoticesSkipsNoticeWhenAllClosest(): void
    {
        $wcMock       = Mockery::mock( \stdClass::class );
        $wcMock->cart = Mockery::mock( \stdClass::class );
        $wcMock->cart->shouldReceive( 'is_empty' )->andReturn( false );
        $wcMock->cart->shouldReceive( 'get_cart' )->andReturn( [
            [ 'product_id' => 10, 'quantity' => 1 ],
        ] );

        $customer = Mockery::mock( \stdClass::class );
        $customer->shouldReceive( 'get_shipping_address_1' )->andReturn( 'Street' );
        $customer->shouldReceive( 'get_shipping_city' )->andReturn( 'Sofia' );
        $customer->shouldReceive( 'get_shipping_state' )->andReturn( '' );
        $customer->shouldReceive( 'get_shipping_postcode' )->andReturn( '1000' );
        $customer->shouldReceive( 'get_shipping_country' )->andReturn( 'BG' );

        $wcMock->customer = $customer;

        Functions\expect( 'WC' )->andReturn( $wcMock );
        Functions\expect( 'get_option' )->andReturn( '1' );

        $warehouse = Warehouse::fromDbRow( (object) [
            'id' => '1', 'name' => 'Near', 'address' => 'Sofia',
            'latitude' => '42.0', 'longitude' => '23.0',
            'is_active' => '1', 'extra_shipping_cost' => '0',
        ] );

        $this->selectionService
            ->shouldReceive( 'selectWarehousesForCart' )
            ->andReturn( [
                10 => [
                    [
                        'warehouse'  => $warehouse,
                        'quantity'   => 1,
                        'is_closest' => true,
                        'extra_cost' => 0.0,
                    ],
                ],
            ] );

        // wc_print_notice should NOT be called.
        $this->checkoutHandler->displayWarehouseNotices();

        $this->assertTrue( true );
    }

    // ─── maybeAllocateWarehouseStock ───────────────────────────

    public function testMaybeAllocateSkipsWhenOrderNotFound(): void
    {
        Functions\expect( 'wc_get_order' )->with( 999 )->andReturn( false );

        // allocateWarehouseStock should NOT be invoked — no selection/stock calls.
        $this->selectionService->shouldNotReceive( 'selectWarehousesForCart' );
        $this->stockService->shouldNotReceive( 'reduceStockFromWarehouse' );

        $this->checkoutHandler->maybeAllocateWarehouseStock( 999 );

        $this->assertTrue( true );
    }

    public function testMaybeAllocateSkipsWhenAlreadyAllocated(): void
    {
        $order = Mockery::mock( \WC_Order::class );
        $order->shouldReceive( 'get_meta' )
            ->with( '_wdmw_stock_allocated' )
            ->andReturn( '1' );

        Functions\expect( 'wc_get_order' )->with( 42 )->andReturn( $order );

        $this->selectionService->shouldNotReceive( 'selectWarehousesForCart' );
        $this->stockService->shouldNotReceive( 'reduceStockFromWarehouse' );

        $this->checkoutHandler->maybeAllocateWarehouseStock( 42 );

        $this->assertTrue( true );
    }

    public function testMaybeAllocateDelegatesToAllocate(): void
    {
        $warehouse = Warehouse::fromDbRow( (object) [
            'id' => '1', 'name' => 'WH1', 'address' => 'Addr',
            'latitude' => '42.0', 'longitude' => '23.0',
            'is_active' => '1', 'extra_shipping_cost' => '0',
        ] );

        $item = Mockery::mock( \stdClass::class );
        $item->shouldReceive( 'get_product_id' )->andReturn( 10 );
        $item->shouldReceive( 'get_quantity' )->andReturn( 1 );
        $item->shouldReceive( 'get_name' )->andReturn( 'Prod' );
        $item->shouldReceive( 'add_meta_data' )->twice();
        $item->shouldReceive( 'save' )->once();

        $order = Mockery::mock( \WC_Order::class );
        $order->shouldReceive( 'get_meta' )
            ->with( '_wdmw_stock_allocated' )
            ->andReturn( '' );
        $order->shouldReceive( 'get_shipping_address_1' )->andReturn( 'St' );
        $order->shouldReceive( 'get_shipping_address_2' )->andReturn( '' );
        $order->shouldReceive( 'get_shipping_city' )->andReturn( 'City' );
        $order->shouldReceive( 'get_shipping_state' )->andReturn( '' );
        $order->shouldReceive( 'get_shipping_postcode' )->andReturn( '1000' );
        $order->shouldReceive( 'get_shipping_country' )->andReturn( 'BG' );
        $order->shouldReceive( 'get_items' )->andReturn( [ $item ] );
        $order->shouldReceive( 'add_order_note' )->once();
        $order->shouldReceive( 'update_meta_data' )
            ->once()
            ->with( '_wdmw_stock_allocated', '1' );
        $order->shouldReceive( 'save' )->once();

        Functions\expect( 'wc_get_order' )->with( 7 )->andReturn( $order );
        Functions\expect( '__' )->andReturnFirstArg();

        $this->selectionService
            ->shouldReceive( 'selectWarehousesForCart' )
            ->andReturn( [
                10 => [
                    [
                        'warehouse'  => $warehouse,
                        'quantity'   => 1,
                        'is_closest' => true,
                        'extra_cost' => 0.0,
                    ],
                ],
            ] );

        $this->stockService
            ->shouldReceive( 'reduceStockFromWarehouse' )
            ->once()
            ->with( 1, 10, 1 );

        $this->checkoutHandler->maybeAllocateWarehouseStock( 7 );

        $this->assertTrue( true );
    }
}
