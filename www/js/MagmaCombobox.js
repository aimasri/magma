import { MenuItemState } from './MenuItemState.js';

/**
 * Title: Magma Combobox Component
 * 
 * Purpose:
 * - Creates an interactive autocomplete/combobox dropdown for a given input element.
 * - Handles asynchronous data fetching, debouncing, and rendering of results.
 * 
 * Why/Why this design:
 * - Encapsulates DOM manipulation, state, and event handling within a class.
 * - Uses debouncing to limit network requests, which is essential for performance and rate-limit compliance.
 * 
 * Teaching notes:
 * - Observe the injection of `dataProvider` via options, an application of the Strategy pattern, allowing the combobox to be agnostic of the data source (API, local array, etc.).
 * - To improve accessibility (a11y), consider adding ARIA attributes (e.g., aria-expanded, aria-activedescendant) and keyboard navigation.
 */
export class MagmaCombobox {
    /**
     * Initializes the MagmaCombobox instance.
     * 
     * 1. Assigns dependencies and options.
     * 2. Instantiates a localized MenuItemState for internal state management.
     * 3. Constructs the dropdown DOM element and injects it adjacent to the target input.
     * 4. Binds necessary DOM events.
     * 
     * Logic behind the logic:
     * Injecting the dropdown container immediately after the input ensures natural tab-ordering and DOM proximity, 
     * which simplifies CSS positioning (e.g., using relative/absolute pairings).
     * 
     * @param {HTMLElement} inputElement - The target input DOM node.
     * @param {Object} options - Configuration options, particularly the dataProvider function.
     */
    constructor(inputElement, options = {}) {
        this.input = inputElement;
        this.options = options;
        this.state = new MenuItemState();
        this.debounceTimer = null;
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'magma-combobox-dropdown';
        this.input.parentNode.insertBefore(this.dropdown, this.input.nextSibling);

        this.bindEvents();
    }

    /**
     * Attaches input and click event listeners for the combobox.
     * 
     * 1. Binds an 'input' listener to handle typing, utilizing a debounce timer to delay the data fetch.
     * 2. Binds a 'click' listener to the dropdown container to handle item selection via event delegation.
     * 3. On selection, updates internal state, populates the input, and clears the dropdown.
     * 
     * Logic behind the logic:
     * Debouncing is implemented directly via setTimeout to avoid external dependencies, ensuring the component remains lightweight. 
     * Event delegation on the dropdown guarantees that dynamically rendered items remain clickable without rebinding.
     */
    bindEvents() {
        this.input.addEventListener('input', (e) => {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.fetchData(e.target.value);
            }, 300);
        });
        
        // Example of recursive event propagation
        this.dropdown.addEventListener('click', (e) => {
            const item = e.target.closest('.magma-combobox-item');
            if (item) {
                this.state.update({ selectedId: item.dataset.id });
                this.input.value = item.dataset.name;
                this.dropdown.innerHTML = ''; // close dropdown
            }
        });
    }

    /**
     * Asynchronously fetches data based on the user's query.
     * 
     * 1. Clears the dropdown if the query is empty.
     * 2. Validates the existence of the `dataProvider` function in the options.
     * 3. Awaits the results from the `dataProvider`.
     * 4. Passes the items to the render method.
     * 
     * Logic behind the logic:
     * The method delegates the actual fetching logic to an external `dataProvider` (Inversion of Control), 
     * allowing the combobox to be reused for various data sources without modifying its internal logic.
     * 
     * @param {string} query - The search string inputted by the user.
     */
    async fetchData(query) {
        if (!query) {
            this.dropdown.innerHTML = '';
            return;
        }

        try {
            if (typeof this.options.dataProvider !== 'function') {
                throw new Error("MagmaCombobox requires a dataProvider function in options.");
            }
            const items = await this.options.dataProvider(query);
            this.render(items || []);
        } catch (error) {
            console.error("MagmaCombobox dataProvider error:", error);
        }
    }

    /**
     * Renders the fetched items into the dropdown DOM.
     * 
     * 1. Empties the current dropdown contents.
     * 2. Iterates over the items array.
     * 3. Constructs individual wrapper elements with title and subtitle for each item.
     * 4. Appends them to the dropdown container.
     * 
     * Logic behind the logic:
     * Direct DOM manipulation (document.createElement) is used here for performance and to avoid innerHTML injection vulnerabilities (XSS), 
     * ensuring that data properties are safely set as textContent.
     * 
     * @param {Array} items - The list of data objects to render.
     */
    render(items) {
        this.dropdown.innerHTML = '';
        items.forEach(item => {
            const wrapper = document.createElement('div');
            wrapper.className = 'magma-combobox-item';
            wrapper.dataset.id = item.id;
            wrapper.dataset.name = item.name;

            const title = document.createElement('div');
            title.className = 'item-title';
            title.textContent = item.name;

            const subtitle = document.createElement('div');
            subtitle.className = 'item-subtitle';
            subtitle.textContent = item.description || '';

            wrapper.appendChild(title);
            wrapper.appendChild(subtitle);
            this.dropdown.appendChild(wrapper);
        });
    }
}
