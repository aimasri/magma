/**
 * Title: Lightweight ES6 Client Event Bus
 *
 * Purpose:
 * - Provides a decoupled Pub/Sub (Observer) micro-library for cross-module communication.
 * - Prevents JavaScript components from polluting the global `window.*` namespace or creating tight couplings.
 * - Supports `AbortSignal` lifecycle teardown and one-time `once()` event listeners.
 *
 * Why / Why this design:
 * - Decoupled Architecture: Allows separate UI widgets (e.g. cart drawer, notification toasts, header badge)
 *   to respond to domain events (e.g. `order:item-added`) without knowing about each other's DOM structure.
 * - Memory Leak Elimination: Direct integration with native `AbortSignal` allows automatic listener unregistration
 *   when dynamic cards or modals are closed.
 *
 * Teaching notes:
 * - Import and use the exported singleton `eventBus` for app-wide events: `eventBus.emit('entity:updated', payload)`.
 * - For component-isolated events, instantiate `new EventBus()`.
 */
export class EventBus {
    constructor() {
        /** @type {Map<string, Set<Function>>} */
        this._listeners = new Map();
    }

    /**
     * Subscribes a handler callback to a named event.
     *
     * @param {string} event Event identifier (e.g., 'cart:updated', 'modal:opened').
     * @param {Function} handler Callback function receiving the event payload.
     * @param {Object} [options={}] Optional configuration ({ signal, once }).
     * @returns {Function} Unsubscribe closure function.
     */
    on(event, handler, options = {}) {
        if (typeof handler !== 'function') {
            throw new TypeError(`EventBus.on expects a function handler for event [${event}].`);
        }

        if (!this._listeners.has(event)) {
            this._listeners.set(event, new Set());
        }

        const listeners = this._listeners.get(event);

        // Handle one-time execution
        let effectiveHandler = handler;
        if (options.once) {
            effectiveHandler = (payload) => {
                this.off(event, effectiveHandler);
                handler(payload);
            };
            // Retain reference to original handler for off() lookups
            effectiveHandler._original = handler;
        }

        listeners.add(effectiveHandler);

        const unsubscribe = () => {
            this.off(event, effectiveHandler);
        };

        // AbortSignal lifecycle integration
        if (options.signal instanceof AbortSignal) {
            if (options.signal.aborted) {
                unsubscribe();
            } else {
                options.signal.addEventListener('abort', () => unsubscribe(), { once: true });
            }
        }

        return unsubscribe;
    }

    /**
     * Subscribes a one-time event handler.
     *
     * @param {string} event Event identifier.
     * @param {Function} handler Callback function.
     * @param {Object} [options={}] Optional AbortSignal config.
     * @returns {Function} Unsubscribe closure.
     */
    once(event, handler, options = {}) {
        return this.on(event, handler, { ...options, once: true });
    }

    /**
     * Removes an event listener from a specific event.
     *
     * @param {string} event Event identifier.
     * @param {Function} handler Handler function to remove.
     */
    off(event, handler) {
        const listeners = this._listeners.get(event);
        if (!listeners) return;

        if (listeners.has(handler)) {
            listeners.delete(handler);
        } else {
            // Check for wrapped once handlers
            for (const item of listeners) {
                if (item._original === handler) {
                    listeners.delete(item);
                    break;
                }
            }
        }

        if (listeners.size === 0) {
            this._listeners.delete(event);
        }
    }

    /**
     * Dispatches an event with an optional payload to all registered listeners.
     *
     * @param {string} event Event identifier.
     * @param {*} [payload=null] Arbitrary payload passed to listeners.
     */
    emit(event, payload = null) {
        const listeners = this._listeners.get(event);
        if (!listeners || listeners.size === 0) return;

        // Clone set to prevent mutation issues if a listener unsubscribes during iteration
        const snapshot = Array.from(listeners);
        for (const handler of snapshot) {
            try {
                handler(payload);
            } catch (error) {
                console.error(`EventBus error executing listener for [${event}]:`, error);
            }
        }
    }

    /**
     * Returns the count of registered listeners for an event.
     *
     * @param {string} event
     * @returns {number}
     */
    listenerCount(event) {
        const listeners = this._listeners.get(event);
        return listeners ? listeners.size : 0;
    }

    /**
     * Clears all listeners for a specific event, or all events if none specified.
     *
     * @param {string|null} [event=null]
     */
    clear(event = null) {
        if (event) {
            this._listeners.delete(event);
        } else {
            this._listeners.clear();
        }
    }
}

/** Default framework-wide singleton EventBus instance. */
export const eventBus = new EventBus();
