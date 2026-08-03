<?php

namespace Magma\modules\Inventory\domain;

class RecordInventoryMovementCommand
{
    public function __construct(
        public readonly int $vendorId,
        public readonly int $productId,
        public readonly float $quantity,
        public readonly string $transactionType,
        public readonly ?int $supplierId = null,
        public readonly ?float $unitPrice = null
    ) {}
}
