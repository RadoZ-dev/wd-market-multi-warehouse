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
     * Select the best warehouse for a product based on the customer's shipping address.
     *
     * Returns an associative array with:
     *  - 'warehouse' => Warehouse
     *  - 'is_closest' => bool (whether this is the nearest warehouse)
     *  - 'extra_cost' => float (additional shipping cost if not closest)
     *
     * @return array{warehouse: Warehouse, is_closest: bool, extra_cost: float}|null
     */
    public function selectWarehouse( int $productId, string $shippingAddress ): ?array
    {
        $activeWarehouses = $this->warehouseRepository->findActive();

        if ( empty( $activeWarehouses ) ) {
            return null;
        }

        $customerCoordinates = $this->geocoder->geocode( $shippingAddress );

        if ( $customerCoordinates === null ) {
            return $this->fallbackToFirstAvailable( $productId, $activeWarehouses );
        }

        $sortedWarehouses = $this->sortByDistance(
            $activeWarehouses,
            $customerCoordinates[0],
            $customerCoordinates[1]
        );

        return $this->findFirstWithStock( $productId, $sortedWarehouses );
    }

    /**
     * Select the best warehouse for each product in the cart.
     *
     * @param array<int, int> $cartItems productId => quantity
     * @return array<int, array{warehouse: Warehouse, is_closest: bool, extra_cost: float}>
     */
    public function selectWarehousesForCart( array $cartItems, string $shippingAddress ): array
    {
        $allocations = [];

        foreach ( $cartItems as $productId => $quantity ) {
            $selection = $this->selectWarehouse( $productId, $shippingAddress );

            if ( $selection !== null ) {
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
     * @param array<int, array{warehouse: Warehouse, distance: float}> $sortedWarehouses
     * @return array{warehouse: Warehouse, is_closest: bool, extra_cost: float}|null
     */
    private function findFirstWithStock( int $productId, array $sortedWarehouses ): ?array
    {
        $stockMap = $this->stockRepository->getStockMapForProduct( $productId );

        foreach ( $sortedWarehouses as $index => $entry ) {
            $warehouseId    = $entry['warehouse']->getId();
            $availableStock = $stockMap[ $warehouseId ] ?? 0;

            if ( $availableStock > 0 ) {
                $isClosest = ( $index === 0 );
                $extraCost = 0.0;

                if ( ! $isClosest && $this->isExtraShippingEnabled() ) {
                    $extraCost = $entry['warehouse']->getExtraShippingCost();
                }

                return [
                    'warehouse'  => $entry['warehouse'],
                    'is_closest' => $isClosest,
                    'extra_cost' => $extraCost,
                ];
            }
        }

        return null;
    }

    /**
     * Fallback when geocoding fails: pick the first warehouse that has stock.
     *
     * @param Warehouse[] $warehouses
     * @return array{warehouse: Warehouse, is_closest: bool, extra_cost: float}|null
     */
    private function fallbackToFirstAvailable( int $productId, array $warehouses ): ?array
    {
        $stockMap = $this->stockRepository->getStockMapForProduct( $productId );

        foreach ( $warehouses as $warehouse ) {
            $available = $stockMap[ $warehouse->getId() ] ?? 0;

            if ( $available > 0 ) {
                return [
                    'warehouse'  => $warehouse,
                    'is_closest' => true,
                    'extra_cost' => 0.0,
                ];
            }
        }

        return null;
    }

    private function isExtraShippingEnabled(): bool
    {
        return get_option( 'wdmw_extra_shipping_enabled', '0' ) === '1';
    }
}
