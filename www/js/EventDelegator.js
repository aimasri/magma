/**
 * Title: High-Performance DOM Event Delegator
 *
 * Purpose:
 * - Centralizes event delegation onto root DOM nodes to eliminate per-element listener attachment.
 * - Supports `AbortSignal` lifecycle management and target matching via `Element.closest()`.
 *
 * Why / Why this design:
 * - Performance: Attaching thousands of individual event listeners to data-grid rows or cards consumes
 *   excessive browser memory and slows down DOM insertion. Delegating to a single parent container (or `document.body`)
 *   ensures O(1) listener overhead regardless of dataset size.
 * - Dynamic Content Resilience: Works seamlessly with dynamically injected AJAX partials without
 *   requiring re-binding.
 *
 * Teaching notes:
 * - Call `eventDelegator.on('click', '.btn-delete', (e, target) => { ... });`
 */
export class EventDelegator {
    /**
     * Initializes the EventDelegator on a specific root element (defaults to document).
     *
     * @param {Element|Document} [root=document] Root DOM node to bind delegated listeners to.
     */
    constructor(root = document) {
        this.root = root;
        this.registries = new Map();
    }

    /**
     * Registers a delegated event listener for a CSS selector.
     *
     * @param {string} eventType Event name (e.g. 'click', 'change', 'submit', 'input').
     * @param {string} selector CSS selector to match against.
     * @param {Function} handler Callback receiving `(event, targetElement)`.
     * @param {Object} [options={}] Optional listener options (signal).
     * @returns {Function} Unbind closure function.
     */
    on(eventType, selector, handler, options = {}) {
        if (typeof handler !== 'function') {
            throw new TypeError("EventDelegator.on requires a handler function.");
        }

        if (!this.registries.has(eventType)) {
            const registry = new Map();
            this.registries.set(eventType, registry);
            
            // Attach a single global listener for this event type
            this.root.addEventListener(eventType, (event) => {
                const target = event.target;
                if (!(target instanceof Element)) return;

                registry.forEach((handlers, sel) => {
                    const matchingElement = target.closest(sel);
                    if (matchingElement && (this.root === document || this.root.contains(matchingElement))) {
                        handlers.forEach(fn => fn(event, matchingElement));
                    }
                });
            });
        }

        const registry = this.registries.get(eventType);
        if (!registry.has(selector)) {
            registry.set(selector, new Set());
        }
        registry.get(selector).add(handler);

        const unbind = () => {
            const reg = this.registries.get(eventType);
            if (reg && reg.has(selector)) {
                reg.get(selector).delete(handler);
                if (reg.get(selector).size === 0) {
                    reg.delete(selector);
                }
            }
        };

        if (options.signal instanceof AbortSignal) {
            if (options.signal.aborted) {
                unbind();
            } else {
                options.signal.addEventListener('abort', () => unbind(), { once: true });
            }
        }

        return unbind;
    }

    /**
     * Static convenience delegate method.
     *
     * @param {Element|Document} root
     * @param {string} eventType
     * @param {string} selector
     * @param {Function} handler
     * @param {AbortSignal|null} [signal=null]
     * @returns {Function}
     */
    static delegate(root, eventType, selector, handler, signal = null) {
        const delegator = new EventDelegator(root);
        return delegator.on(eventType, selector, handler, { signal });
    }
}

/** Default document-wide delegator instance. */
export const eventDelegator = new EventDelegator(document);
