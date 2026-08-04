'use strict';

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

    /**
     * Renders the combobox items into the dropdown using a DocumentFragment for performance.
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
        this.state = options.state || new MenuItemState();
        
        this.model = new ComboboxModel(options.dataProvider);
        this.view = new ComboboxView(inputElement);

        this.bindEvents();
    }

    bindEvents() {
        this.view.onInput(async (query) => {
            try {
                if (!query) {
                    this.view.clear();
                    return;
                }
                const data = await this.model.fetch(query);
                this.view.render(data);
            } catch (error) {
                console.error("Error fetching combobox data:", error);
            }
        }, this.debounceDelay);

        this.view.onSelect((item) => {
            this.state.update({ selectedId: item.id });
            this.view.setInputValue(item.name);
            this.view.clear();
        });
    }

    destroy() {
        this.view.destroy();
    }
}
