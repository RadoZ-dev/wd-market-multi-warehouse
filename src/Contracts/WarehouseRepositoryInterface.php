<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Contracts;

use WdMultiWarehouse\Models\Warehouse;

interface WarehouseRepositoryInterface
{
    public function findById( int $id ): ?Warehouse;

    /** @return Warehouse[] */
    public function findAll(): array;

    /** @return Warehouse[] */
    public function findActive(): array;

    public function save( Warehouse $warehouse ): int;
    public function delete( int $id ): bool;
}
