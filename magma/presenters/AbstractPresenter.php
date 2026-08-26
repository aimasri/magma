<?php

declare(strict_types=1);

namespace Magma\presenters;

use JsonSerializable;
use ArrayAccess;

/**
 * Title: Abstract Entity Presenter
 *
 * Purpose:
 * - Serves as the base Presenter (Decorator Pattern) wrapping Domain Entities and DTOs.
 * - Eliminates inline presentation logic, date formatting, currency calculations, and conditional
 *   styling from HTML view templates.
 *
 * Why / Why this design:
 * - Decorator & Presenter Pattern: Wraps the raw entity and transparently delegates calls to it
 *   while providing display-specific getters and formatting helpers.
 * - Single Responsibility Principle (SRP): Keeps Domain Entities focused purely on business rules
 *   and data integrity without polluting them with UI-specific formatting concerns (e.g. `$19.99`, `Yes/No` badges).
 * - Separation of Concerns: Views become purely declarative and free of complex ternary expressions.
 *
 * Teaching notes:
 * - Presenter methods take precedence over raw entity properties. When accessing `$presenter->price`,
 *   if `getPrice()` exists in the presenter it will be called; otherwise it delegates to the entity.
 */
/**
 * @implements ArrayAccess<string, mixed>
 */
abstract class AbstractPresenter implements JsonSerializable, ArrayAccess
{
    /** @var object|array<string, mixed> The wrapped domain entity or data structure. */
    protected object|array $entity;

    /**
     * Initializes the Presenter with an entity or DTO.
     *
     * @param object|array<string, mixed> $entity The domain entity or data dictionary.
     */
    public function __construct(object|array $entity)
    {
        $this->entity = $entity;
    }

    /**
     * Retrieves the raw underlying entity.
     *
     * @return object|array<string, mixed>
     */
    public function getEntity(): object|array
    {
        return $this->entity;
    }

    /**
     * Formats an integer/float amount (in cents or standard units) into a formatted currency string.
     *
     * @param int|float|null $amount Value in cents or standard decimal.
     * @param bool $isCents True if amount is represented in cents (e.g., 1999 -> $19.99).
     * @param string $symbol Currency symbol (default '$').
     * @return string Formatted currency string.
     */
    public function formatCurrency(int|float|null $amount, bool $isCents = true, string $symbol = '$'): string
    {
        if ($amount === null) {
            return $symbol . '0.00';
        }

        $numericVal = $isCents ? ((float) $amount / 100.0) : (float) $amount;
        return $symbol . number_format($numericVal, 2, '.', ',');
    }

    /**
     * Formats a date string or timestamp into a localized human-readable format.
     *
     * @param string|int|\DateTimeInterface|null $date Date string, timestamp, or DateTime object.
     * @param string $format Target date format (defaults to 'M d, Y H:i').
     * @return string Formatted date string or 'N/A' if null/invalid.
     */
    public function formatDate(string|int|\DateTimeInterface|null $date, string $format = 'M d, Y H:i'): string
    {
        if ($date === null || $date === '') {
            return 'N/A';
        }

        try {
            if ($date instanceof \DateTimeInterface) {
                return $date->format($format);
            }

            if (is_numeric($date)) {
                $dt = new \DateTimeImmutable('@' . $date);
                return $dt->format($format);
            }

            $dt = new \DateTimeImmutable((string) $date);
            return $dt->format($format);
        } catch (\Throwable) {
            return 'Invalid Date';
        }
    }

    /**
     * Formats a boolean flag into user-friendly text.
     *
     * @param bool|int|null $value
     * @param string $trueLabel Text when true.
     * @param string $falseLabel Text when false.
     * @return string
     */
    public function formatBoolean(bool|int|null $value, string $trueLabel = 'Yes', string $falseLabel = 'No'): string
    {
        return !empty($value) ? $trueLabel : $falseLabel;
    }

    /**
     * Truncates a string to a specified length with ellipsis.
     *
     * @param string|null $text Unformatted text.
     * @param int $limit Maximum length.
     * @param string $end Trailing ellipsis string.
     * @return string
     */
    public function truncate(?string $text, int $limit = 100, string $end = '...'): string
    {
        if ($text === null || mb_strlen($text) <= $limit) {
            return $text ?? '';
        }

        return mb_substr($text, 0, $limit) . $end;
    }

    /**
     * Escapes unsafe characters for HTML output.
     *
     * @param string|null $value
     * @return string
     */
    public function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Magic property getter:
     * 1. Checks if presenter has a dedicated getter method (e.g., `formattedPrice()` or `getFormattedPrice()`).
     * 2. Delegates to entity getter or property if not defined on the presenter.
     *
     * @param string $name Property name.
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        $getter = 'get' . str_replace('_', '', ucwords($name, '_'));
        if (method_exists($this, $getter)) {
            return $this->{$getter}();
        }

        $camel = lcfirst(str_replace('_', '', ucwords($name, '_')));
        if (method_exists($this, $camel)) {
            return $this->{$camel}();
        }

        if (is_object($this->entity)) {
            if (method_exists($this->entity, $getter)) {
                return $this->entity->{$getter}();
            }
            if (isset($this->entity->{$name})) {
                return $this->entity->{$name};
            }
        } elseif (is_array($this->entity) && array_key_exists($name, $this->entity)) {
            return $this->entity[$name];
        }

        return null;
    }

    /**
     * Magic method caller delegating unhandled method calls to the underlying entity.
     *
     * @param string $name Method name.
     * @param array<int, mixed> $arguments Method arguments.
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (is_object($this->entity) && method_exists($this->entity, $name)) {
            return $this->entity->{$name}(...$arguments);
        }

        throw new \BadMethodCallException("Method [{$name}] does not exist on presenter or wrapped entity.");
    }

    /**
     * Magic isset check.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        $getter = 'get' . str_replace('_', '', ucwords($name, '_'));
        if (method_exists($this, $getter)) {
            return true;
        }

        if (is_object($this->entity)) {
            return isset($this->entity->{$name}) || method_exists($this->entity, $getter);
        }

        return isset($this->entity[$name]);
    }

    /**
     * Exports presenter data to an array for view consumption.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Alias for `toArray()` to support json_encode serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * ArrayAccess: offsetExists
     * @param mixed $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        $offsetStr = is_scalar($offset) ? (string) $offset : '';
        return $this->__isset($offsetStr);
    }

    /**
     * ArrayAccess: offsetGet
     * @param mixed $offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        $offsetStr = is_scalar($offset) ? (string) $offset : '';
        return $this->__get($offsetStr);
    }

    /**
     * ArrayAccess: offsetSet (Immutable Presenter)
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Presenters are immutable. Cannot mutate presenter offsets directly.');
    }

    /**
     * ArrayAccess: offsetUnset (Immutable Presenter)
     * @param mixed $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Presenters are immutable. Cannot unset presenter offsets directly.');
    }
}
