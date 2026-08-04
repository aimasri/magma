'use strict';

/**
 * Title: Menu Item State Manager
 * 
 * Purpose:
 * - Maintains the state of menu items (e.g., toggled states, selected items).
 * - Implements the Observer pattern to notify listeners of state changes.
 * 
 * Why/Why this design:
 * - Uses a simplistic unidirectional data flow (similar to Redux or Vuex but lightweight).
 * - State immutability is maintained via spread operators during updates, preventing accidental side effects.
 * 
 * Teaching notes:
 * - This is a classic Pub/Sub or Observable pattern.
 * - For larger applications, consider integrating a proper state machine or full-fledged state library if the complexity of the menu state grows.
 */
export class MenuItemState {
    constructor() {
        this.subscribers = [];
        this.state = {};
    }

    /**
     * Registers a callback to be invoked upon state changes.
     * 
     * @param {Function} callback - The function to execute when state updates.
     */
    subscribe(callback) {
        this.subscribers.push(callback);
    }

    /**
     * Updates the internal state and triggers notifications to all subscribers.
     * 
     * 1. Merges the current state with the provided new state using the spread operator for a shallow copy.
     * 2. Invokes the notify process to alert subscribers.
     * 
     * Logic behind the logic:
     * Shallow cloning enforces immutability at the top level, ensuring subscribers receive a distinct state object, 
     * which helps React-like UI frameworks accurately determine if a re-render is necessary.
     * 
     * @param {Object} newState - The partial state to merge.
     */
    update(newState) {
        this.state = { ...this.state, ...newState };
        this.notify();
    }

    /**
     * Iterates through all registered subscribers and executes them with the current state.
     */
    notify() {
        this.subscribers.forEach(fn => fn(this.state));
    }
}
