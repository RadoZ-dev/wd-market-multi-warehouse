<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Services;

use WdMultiWarehouse\Contracts\WarehouseRepositoryInterface;
use WdMultiWarehouse\Contracts\StockRepositoryInterface;
use WdMultiWarehouse\Contracts\GeocoderInterface;
use WdMultiWarehouse\Models\Warehouse;

class WarehouseSelectionService
{
    private WarehouseRepositoryInterface $warehouseRepository;
    private StockRepositoryInterface $stockRepository;
    private DistanceService $distanceService;
    private GeocoderInterface $geocoder;

    public function __construct(
        WarehouseRepositoryInterface $warehouseRepository,
        StockRepositoryInterface $stockRepository,
        DistanceService $distanceService,
        GeocoderInterface $geocoder
    ) {
        $this->warehouseRepository = $warehouseRepository;
        $this->stockRepository     = $stockRepository;
        $this->distanceService     = $distanceService;
        $this->geocoder            = $geocoder;
    }

    /**
     * Select warehouses for a product, splitting across multiple when needed.
     *
     * Returns an array of allocations, each with:
     *  - 'warehouse'  => Warehouse
     *  - 'quantity'   => int (how many units to take from this warehouse)
     *  - 'is_closest' => bool (whether this is the nearest warehouse)
     *  - 'extra_cost' => float (additional shipping cost if not closest)
     *
     * @return array<int, array{warehouse: Warehouse, quantity: int, is_closest: bool, extra_cost: float}>
     */
    public function selectWarehouse( int $productId, int $quantity, string $shippingAddress ): array
    {
        $activeWarehouses = $this->warehouseRepository->findActive();

        if ( empty( $activeWarehouses ) ) {
            return [];
        }

        $customerCoordinates = $this->geocoder->geocode( $shippingAddress );

        if ( $customerCoordinates === null ) {
            return $this->fallbackAllocations( $productId, $quantity, $activeWarehouses );
        }

        $sortedWarehouses = $this->sortByDistance(
            $activeWarehouses,
            $customerCoordinates[0],
            $customerCoordinates[1]
        );

        return $this->allocateFromSorted( $productId, $quantity, $sortedWarehouses );
    }

    /**
     * Select warehouses for each product in the cart.
     *
     * @param array<int, int> $cartItems productId => quantity
     * @return array<int, array<int, array{warehouse: Warehouse, quantity: int, is_closest: bool, extra_cost: float}>>
     */
    public function selectWarehousesForCart( array $cartItems, string $shippingAddress ): array
    {
        $allocations = [];

        foreach ( $cartItems as $productId => $quantity ) {
            $selection = $this->selectWarehouse( $productId, $quantity, $shippingAddress );

            if ( ! empty( $selection ) ) {
                $allocations[ $productId ] = $selection;
            }
        }

        return $allocations;
    }

    /**
     * @param Warehouse[] $warehouses
     * @return array<int, array{warehouse: Warehouse, distance: float}>
     */
    private function sortByDistance( array $warehouses, float $customerLat, float $customerLon ): array
    {
        $warehouseDistances = [];

        foreach ( $warehouses as $warehouse ) {
            if ( ! $warehouse->hasCoordinates() ) {
                continue;
            }

            $distance = $this->distanceService->calculateDistance(
                $customerLat,
                $customerLon,
                $warehouse->getLatitude(),
                $warehouse->getLongitude()
            );

            $warehouseDistances[] = [
                'warehouse' => $warehouse,
                'distance'  => $distance,
            ];
        }

        usort(
            $warehouseDistances,
            static function ( array $a, array $b ): int {
                return $a['distance'] <=> $b['distance'];
            }
        );

        return $warehouseDistances;
    }

    /**
     * Allocate stock from distance-sorted warehouses, splitting when necessary.
     *
     * @param array<int, array{warehouse: Warehouse, distance: float}> $sortedWarehouses
     * @return array<int, array{warehouse: Warehouse, quantity: int, is_closest: bool, extra_cost: float}>
     */
    private function allocateFromSorted( int $productId, int $quantity, array $sortedWarehouses ): array
    {
        $stockMap    = $this->stockRepository->getStockMapForProduct( $productId );
        $allocations = [];
        $remaining   = $quantity;

        foreach ( $sortedWarehouses as $index => $entry ) {
            $warehouseId    = $entry['warehouse']->getId();
            $availableStock = $stockMap[ $warehouseId ] ?? 0;

            if ( $availableStock <= 0 ) {
                continue;
            }

            $allocateQty = min( $availableStock, $remaining );
            $isClosest   = ( $index === 0 );
            $extraCost   = 0.0;

            if ( ! $isClosest && $this->isExtraShippingEnabled() ) {
                $extraCost = $entry['warehouse']->getExtraShippingCost();
            }

            $allocations[] = [
                'warehouse'  => $entry['warehouse'],
                'quantity'   => $allocateQty,
                'is_closest' => $isClosest,
                'extra_cost' => $extraCost,
            ];

            $remaining -= $allocateQty;

            if ( $remaining <= 0 ) {
                break;
            }
        }

        return $allocations;
    }

    /**
     * Fallback when geocoding fails: allocate from warehouses in DB order.
     *
     * @param Warehouse[] $warehouses
     * @return array<int, array{warehouse: Warehouse, quantity: int, is_closest: bool, extra_cost: float}>
     */
    private function fallbackAllocations( int $productId, int $quantity, array $warehouses ): array
    {
        $stockMap    = $this->stockRepository->getStockMapForProduct( $productId );
        $allocations = [];
        $remaining   = $quantity;
        $isFirst     = true;

        foreach ( $warehouses as $warehouse ) {
            $available = $stockMap[ $warehouse->getId() ] ?? 0;

            if ( $available <= 0 ) {
                continue;
            }

            $allocateQty = min( $available, $remaining );
            $extraCost   = 0.0;

            if ( ! $isFirst && $this->isExtraShippingEnabled() ) {
                $extraCost = $warehouse->getExtraShippingCost();
            }

            $allocations[] = [
                'warehouse'  => $warehouse,
                'quantity'   => $allocateQty,
                'is_closest' => $isFirst,
                'extra_cost' => $extraCost,
            ];

            $isFirst    = false;
            $remaining -= $allocateQty;

            if ( $remaining <= 0 ) {
                break;
            }
        }

        return $allocations;
    }

    private function isExtraShippingEnabled(): bool
    {
        return get_option( 'wdmw_extra_shipping_enabled', '0' ) === '1';
    }
}
