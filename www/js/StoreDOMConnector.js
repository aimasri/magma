/**
 * Title: Store DOM Connector
 *
 * Purpose:
 * - Binds reactive data stores (ObservableStore) directly to DOM elements.
 * - Manages the lifecycle of subscriptions to prevent memory leaks when DOM elements are removed.
 *
 * Why / Why this design:
 * - Single Responsibility Principle (SRP): Extracted from the `ObservableStore` to ensure that 
 *   data-layer logic remains decoupled from UI/DOM lifecycle management.
 * - Memory Safety: By proactively checking `!document.body.contains(element)`, the connector 
 *   safely unsubscribes orphaned listeners, avoiding ghost rendering.
 *
 * Teaching notes:
 * - Pass an AbortSignal to safely cancel network requests or long-running computations tied to 
 *   the lifecycle of the connected DOM element.
 */
export class StoreDOMConnector {
    static connect(store, element, renderFn, selector = null, signal = null) {
        if (!element || !(element instanceof Element)) {
            throw new TypeError("StoreDOMConnector requires a valid HTMLElement.");
        }
        renderFn(selector ? selector(store.getState()) : store.getState(), element);

        const unsubscribe = store.subscribe((state) => {
            if (!element.isConnected) {
                unsubscribe();
                return;
            }
            renderFn(selector ? selector(state) : state, element);
        }, selector, signal);

        return unsubscribe;
    }
}
