<?php

declare(strict_types=1);

namespace WdMultiWarehouse\Models;

class Warehouse
{
    private int $id;
    private string $name;
    private string $address;
    private ?float $latitude;
    private ?float $longitude;
    private bool $isActive;
    private float $extraShippingCost;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        int $id = 0,
        string $name = '',
        string $address = '',
        ?float $latitude = null,
        ?float $longitude = null,
        bool $isActive = true,
        float $extraShippingCost = 0.00,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id                = $id;
        $this->name              = $name;
        $this->address           = $address;
        $this->latitude          = $latitude;
        $this->longitude         = $longitude;
        $this->isActive          = $isActive;
        $this->extraShippingCost = $extraShippingCost;
        $this->createdAt         = $createdAt;
        $this->updatedAt         = $updatedAt;
    }

    public static function fromDbRow( object $row ): self
    {
        return new self(
            (int) $row->id,
            (string) $row->name,
            (string) $row->address,
            $row->latitude !== null ? (float) $row->latitude : null,
            $row->longitude !== null ? (float) $row->longitude : null,
            (bool) $row->is_active,
            (float) $row->extra_shipping_cost,
            $row->created_at ?? null,
            $row->updated_at ?? null
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName( string $name ): void
    {
        $this->name = $name;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress( string $address ): void
    {
        $this->address = $address;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude( ?float $latitude ): void
    {
        $this->latitude = $latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude( ?float $longitude ): void
    {
        $this->longitude = $longitude;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive( bool $isActive ): void
    {
        $this->isActive = $isActive;
    }

    public function getExtraShippingCost(): float
    {
        return $this->extraShippingCost;
    }

    public function setExtraShippingCost( float $cost ): void
    {
        $this->extraShippingCost = $cost;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'address'             => $this->address,
            'latitude'            => $this->latitude,
            'longitude'           => $this->longitude,
            'is_active'           => $this->isActive,
            'extra_shipping_cost' => $this->extraShippingCost,
        ];
    }
}
