'use strict';

/**
 * Title: Combobox View
 * 
 * Purpose:
 * - Handles all DOM manipulation and user interaction event bindings for the combobox.
 * 
 * Why / Why this design:
 * - Isolating view logic ensures the controller and model are testable without a DOM environment.
 * 
 * Teaching notes:
 * - Uses DocumentFragment in `render()` to minimize DOM reflows and repaints during rapid updates.
 */
export class ComboboxView {
    constructor(inputElement) {
        this.input = inputElement;
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'magma-combobox-dropdown';
        
        if (this.input.parentNode) {
            this.input.parentNode.insertBefore(this.dropdown, this.input.nextSibling);
        } else {
            console.warn("MagmaCombobox: input element must be attached to the DOM.");
        }
    }

    /**
     * Renders the combobox items into the dropdown using a DocumentFragment for performance.
     * 
     * Execution steps:
     * 1. Clears current dropdown.
     * 2. Loops over results, building a DOM node for each item.
     * 3. Appends all at once via a fragment.
     * 
     * Core architectural reasoning:
     * - A fragment avoids thrashing the DOM which is crucial for autocomplete performance.
     * 
     * @param {Array} items - The list of items to render.
     */
    render(items) {
        this.dropdown.innerHTML = '';
        const fragment = document.createDocumentFragment();
        
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
            fragment.appendChild(wrapper);
        });
        
        this.dropdown.appendChild(fragment);
    }

    clear() {
        this.dropdown.innerHTML = '';
    }

    setInputValue(value) {
        this.input.value = value;
    }

    onInput(callback, debounceDelay) {
        let debounceTimer;
        this.inputHandler = (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                callback(e.target.value);
            }, debounceDelay);
        };
        this.input.addEventListener('input', this.inputHandler);
    }

    onSelect(callback) {
        this.selectHandler = (e) => {
            const item = e.target.closest('.magma-combobox-item');
            if (item) {
                callback({
                    id: item.dataset.id,
                    name: item.dataset.name
                });
            }
        };
        this.dropdown.addEventListener('click', this.selectHandler);
    }

    destroy() {
        if (this.inputHandler) {
            this.input.removeEventListener('input', this.inputHandler);
        }
        if (this.selectHandler) {
            this.dropdown.removeEventListener('click', this.selectHandler);
        }
        if (this.dropdown.parentNode) {
            this.dropdown.parentNode.removeChild(this.dropdown);
        }
    }
}
