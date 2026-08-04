<?php

namespace Magma\modules\Inventory\domain;

/**
 * Inventory Movement Domain Entity
 *
 * Purpose:
 * - Encapsulate the data representing a movement of inventory.
 *
 * Why / Why this design:
 * - Keeps the InventoryService as a thin orchestrator by centralizing the parameter
 *   validation and assignment for inventory movements.
 *
 * Teaching notes:
 * - Can be expanded with validation rules, e.g., negative quantity checks or 
 *   requiring a supplier for restock transactions.
 */
class InventoryMovement
{
    private int $vendorId;
    private int $productId;
    private float $quantity;
    private string $transactionType;
    private ?int $supplierId;
    private ?float $unitPrice;

    /**
     * Constructs a new InventoryMovement entity.
     *
     * Execution Flow:
     * 1. Assigns vendor, product, and quantity data.
     * 2. Sets the transaction type (e.g., restock, sale).
     * 3. Assigns optional fields like supplier and unit price.
     *
     * Logic behind the logic:
     * - Bundling these 6 parameters into an entity prevents "primitive obsession" 
     *   and ensures that the service and repository layer have a guaranteed contract 
     *   for what constitutes a valid inventory transaction.
     *
     * @param int $vendorId The vendor ID.
     * @param int $productId The product ID.
     * @param float $quantity The amount moved (positive or negative).
     * @param string $transactionType The classification.
     * @param int|null $supplierId Optional supplier ID.
     * @param float|null $unitPrice Optional unit price.
     */
    public function __construct(
        int $vendorId,
        int $productId,
        float $quantity,
        string $transactionType,
        ?int $supplierId = null,
        ?float $unitPrice = null
    ) {
        if ($quantity === 0.0) {
            throw new \InvalidArgumentException("Quantity cannot be zero.");
        }
        
        $validTypes = ['restock', 'sale', 'adjustment', 'return'];
        if (!in_array($transactionType, $validTypes, true)) {
            throw new \InvalidArgumentException("Invalid transaction type.");
        }

        if ($transactionType === 'restock' && $quantity < 0) {
            throw new \InvalidArgumentException("Restock quantity must be positive.");
        }
        
        if ($transactionType === 'sale' && $quantity > 0) {
            throw new \InvalidArgumentException("Sale quantity must be negative.");
        }

        $this->vendorId = $vendorId;
        $this->productId = $productId;
        $this->quantity = $quantity;
        $this->transactionType = $transactionType;
        $this->supplierId = $supplierId;
        $this->unitPrice = $unitPrice;
    }

    /**
     * Retrieves the vendor ID.
     *
     * @return int
     */
    public function getVendorId(): int
    {
        return $this->vendorId;
    }

    /**
     * Retrieves the product ID.
     *
     * @return int
     */
    public function getProductId(): int
    {
        return $this->productId;
    }

    /**
     * Retrieves the delta quantity.
     *
     * @return float
     */
    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * Retrieves the transaction type.
     *
     * @return string
     */
    public function getTransactionType(): string
    {
        return $this->transactionType;
    }

    /**
     * Retrieves the optional supplier ID.
     *
     * @return int|null
     */
    public function getSupplierId(): ?int
    {
        return $this->supplierId;
    }

    /**
     * Retrieves the optional unit price.
     *
     * @return float|null
     */
    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }
}
