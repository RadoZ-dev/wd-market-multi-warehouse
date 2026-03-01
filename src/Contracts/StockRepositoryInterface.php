<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Contracts;

use WdMultiWarehouse\Models\WarehouseStock;

interface StockRepositoryInterface
{
    public function findByWarehouseAndProduct( int $warehouseId, int $productId ): ?WarehouseStock;

    /** @return WarehouseStock[] */
    public function findByProduct( int $productId ): array;

    /** @return WarehouseStock[] */
    public function findByWarehouse( int $warehouseId ): array;

    public function save( WarehouseStock $stock ): int;

    public function deleteByWarehouse( int $warehouseId ): bool;

    public function getTotalStockForProduct( int $productId ): int;

    /** @return int[] warehouseId => quantity */
    public function getStockMapForProduct( int $productId ): array;

    /** @return int[] product IDs with stock > 0 in the given warehouse */
    public function getProductIdsInWarehouse( int $warehouseId ): array;
}
