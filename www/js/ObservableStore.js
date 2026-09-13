/**
 * Title: Observable Reactive State Store
 *
 * Purpose:
 * - Provides a vanilla ES6 reactive client-side store implementing the Observer (Pub/Sub) Pattern.
 * - Supports granular slice selectors, immutability guarantees, action dispatching, and lifecycle teardown hooks.
 *
 * Why / Why this design:
 * - Decoupled UI State: Eliminates state synchronization bugs across disparate DOM components (e.g. modals,
 *   dropdowns, cart tallies) without requiring heavy dependencies like Redux or Vuex.
 * - Memory Leak Prevention: Standardized `destroy()` hooks and `AbortSignal` integration ensure event
 *   subscriptions are cleanly unregistered when dynamic DOM cards/modals are removed.
 * - Granular Subscriptions: Selector functions prevent unnecessary DOM updates by only notifying
 *   listeners when their specific slice of state actually changes (shallow equality guard).
 *
 * Teaching notes:
 * - Instantiate a store with `new ObservableStore(initialState)`.
 * - Subscribe with `store.subscribe((state) => render(state), (state) => state.selectedItem)`.
 * - Call `store.destroy()` when tearing down a page or component.
 */
export class ObservableStore {
    /**
     * Initializes the ObservableStore with an initial state dictionary.
     *
     * @param {Object} [initialState={}] Initial state object.
     * @param {Object} [options={}] Store configuration options.
     */
    constructor(initialState = {}, options = {}) {
        this._state = this._deepFreeze({ ...initialState });
        this._subscribers = new Set();
        this._reducers = new Map();
        this._options = {
            freeze: options.freeze !== false,
            debug: options.debug === true,
            ...options
        };
        this._isDestroyed = false;
    }

    /**
     * Retrieves the current immutable snapshot of the store's state.
     *
     * @returns {Object} Read-only state object.
     */
    getState() {
        return this._state;
    }

    /**
     * Updates the state using a partial object or a pure updater function `(prevState) => nextState`.
     *
     * Execution Flow:
     * 1. If destroyed, abort.
     * 2. Compute next state based on object merge or updater return.
     * 3. Verify if state actually changed (shallow comparison).
     * 4. Freeze and assign new state.
     * 5. Notify all registered subscribers matching their selector constraints.
     *
     * @param {Object|Function} updater Partial state object or reducer function.
     * @param {string} [actionName='SET_STATE'] Descriptive name for debugging/tracing.
     * @returns {Object} The updated state snapshot.
     */
    setState(updater, actionName = 'SET_STATE') {
        if (this._isDestroyed) {
            console.warn(`ObservableStore: Attempted to setState on destroyed store [${actionName}].`);
            return this._state;
        }

        const prevState = this._state;
        let partial;

        if (typeof updater === 'function') {
            partial = updater(prevState);
        } else if (typeof updater === 'object' && updater !== null) {
            partial = updater;
        } else {
            throw new TypeError("ObservableStore.setState expects an object or updater function.");
        }

        const nextState = this._options.freeze
            ? this._deepFreeze({ ...prevState, ...partial })
            : { ...prevState, ...partial };

        // Shallow equality check
        if (this._isEqual(prevState, nextState)) {
            return this._state;
        }

        this._state = nextState;

        if (this._options.debug) {
            console.log(`[ObservableStore][${actionName}]`, { prevState, nextState, partial });
        }

        this._notifySubscribers(prevState, nextState, actionName);

        return this._state;
    }

    /**
     * Subscribes a listener function to store updates.
     *
     * @param {Function} listener Callback receiving `(nextState, prevState, actionName)`.
     * @param {Function|null} [selector=null] Optional slice selector `(state) => state.slice`.
     * @param {AbortSignal|null} [signal=null] Optional AbortSignal for automatic subscription teardown.
     * @returns {Function} Unsubscribe function.
     */
    subscribe(listener, selector = null, signal = null) {
        if (typeof listener !== 'function') {
            throw new TypeError("ObservableStore.subscribe expects a listener function.");
        }

        if (this._isDestroyed) {
            return () => {};
        }

        let lastSelected = selector ? selector(this._state) : null;

        const subscriberRecord = {
            listener,
            selector,
            lastSelected
        };

        this._subscribers.add(subscriberRecord);

        // Immediate unsubscribe closure
        const unsubscribe = () => {
            this._subscribers.delete(subscriberRecord);
        };

        // Automatic AbortSignal teardown
        if (signal instanceof AbortSignal) {
            if (signal.aborted) {
                unsubscribe();
            } else {
                signal.addEventListener('abort', () => unsubscribe(), { once: true });
            }
        }

        return unsubscribe;
    }

    /**
     * Registers a typed action reducer.
     *
     * @param {string} actionType Unique action name (e.g., 'ITEM_ADDED', 'FILTER_CHANGED').
     * @param {Function} reducer Function receiving `(state, payload) => nextState`.
     */
    registerReducer(actionType, reducer) {
        if (typeof reducer !== 'function') {
            throw new TypeError(`Reducer for [${actionType}] must be a function.`);
        }
        this._reducers.set(actionType, reducer);
    }

    /**
     * Dispatches an action to registered reducers.
     *
     * @param {string} actionType Action identifier.
     * @param {*} [payload=null] Action payload data.
     * @returns {Object} The resulting state.
     */
    dispatch(actionType, payload = null) {
        const reducer = this._reducers.get(actionType);
        if (reducer) {
            return this.setState((state) => reducer(state, payload), actionType);
        }

        if (this._options.debug) {
            console.warn(`ObservableStore: No reducer registered for action [${actionType}].`);
        }

        return this._state;
    }

    /**
     * Internal subscriber notification engine with slice change detection.
     *
     * @param {Object} prevState
     * @param {Object} nextState
     * @param {string} actionName
     * @private
     */
    _notifySubscribers(prevState, nextState, actionName) {
        for (const record of this._subscribers) {
            try {
                if (record.selector) {
                    const currentSelected = record.selector(nextState);
                    if (!this._isEqual(record.lastSelected, currentSelected)) {
                        const previousSelected = record.lastSelected;
                        record.lastSelected = currentSelected;
                        record.listener(currentSelected, previousSelected, actionName);
                    }
                } else {
                    record.listener(nextState, prevState, actionName);
                }
            } catch (error) {
                console.error(`ObservableStore subscriber error during [${actionName}]:`, error);
            }
        }
    }

    /**
     * Shallow equality comparator.
     *
     * @param {*} a
     * @param {*} b
     * @returns {boolean}
     * @private
     */
    _isEqual(a, b) {
        if (Object.is(a, b)) return true;
        if (typeof a !== 'object' || a === null || typeof b !== 'object' || b === null) return false;

        const keysA = Object.keys(a);
        const keysB = Object.keys(b);

        if (keysA.length !== keysB.length) return false;

        for (const key of keysA) {
            if (!Object.prototype.hasOwnProperty.call(b, key) || !Object.is(a[key], b[key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cleans up all subscribers, reducers, and tears down the store instance.
     */
    destroy() {
        this._isDestroyed = true;
        this._subscribers.clear();
        this._reducers.clear();
        this._state = this._deepFreeze({});
    }

    /**
     * Recursively freezes an object to ensure deep immutability.
     *
     * Execution Flow:
     * 1. Check if the input object is null or not of type 'object'. If so, return it directly.
     * 2. Call Object.freeze() on the current object to prevent modification of its immediate properties.
     * 3. Iterate over all keys of the object.
     * 4. For each key, if its value is an object or function and is not yet frozen, recursively call _deepFreeze on it.
     * 5. Return the fully frozen object.
     *
     * Logic behind the logic:
     * - Shallow freezing (Object.freeze) only prevents modification of the top-level properties. To guarantee state consistency and prevent accidental mutations in a reactive store, we traverse the entire object tree to ensure deeply nested objects are also strictly immutable.
     *
     * @param {Object} obj
     * @returns {Object}
     * @private
     */
    _deepFreeze(obj) {
        if (obj === null || typeof obj !== 'object') {
            return obj;
        }

        Object.freeze(obj);

        for (const key of Object.keys(obj)) {
            const prop = obj[key];
            if (prop !== null && (typeof prop === 'object' || typeof prop === 'function') && !Object.isFrozen(prop)) {
                this._deepFreeze(prop);
            }
        }

        return obj;
    }
}


