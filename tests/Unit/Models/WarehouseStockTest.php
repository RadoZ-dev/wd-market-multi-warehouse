<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use WdMultiWarehouse\Models\WarehouseStock;

class WarehouseStockTest extends TestCase
{
    public function testDefaultConstructorValues(): void
    {
        $stock = new WarehouseStock();

        $this->assertSame( 0, $stock->getId() );
        $this->assertSame( 0, $stock->getWarehouseId() );
        $this->assertSame( 0, $stock->getProductId() );
        $this->assertSame( 0, $stock->getQuantity() );
    }

    public function testFromDbRowMapsFieldsCorrectly(): void
    {
        $row = (object) [
            'id'           => '7',
            'warehouse_id' => '2',
            'product_id'   => '42',
            'quantity'     => '15',
        ];

        $stock = WarehouseStock::fromDbRow( $row );

        $this->assertSame( 7, $stock->getId() );
        $this->assertSame( 2, $stock->getWarehouseId() );
        $this->assertSame( 42, $stock->getProductId() );
        $this->assertSame( 15, $stock->getQuantity() );
    }

    public function testHasStockReturnsTrueWhenPositive(): void
    {
        $stock = new WarehouseStock( 1, 1, 1, 5 );
        $this->assertTrue( $stock->hasStock() );
    }

    public function testHasStockReturnsFalseWhenZero(): void
    {
        $stock = new WarehouseStock( 1, 1, 1, 0 );
        $this->assertFalse( $stock->hasStock() );
    }

    public function testReduceStockDecrementsCorrectly(): void
    {
        $stock = new WarehouseStock( 1, 1, 1, 10 );
        $stock->reduceStock( 3 );
        $this->assertSame( 7, $stock->getQuantity() );
    }

    public function testReduceStockToExactlyZero(): void
    {
        $stock = new WarehouseStock( 1, 1, 1, 5 );
        $stock->reduceStock( 5 );
        $this->assertSame( 0, $stock->getQuantity() );
    }

    public function testReduceStockThrowsWhenExceedsQuantity(): void
    {
        $stock = new WarehouseStock( 1, 2, 42, 3 );

        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'Cannot reduce stock by 5' );

        $stock->reduceStock( 5 );
    }

    public function testReduceStockExceptionContainsDetails(): void
    {
        $stock = new WarehouseStock( 1, 7, 99, 2 );

        try {
            $stock->reduceStock( 10 );
            $this->fail( 'Expected InvalidArgumentException was not thrown' );
        } catch ( \InvalidArgumentException $e ) {
            $this->assertStringContainsString( 'warehouse 7', $e->getMessage() );
            $this->assertStringContainsString( 'product 99', $e->getMessage() );
            $this->assertStringContainsString( 'Only 2 available', $e->getMessage() );
        }
    }

    public function testSettersUpdateValues(): void
    {
        $stock = new WarehouseStock();

        $stock->setWarehouseId( 5 );
        $stock->setProductId( 33 );
        $stock->setQuantity( 100 );

        $this->assertSame( 5, $stock->getWarehouseId() );
        $this->assertSame( 33, $stock->getProductId() );
        $this->assertSame( 100, $stock->getQuantity() );
    }
}
