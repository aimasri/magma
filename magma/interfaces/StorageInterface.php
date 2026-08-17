<?php

declare(strict_types=1);

namespace Magma\interfaces;

use Magma\infrastructure\storage\StorageInterface as BaseStorageInterface;

/**
 * Title: Storage Interface (Legacy Compatibility Contract)
 *
 * Purpose:
 * - Provides backward compatibility for components expecting `Magma\interfaces\StorageInterface`.
 * - Extends the enterprise `Magma\infrastructure\storage\StorageInterface`.
 *
 * Why / Why this design:
 * - Preserves Liskov Substitution Principle (LSP) across legacy and modern module boundaries.
 *
 * Teaching notes:
 * - For new development, type-hint `Magma\infrastructure\storage\StorageInterface`.
 */
interface StorageInterface extends BaseStorageInterface
{
}
