import { MenuItemState } from './MenuItemState.js';

/**
 * Title: Magma Combobox Component
 * 
 * Purpose:
 * - Creates an interactive autocomplete/combobox dropdown for a given input element.
 * - Handles asynchronous data fetching, debouncing, and rendering of results.
 * 
 * Why/Why this design:
 * - Refactored to Model-View-Controller (MVC) to separate DOM manipulation, state, and event orchestration.
 * - Uses debouncing to limit network requests, which is essential for performance and rate-limit compliance.
 */

class ComboboxModel {
    constructor(dataProvider) {
        if (typeof dataProvider !== 'function') {
            throw new Error("MagmaCombobox requires a dataProvider function in options.");
        }
        this.dataProvider = dataProvider;
    }

    async fetch(query) {
        if (!query) return [];
        try {
            const items = await this.dataProvider(query);
            return items || [];
        } catch (error) {
            console.error("MagmaCombobox dataProvider error:", error);
            return [];
        }
    }
}

class ComboboxView {
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

    clear() {
        this.dropdown.innerHTML = '';
    }

    setInputValue(value) {
        this.input.value = value;
    }

    onInput(callback, debounceDelay) {
        let debounceTimer;
        this.input.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                callback(e.target.value);
            }, debounceDelay);
        });
    }

    onSelect(callback) {
        this.dropdown.addEventListener('click', (e) => {
            const item = e.target.closest('.magma-combobox-item');
            if (item) {
                callback({
                    id: item.dataset.id,
                    name: item.dataset.name
                });
            }
        });
    }
}

export class MagmaCombobox {
    /**
     * Initializes the MagmaCombobox controller instance.
     * 
     * @param {HTMLElement} inputElement - The target input DOM node.
     * @param {Object} options - Configuration options, particularly the dataProvider function and debounceDelay.
     */
    constructor(inputElement, options = {}) {
        this.options = options;
        this.debounceDelay = options.debounceDelay || 300;
        this.state = new MenuItemState();
        
        this.model = new ComboboxModel(options.dataProvider);
        this.view = new ComboboxView(inputElement);

        this.bindEvents();
    }

    bindEvents() {
        this.view.onInput(async (query) => {
            if (!query) {
                this.view.clear();
                return;
            }
            const data = await this.model.fetch(query);
            this.view.render(data);
        }, this.debounceDelay);

        this.view.onSelect((item) => {
            this.state.update({ selectedId: item.id });
            this.view.setInputValue(item.name);
            this.view.clear();
        });
    }
}
