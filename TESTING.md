# Testing & Debugging Strategy

## Overview

This project uses two types of tests:

| Type | Tool | What it tests | Needs WordPress? |
|------|------|---------------|-----------------|
| **Unit Tests** | PHPUnit 9.x | Models, Services, pure logic | No |
| **Integration Tests** | PHPUnit + WP Test Suite | Repositories, Hooks, Admin pages | Yes (test DB) |

---

## 1. Directory Structure

```
tests/
├── Unit/
│   ├── Models/
│   │   ├── WarehouseTest.php
│   │   └── WarehouseStockTest.php
│   └── Services/
│       ├── DistanceServiceTest.php
│       ├── StockServiceTest.php
│       └── WarehouseSelectionServiceTest.php
├── Integration/
│   ├── Repositories/
│   │   ├── WarehouseRepositoryTest.php
│   │   └── StockRepositoryTest.php
│   ├── Admin/
│   │   └── AdminMenuTest.php
│   └── Frontend/
│       ├── CheckoutHandlerTest.php
│       └── CatalogFilterTest.php
├── bootstrap.php
└── bootstrap-integration.php
```

---

## 2. What to Test per Step

### Step 2 — Contracts & Models (current)

**Unit tests only** — models are pure PHP, no WordPress dependency.

#### `WarehouseTest.php`
- Constructor sets default values correctly
- `fromDbRow()` maps snake_case DB fields to camelCase properties
- `hasCoordinates()` returns `true` when both lat/lng are set
- `hasCoordinates()` returns `false` when latitude is null
- `hasCoordinates()` returns `false` when longitude is null
- `toArray()` returns correct snake_case keys
- `setName()` / `getName()` roundtrip
- `isActive()` returns correct boolean

#### `WarehouseStockTest.php`
- Constructor sets default values correctly
- `fromDbRow()` maps DB fields with correct types
- `hasStock()` returns `true` when quantity > 0
- `hasStock()` returns `false` when quantity is 0
- `reduceStock()` decrements quantity correctly
- `reduceStock()` throws `InvalidArgumentException` when amount exceeds quantity
- `reduceStock()` allows reducing to exactly 0
- Exception message includes warehouse ID, product ID, and quantities

### Step 3 — Repositories

**Integration tests** — needs `$wpdb` and real database tables.

#### `WarehouseRepositoryTest.php`
- `save()` inserts a new warehouse and returns ID > 0
- `save()` updates an existing warehouse
- `findById()` returns Warehouse object with correct data
- `findById()` returns null for non-existent ID
- `findAll()` returns all warehouses
- `findActive()` returns only warehouses where is_active = 1
- `delete()` removes warehouse and returns true
- `delete()` returns false for non-existent ID

#### `StockRepositoryTest.php`
- `save()` inserts new stock record
- `save()` updates existing stock (upsert on warehouse_id + product_id)
- `findByWarehouseAndProduct()` returns correct WarehouseStock
- `findByProduct()` returns all warehouse stocks for a product
- `getTotalStockForProduct()` sums quantities across warehouses
- `getStockMapForProduct()` returns [warehouseId => quantity] map
- `deleteByWarehouse()` removes all stock entries for a warehouse

### Step 4 — Geocoding & Template Services

#### `DistanceServiceTest.php` (Unit)
- Haversine: distance between same point is 0
- Haversine: known distance between Belgrade and Novi Sad (~80km)
- Haversine: known distance between two distant cities
- Result is always positive (absolute distance)

#### `GeocodingServiceTest.php` (Integration — calls external API)
- Skip in CI (requires network)
- Test that transient caching works (mock `wp_remote_get`)

### Step 5 — Stock & Selection Services

#### `StockServiceTest.php` (Unit with mocks)
- `getTotalStock()` delegates to repository
- `reduceStockFromWarehouse()` calls `reduceStock()` on model then saves
- WooCommerce filter returns correct total stock

#### `WarehouseSelectionServiceTest.php` (Unit with mocks)
- Selects nearest warehouse with stock
- Skips warehouses without stock
- Skips warehouses without coordinates
- Returns null when no warehouse has stock
- Includes extra shipping cost from selected warehouse

### Steps 6-8 — Admin & Frontend

#### Integration tests for:
- Admin menu page renders without errors
- Product stock tab saves warehouse quantities
- Checkout handler reduces stock from correct warehouse
- Catalog filter modifies WP_Query correctly

---

## 3. Setup Instructions

### Install PHPUnit

```bash
composer require --dev phpunit/phpunit:"^9.6" brain/monkey:"^2.6" mockery/mockery:"^1.6"
```

| Package | Purpose |
|---------|---------|
| `phpunit/phpunit` | Test runner |
| `brain/monkey` | Mocks WordPress functions (`add_action`, `get_option`, etc.) without loading WP |
| `mockery/mockery` | Object mocking for dependency injection |

### PHPUnit Config

Create `phpunit.xml.dist`:

```xml
<?xml version="1.0"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    colors="true"
    testdox="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### Unit Test Bootstrap (`tests/bootstrap.php`)

```php
<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Brain\Monkey setup for mocking WP functions
\Brain\Monkey\setUp();
```

### Composer Scripts

```json
"scripts": {
    "test": "phpunit --testsuite=Unit",
    "test:all": "phpunit",
    "test:coverage": "phpunit --testsuite=Unit --coverage-html=coverage"
}
```

---

## 4. Example Test: WarehouseStockTest

```php
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

    public function testReduceStockToZero(): void
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
}
```

---

## 5. Example Test: WarehouseTest

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use WdMultiWarehouse\Models\Warehouse;

class WarehouseTest extends TestCase
{
    public function testFromDbRowMapsCorrectly(): void
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
        $this->assertSame( 44.8178, $warehouse->getLatitude() );
        $this->assertTrue( $warehouse->isActive() );
        $this->assertSame( 5.50, $warehouse->getExtraShippingCost() );
    }

    public function testHasCoordinatesWithBothSet(): void
    {
        $warehouse = new Warehouse( 1, 'Test', 'Addr', 44.0, 20.0 );
        $this->assertTrue( $warehouse->hasCoordinates() );
    }

    public function testHasCoordinatesWithoutLatitude(): void
    {
        $warehouse = new Warehouse( 1, 'Test', 'Addr', null, 20.0 );
        $this->assertFalse( $warehouse->hasCoordinates() );
    }

    public function testToArrayReturnsSnakeCaseKeys(): void
    {
        $warehouse = new Warehouse( 1, 'Test', 'Addr', 44.0, 20.0, true, 3.00 );
        $array = $warehouse->toArray();

        $this->assertArrayHasKey( 'is_active', $array );
        $this->assertArrayHasKey( 'extra_shipping_cost', $array );
        $this->assertArrayNotHasKey( 'isActive', $array );
    }
}
```

---

## 6. Debugging Strategy

### During Development

| Issue | Tool | How |
|-------|------|-----|
| PHP errors | WP Debug | Set `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY` in `wp-config.php` |
| SQL issues | Query Monitor plugin | Shows all queries, slow queries, errors |
| Hook firing order | Query Monitor | Shows all hooks with callbacks and priority |
| Twig template errors | Twig debug mode | Set `'debug' => true` in TemplateRenderer |
| PHPCS violations | `composer lint` | Run before every commit |
| Type errors | `declare(strict_types=1)` | Already in every file — catches type mismatches at runtime |

### wp-config.php Settings

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );       // Logs to wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false );  // Don't show errors on screen
define( 'SCRIPT_DEBUG', true );       // Use unminified JS/CSS
```

### Manual Testing Checklist

For each step, verify in the browser:

- [ ] Plugin activates without errors
- [ ] Plugin deactivates without errors
- [ ] No PHP notices/warnings in `debug.log`
- [ ] Admin pages render correctly
- [ ] Data saves and loads from database
- [ ] WooCommerce stock displays correct totals

---

## 7. Test Execution Priority

Run tests in this order for fastest feedback:

1. `composer lint` — catch style issues (instant)
2. `composer test` — unit tests (< 1 second)
3. Manual browser check — visual verification
4. `composer test:all` — integration tests (requires WP test DB)
