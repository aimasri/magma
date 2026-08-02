/**
 * Title: Menu DOM Listener Component
 * 
 * Purpose:
 * - Attaches and manages DOM event listeners for the navigation menu.
 * - Translates user interactions (clicks) into state updates via the state manager.
 * 
 * Why/Why this design:
 * - Employs Event Delegation by attaching a single listener to the container.
 * - This reduces memory footprint and automatically handles dynamically injected menu items without needing to rebind events.
 * 
 * Teaching notes:
 * - Event delegation is an industry-standard performance optimization for lists and menus.
 * - Remember that the container should be carefully scoped to avoid capturing unnecessary global clicks.
 */
export class MenuListeners {
    /**
     * Initializes the MenuListeners component.
     * 
     * @param {Object} stateManager - The state manager instance (MenuItemState).
     * @param {HTMLElement} [containerElement] - The root element for event delegation.
     */
    constructor(stateManager, containerElement = null) {
        this.stateManager = stateManager;
        this.container = containerElement || document.querySelector('.sidebar') || document.body;
        this.bindEvents();
    }

    /**
     * Binds event listeners to the container using event delegation.
     * 
     * 1. Attaches a click event listener to the root container.
     * 2. Checks if the clicked target matches the '.menu-item-toggle' selector.
     * 3. Extracts the data-id from the matched element.
     * 4. Dispatches an update to the state manager.
     * 
     * Logic behind the logic:
     * By filtering events at the container level (e.target.matches), we bypass the need to bind individual click handlers 
     * to every menu item. This is critical for single-page applications where DOM nodes might be destroyed and recreated frequently.
     */
    bindEvents() {
        // Use event delegation for better performance and dynamic element support
        this.container.addEventListener('click', (e) => {
            if (e.target.matches('.menu-item-toggle')) {
                const itemId = e.target.dataset.id;
                this.stateManager.update({ toggledItemId: itemId });
            }
        });
    }
}
