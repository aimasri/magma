<?php

declare(strict_types=1);

namespace Magma\domain;

use Magma\domain\exceptions\InvalidStateTransitionException;

/**
 * Title: Universal Finite State Machine Engine
 *
 * Purpose:
 * - Manages entity lifecycle state transitions, enforcing allowed transition graphs,
 *   case-insensitive state normalization, and terminal state invariants.
 *
 * Why / Why this design:
 * - Finite State Machine (FSM) Pattern: Restricts domain entities (Orders, Invoices, Work Orders,
 *   Appointments) to strictly validated lifecycle graphs, preventing illegal state corruption.
 * - Case-Insensitive Normalization: Eliminates mismatch bugs between PostgreSQL enum strings
 *   and PHP runtime strings by normalizing all state identifiers to uppercase.
 * - Terminal State Invariants: Protects terminal entities (e.g. CANCELLED, REFUNDED, COMPLETED)
 *   from receiving downstream mutations.
 *
 * Teaching notes:
 * - A state is considered "terminal" if it is explicitly listed in `$terminalStates` or has no
 *   outbound transition paths in `$transitionGraph`.
 */
class StateMachine
{
    /** @var array<string, string[]> Transition graph: [FROM_STATE => [TO_STATE_1, TO_STATE_2]] */
    private array $transitionGraph = [];

    /** @var array<string, bool> Set of terminal states */
    private array $terminalStates = [];

    private ?string $initialState;

    /**
     * @param array<string, string[]> $transitionGraph Map of source states to arrays of allowed destination states.
     * @param string[] $terminalStates List of terminal states where no further transitions are permitted.
     * @param string|null $initialState The default initial state for new entities.
     */
    public function __construct(
        array $transitionGraph = [],
        array $terminalStates = [],
        ?string $initialState = null
    ) {
        foreach ($transitionGraph as $from => $toStates) {
            $normalizedFrom = strtoupper(trim((string)$from));
            $this->transitionGraph[$normalizedFrom] = array_values(array_unique(
                array_map(fn(string $to) => strtoupper(trim($to)), $toStates)
            ));
        }

        foreach ($terminalStates as $terminal) {
            $this->terminalStates[strtoupper(trim($terminal))] = true;
        }

        $this->initialState = $initialState !== null ? strtoupper(trim($initialState)) : null;
    }

    /**
     * Returns the configured initial state.
     *
     * @return string|null
     */
    public function getInitialState(): ?string
    {
        return $this->initialState;
    }

    /**
     * Checks if a transition from `$fromState` to `$toState` is permitted.
     *
     * Execution Flow:
     * 1. Normalize both state strings to uppercase.
     * 2. If source and destination are identical, return true (identity transition/no-op).
     * 3. If source is a terminal state, return false.
     * 4. Check if destination is in the allowed transition array for source.
     *
     * Logic behind the logic:
     * - Case normalization ensures that `Pending` vs `PENDING` vs `pending` evaluate identically.
     *
     * @param string $fromState
     * @param string $toState
     * @return bool
     */
    public function canTransition(string $fromState, string $toState): bool
    {
        $normalizedFrom = strtoupper(trim($fromState));
        $normalizedTo = strtoupper(trim($toState));

        if ($normalizedFrom === $normalizedTo) {
            return true;
        }

        if ($this->isTerminal($normalizedFrom)) {
            return false;
        }

        if (!isset($this->transitionGraph[$normalizedFrom])) {
            return false;
        }

        return in_array($normalizedTo, $this->transitionGraph[$normalizedFrom], true);
    }

    /**
     * Validates and executes a state transition, returning the normalized destination state.
     *
     * Execution Flow:
     * 1. Normalize state strings to uppercase.
     * 2. Check if source state is marked terminal; throw InvalidStateTransitionException if so.
     * 3. Check if target state is registered in allowed transitions; throw InvalidStateTransitionException if not.
     * 4. Return normalized destination state.
     *
     * Logic behind the logic:
     * - Explicit validation and domain exceptions ensure erroneous controller input is trapped
     *   before database update queries are constructed.
     *
     * @param string $fromState Originating state.
     * @param string $toState Requested destination state.
     * @param array<string, mixed> $context Optional metadata for audit logging.
     * @return string Normalized destination state.
     * @throws InvalidStateTransitionException If transition is forbidden.
     */
    public function transition(string $fromState, string $toState, array $context = []): string
    {
        $normalizedFrom = strtoupper(trim($fromState));
        $normalizedTo = strtoupper(trim($toState));

        $this->validateTransition($normalizedFrom, $normalizedTo);

        return $normalizedTo;
    }

    /**
     * Validates that a transition is authorized, throwing an exception if invalid.
     *
     * @param string $fromState
     * @param string $toState
     * @throws InvalidStateTransitionException
     */
    public function validateTransition(string $fromState, string $toState): void
    {
        $normalizedFrom = strtoupper(trim($fromState));
        $normalizedTo = strtoupper(trim($toState));

        if ($this->isTerminal($normalizedFrom)) {
            throw new InvalidStateTransitionException(
                $normalizedFrom,
                $normalizedTo,
                "State [{$normalizedFrom}] is terminal. No outbound transitions are permitted."
            );
        }

        if (!$this->canTransition($normalizedFrom, $normalizedTo)) {
            $allowed = implode(', ', $this->getAllowedTransitions($normalizedFrom));
            $allowedMsg = !empty($allowed) ? "Allowed transitions: [{$allowed}]." : "No outbound transitions configured.";
            throw new InvalidStateTransitionException(
                $normalizedFrom,
                $normalizedTo,
                "Cannot transition from [{$normalizedFrom}] to [{$normalizedTo}]. {$allowedMsg}"
            );
        }
    }

    /**
     * Checks if a given state is terminal.
     *
     * @param string $state
     * @return bool
     */
    public function isTerminal(string $state): bool
    {
        $normalized = strtoupper(trim($state));

        if (isset($this->terminalStates[$normalized])) {
            return true;
        }

        // If defined in graph with empty transitions, treat as terminal
        if (isset($this->transitionGraph[$normalized]) && empty($this->transitionGraph[$normalized])) {
            return true;
        }

        return false;
    }

    /**
     * Returns an array of all allowed destination states from a given current state.
     *
     * @param string $currentState
     * @return string[]
     */
    public function getAllowedTransitions(string $currentState): array
    {
        $normalized = strtoupper(trim($currentState));

        if ($this->isTerminal($normalized)) {
            return [];
        }

        return $this->transitionGraph[$normalized] ?? [];
    }

    /**
     * Returns all unique state names known to this state machine.
     *
     * @return string[]
     */
    public function getStates(): array
    {
        $states = array_keys($this->transitionGraph);

        foreach ($this->transitionGraph as $targets) {
            foreach ($targets as $target) {
                $states[] = $target;
            }
        }

        foreach (array_keys($this->terminalStates) as $terminal) {
            $states[] = $terminal;
        }

        if ($this->initialState !== null) {
            $states[] = $this->initialState;
        }

        return array_values(array_unique($states));
    }
}
