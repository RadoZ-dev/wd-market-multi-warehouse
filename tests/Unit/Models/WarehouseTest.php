<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use WdMultiWarehouse\Models\Warehouse;

class WarehouseTest extends TestCase
{
    public function testDefaultConstructorValues(): void
    {
        $warehouse = new Warehouse();

        $this->assertSame( 0, $warehouse->getId() );
        $this->assertSame( '', $warehouse->getName() );
        $this->assertSame( '', $warehouse->getAddress() );
        $this->assertNull( $warehouse->getLatitude() );
        $this->assertNull( $warehouse->getLongitude() );
        $this->assertTrue( $warehouse->isActive() );
        $this->assertSame( 0.00, $warehouse->getExtraShippingCost() );
    }

    public function testFromDbRowMapsFieldsCorrectly(): void
    {
        $row = (object) [
            'id'                  => '1',
            'name'                => 'Belgrade HQ',
            'address'             => 'Knez Mihailova 5',
            'latitude'            => '44.817800',
            'longitude'           => '20.457700',
            'is_active'           => '1',
            'extra_shipping_cost' => '5.50',
            'created_at'          => '2026-01-01 00:00:00',
            'updated_at'          => '2026-01-01 00:00:00',
        ];

        $warehouse = Warehouse::fromDbRow( $row );

        $this->assertSame( 1, $warehouse->getId() );
        $this->assertSame( 'Belgrade HQ', $warehouse->getName() );
        $this->assertSame( 'Knez Mihailova 5', $warehouse->getAddress() );
        $this->assertSame( 44.8178, $warehouse->getLatitude() );
        $this->assertSame( 20.4577, $warehouse->getLongitude() );
        $this->assertTrue( $warehouse->isActive() );
        $this->assertSame( 5.50, $warehouse->getExtraShippingCost() );
        $this->assertSame( '2026-01-01 00:00:00', $warehouse->getCreatedAt() );
    }

    public function testFromDbRowHandlesNullCoordinates(): void
    {
        $row = (object) [
            'id'                  => '2',
            'name'                => 'No GPS',
            'address'             => 'Unknown Street',
            'latitude'            => null,
            'longitude'           => null,
            'is_active'           => '0',
            'extra_shipping_cost' => '0.00',
            'created_at'          => null,
            'updated_at'          => null,
        ];

        $warehouse = Warehouse::fromDbRow( $row );

        $this->assertNull( $warehouse->getLatitude() );
        $this->assertNull( $warehouse->getLongitude() );
        $this->assertFalse( $warehouse->isActive() );
    }

    public function testHasCoordinatesReturnsTrueWhenBothSet(): void
    {
        $warehouse = new Warehouse( 1, 'Test', 'Addr', 44.0, 20.0 );
        $this->assertTrue( $warehouse->hasCoordinates() );
    }

    public function testHasCoordinatesReturnsFalseWhenLatitudeNull(): void
    {
        $warehouse = new Warehouse( 1, 'Test', 'Addr', null, 20.0 );
        $this->assertFalse( $warehouse->hasCoordinates() );
    }

    public function testHasCoordinatesReturnsFalseWhenLongitudeNull(): void
    {
        $warehouse = new Warehouse( 1, 'Test', 'Addr', 44.0, null );
        $this->assertFalse( $warehouse->hasCoordinates() );
    }

    public function testToArrayReturnsSnakeCaseKeys(): void
    {
        $warehouse = new Warehouse( 1, 'Test', 'Addr', 44.0, 20.0, true, 3.00 );
        $array     = $warehouse->toArray();

        $this->assertArrayHasKey( 'id', $array );
        $this->assertArrayHasKey( 'name', $array );
        $this->assertArrayHasKey( 'is_active', $array );
        $this->assertArrayHasKey( 'extra_shipping_cost', $array );
        $this->assertArrayNotHasKey( 'isActive', $array );
        $this->assertArrayNotHasKey( 'extraShippingCost', $array );
    }

    public function testSettersUpdateValues(): void
    {
        $warehouse = new Warehouse();

        $warehouse->setName( 'Updated' );
        $warehouse->setAddress( 'New Address' );
        $warehouse->setLatitude( 45.0 );
        $warehouse->setLongitude( 21.0 );
        $warehouse->setIsActive( false );
        $warehouse->setExtraShippingCost( 10.50 );

        $this->assertSame( 'Updated', $warehouse->getName() );
        $this->assertSame( 'New Address', $warehouse->getAddress() );
        $this->assertSame( 45.0, $warehouse->getLatitude() );
        $this->assertSame( 21.0, $warehouse->getLongitude() );
        $this->assertFalse( $warehouse->isActive() );
        $this->assertSame( 10.50, $warehouse->getExtraShippingCost() );
    }
}
