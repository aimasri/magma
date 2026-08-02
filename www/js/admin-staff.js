import { MenuItemState } from './MenuItemState.js';
import { MenuListeners } from './MenuListeners.js';

/**
 * Title: Admin Staff Module Entry Point
 * 
 * Purpose:
 * - Coordinates the initialization of the Admin Staff interface.
 * - Binds the menu state manager with the menu interaction listeners.
 * 
 * Why/Why this design:
 * - Uses a composition pattern to instantiate state management and listeners.
 * - Decoupling the state (MenuItemState) from the DOM listeners (MenuListeners) follows separation of concerns, 
 *   ensuring UI logic does not directly mutate internal data structures.
 * 
 * Teaching notes:
 * - This acts as a controller/factory in the MVC/MVP pattern.
 * - When extending this module, prefer passing `menuState` as a dependency to other UI components to maintain a single source of truth.
 */

/**
 * Initializes the admin staff module components.
 * 
 * Logic behind the logic:
 * Sets up the foundational state and event binding for the module. A single state instance 
 * is passed into the listener component, establishing a clean unidirectional data loop 
 * where interactions trigger state changes, and state changes notify subscribers.
 */
export function initAdminStaff() {
    console.log("Admin Staff Module loaded via ES6");
    const menuState = new MenuItemState();
    new MenuListeners(menuState);
    
    menuState.subscribe((state) => {
        console.log("Menu state updated:", state);
    });
}

/**
 * Module entry point listener.
 * 
 * Logic behind the logic:
 * Ensures all required DOM nodes are parsed and available before attempting to bind events 
 * or instantiate module logic.
 */
document.addEventListener('DOMContentLoaded', () => {
    initAdminStaff();
});
