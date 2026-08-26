'use strict';

import { ComboboxModel } from './MagmaComboboxModel.js';
import { ComboboxView } from './MagmaComboboxView.js';

/**
 * Title: Enhanced Magma Combobox Controller
 * 
 * Purpose:
 * - Orchestrates asynchronous debounced search, wildcard data attribute propagation,
 *   keyboard navigation, and multi-line selected card state transitions.
 * 
 * Why / Why this design:
 * - Wildcard Data Propagation (`data-propagate`): Automatically propagates selected item properties
 *   (e.g., `data-propagate="unit,price,allergens,sku"`) to matching sibling form inputs without
 *   requiring custom glue code for every form in the application.
 * - Decoupled MVC Architecture: The controller bridges the Model (network/cache) and View (DOM/a11y).
 * - Instant Edit Transitions: Supports seamless multi-line card display with click-to-edit interactions.
 * 
 * Teaching notes:
 * - Initialize via JS:
 *   new MagmaCombobox(inputElement, { ajaxUrl: '/api/ingredients', propagate: ['unit', 'price'] });
 * - Or initialize declaratively via HTML data attributes:
 *   <input data-combobox data-ajax-url="/api/services" data-propagate="price,duration,category">
 */
export class MagmaCombobox {
    /**
     * Initializes the MagmaCombobox controller instance.
     * 
     * @param {HTMLElement|string} input Target input DOM node or selector.
     * @param {Object} [options={}] Configuration options.
     */
    constructor(input, options = {}) {
        this.inputElement = typeof input === 'string' ? document.querySelector(input) : input;
        if (!this.inputElement) {
            throw new Error(`MagmaCombobox: Target element [${input}] not found.`);
        }

        // Read declarative HTML attributes as defaults
        const dataset = this.inputElement.dataset || {};
        const ajaxUrl = options.ajaxUrl || dataset.ajaxUrl || dataset.url;
        const dataProvider = options.dataProvider || ajaxUrl;

        this.options = {
            debounceDelay: options.debounceDelay || parseInt(dataset.debounce || '300', 10),
            propagate: options.propagate || (dataset.propagate ? dataset.propagate.split(',').map(s => s.trim()) : []),
            customRenderer: options.customRenderer || null,
            cardFormatter: options.cardFormatter || null,
            extractItem: options.extractItem || null,
            onSelect: options.onSelect || null,
            minChars: options.minChars !== undefined ? options.minChars : 1,
            ...options
        };

        this.model = new ComboboxModel(dataProvider, this.options);
        this.view = new ComboboxView(this.inputElement, this.options);

        this.bindEvents();
    }

    /**
     * Binds input typing, keyboard navigation, and selection events.
     *
     * Execution Flow:
     * 1. Debounced Text Search: Listens for input, fetches data when minChars are met, and updates the View.
     * 2. Selection Handling: Captures select events from the View and passes them to selectItem.
     * 3. Keyboard Navigation: Manages ArrowDown, ArrowUp, Enter, and Escape keys for a11y compliance.
     * 4. Outside Click: Detects clicks outside the combobox wrapper to automatically close the dropdown menu.
     *
     * Logic behind the logic:
     * - Event Delegation and Debouncing: Centralizing event listeners avoids memory leaks when the combobox is destroyed, and debouncing network requests prevents API spam while typing.
     */
    bindEvents() {
        // 1. Debounced text search
        this.view.onInput(async (query) => {
            try {
                if (!query || query.trim().length < this.options.minChars) {
                    if (this.model._currentAbortController) {
                        this.model._currentAbortController.abort();
                    }
                    this.view.clear();
                    return;
                }
                const data = await this.model.fetch(query);
                
                // Prevent race conditions and stale response rendering
                if (this.inputElement.value.trim() !== query.trim()) {
                    return;
                }

                this.view.render(data, this.options.customRenderer);
            } catch (error) {
                console.error("Error fetching combobox data:", error);
            }
        }, this.options.debounceDelay);

        // 2. Selection click handling
        this.view.onSelect((item) => {
            this.selectItem(item);
        });

        // 3. Keyboard navigation (ArrowDown, ArrowUp, Enter, Escape)
        const keyMap = {
            'ArrowDown': (e) => {
                e.preventDefault();
                this.view.highlightNext();
            },
            'ArrowUp': (e) => {
                e.preventDefault();
                this.view.highlightPrev();
            },
            'Enter': (e) => {
                const highlighted = this.view.getHighlightedItem();
                if (highlighted) {
                    e.preventDefault();
                    this.selectItem(highlighted);
                }
            },
            'Escape': (e) => {
                e.preventDefault();
                this.view.close();
            }
        };

        this.keydownHandler = (e) => {
            if (this.view.isOpen() && keyMap[e.key]) {
                keyMap[e.key](e);
            }
        };
        this.inputElement.addEventListener('keydown', this.keydownHandler);

        // 4. Close dropdown on outside click
        this.outsideClickHandler = (e) => {
            if (!this.inputElement.isConnected) {
                document.removeEventListener('click', this.outsideClickHandler);
                return;
            }
            if (!this.view.wrapper.contains(e.target)) {
                this.view.close();
            }
        };
        document.addEventListener('click', this.outsideClickHandler);
    }

    /**
     * Handles selection of an item: propagates data, renders card, and fires callbacks.
     *
     * @param {Object} item The selected item data.
     */
    selectItem(item) {
        // Custom extraction hook
        const processedItem = typeof this.options.extractItem === 'function'
            ? this.options.extractItem(item)
            : item;

        // Render multi-line selected card
        this.view.renderSelectedCard(processedItem, this.options.cardFormatter);

        // Notify callback
        if (typeof this.options.onSelect === 'function') {
            this.options.onSelect(processedItem);
        }

        // Dispatch native custom event on input
        const event = new CustomEvent('combobox:select', {
            bubbles: true,
            detail: { item: processedItem }
        });
        this.inputElement.dispatchEvent(event);
    }

    /**
     * Destroys controller and underlying view.
     */
    destroy() {
        if (this.keydownHandler) {
            this.inputElement.removeEventListener('keydown', this.keydownHandler);
        }
        if (this.outsideClickHandler) {
            document.removeEventListener('click', this.outsideClickHandler);
        }
        this.view.destroy();
    }
}
