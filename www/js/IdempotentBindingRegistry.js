/**
 * Title: Idempotent DOM Binding Registry
 *
 * Purpose:
 * - Manages idempotent DOM event listener bindings using `WeakMap` and `AbortController` signals.
 * - Guarantees that re-rendering or reopening dynamic modals/cards never attaches duplicate listeners.
 * - Automatically tears down event listeners and aborts background tasks on dynamic node replacement.
 *
 * Why / Why this design:
 * - Memory Leak & Event Storm Elimination: In hybrid MPA/SPA workflows, dynamically inserting HTML
 *   partials or opening modal windows frequently results in `addEventListener` being called multiple
 *   times on the same element or parent container, causing compounding duplicated AJAX requests.
 * - Idempotency by Key: Tracks active bindings by element reference and string key. If a binding already
 *   exists, subsequent bind requests are safely ignored.
 * - AbortController Lifecycle: Supplies an `AbortSignal` directly into the binding closure, allowing
 *   all nested listeners, timers, or fetch calls to be aborted with a single call.
 *
 * Teaching notes:
 * - Wrap modal initializers in: `bindingRegistry.bind(modalElement, 'my-modal-key', (el, signal) => { ... });`
 */
export class IdempotentBindingRegistry {
    constructor() {
        /** @type {WeakMap<Element, Map<string, AbortController>>} */
        this._bindings = new WeakMap();
    }

    /**
     * Idempotently binds event listeners or initialization logic to a DOM element.
     *
     * Execution Flow:
     * 1. Check if the element already has an active binding for `key`.
     * 2. If already bound, return false (skipping execution to prevent duplicate listeners).
     * 3. If not bound, create a new `AbortController`.
     * 4. Store the controller in the element's WeakMap entry.
     * 5. Execute `binderFn(element, abortController.signal)`.
     * 6. Return true indicating binding occurred.
     *
     * @param {Element} element Target DOM element node.
     * @param {string} key Unique binding identifier (e.g., 'modal-submit-listener', 'card-hover').
     * @param {Function} binderFn Closure receiving `(element, AbortSignal)`.
     * @returns {boolean} True if new binding was established, false if already bound.
     */
    bind(element, key, binderFn) {
        if (!element || !(element instanceof Element)) {
            throw new TypeError("IdempotentBindingRegistry.bind requires a valid DOM Element.");
        }

        if (typeof binderFn !== 'function') {
            throw new TypeError("IdempotentBindingRegistry.bind requires a binderFn closure.");
        }

        let elementMap = this._bindings.get(element);
        if (!elementMap) {
            elementMap = new Map();
            this._bindings.set(element, elementMap);
        }

        // If already bound with this key, do not bind again
        if (elementMap.has(key)) {
            return false;
        }

        const controller = new AbortController();
        elementMap.set(key, controller);

        try {
            binderFn(element, controller.signal);
        } catch (error) {
            console.error(`IdempotentBindingRegistry error during bind [${key}]:`, error);
            // Abort and unbind on initialization failure
            controller.abort();
            elementMap.delete(key);
            throw error;
        }

        return true;
    }

    /**
     * Checks whether an element is currently bound under a specific key.
     *
     * @param {Element} element
     * @param {string} key
     * @returns {boolean}
     */
    isBound(element, key) {
        if (!element || !(element instanceof Element)) return false;
        const elementMap = this._bindings.get(element);
        return Boolean(elementMap && elementMap.has(key));
    }

    /**
     * Retrieves the active AbortSignal for a bound element key.
     *
     * @param {Element} element
     * @param {string} key
     * @returns {AbortSignal|null}
     */
    getSignal(element, key) {
        if (!element || !(element instanceof Element)) return null;
        const elementMap = this._bindings.get(element);
        if (!elementMap || !elementMap.has(key)) return null;
        return elementMap.get(key).signal;
    }

    /**
     * Unbinds and aborts a specific binding on an element.
     *
     * @param {Element} element
     * @param {string} key
     * @returns {boolean} True if an active binding was aborted and removed.
     */
    unbind(element, key) {
        if (!element || !(element instanceof Element)) return false;
        const elementMap = this._bindings.get(element);
        if (!elementMap || !elementMap.has(key)) return false;

        const controller = elementMap.get(key);
        controller.abort();
        elementMap.delete(key);

        return true;
    }

    /**
     * Unbinds and aborts all active bindings associated with an element.
     *
     * @param {Element} element
     * @returns {number} Count of unbind operations performed.
     */
    unbindAll(element) {
        if (!element || !(element instanceof Element)) return 0;
        const elementMap = this._bindings.get(element);
        if (!elementMap) return 0;

        let count = 0;
        for (const [key, controller] of elementMap.entries()) {
            controller.abort();
            count++;
        }

        elementMap.clear();
        return count;
    }
}

/** Default singleton instance. */
export const bindingRegistry = new IdempotentBindingRegistry();
