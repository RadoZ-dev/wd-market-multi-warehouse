<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Models;

class WarehouseStock
{
    private int $id;
    private int $warehouseId;
    private int $productId;
    private int $quantity;

    public function __construct(
        int $id = 0,
        int $warehouseId = 0,
        int $productId = 0,
        int $quantity = 0
    ) {
        $this->id          = $id;
        $this->warehouseId = $warehouseId;
        $this->productId   = $productId;
        $this->quantity    = $quantity;
    }

    public static function fromDbRow( object $row ): self
    {
        return new self(
            (int) $row->id,
            (int) $row->warehouse_id,
            (int) $row->product_id,
            (int) $row->quantity
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getWarehouseId(): int
    {
        return $this->warehouseId;
    }

    public function setWarehouseId( int $warehouseId ): void
    {
        $this->warehouseId = $warehouseId;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function setProductId( int $productId ): void
    {
        $this->productId = $productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity( int $quantity ): void
    {
        $this->quantity = $quantity;
    }

    public function hasStock(): bool
    {
        return $this->quantity > 0;
    }

    public function reduceStock( int $amount ): void
    {
        if ( $amount > $this->quantity ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cannot reduce stock by %d. Only %d available in warehouse %d for product %d.',
                    $amount,
                    $this->quantity,
                    $this->warehouseId,
                    $this->productId
                )
            );
        }
        $this->quantity -= $amount;
    }
}
