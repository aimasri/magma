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
     */
    bindEvents() {
        // 1. Debounced text search
        this.view.onInput(async (query) => {
            try {
                if (!query || query.trim().length < this.options.minChars) {
                    this.view.clear();
                    return;
                }
                const data = await this.model.fetch(query);
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
        this.keydownHandler = (e) => {
            if (this.view.dropdown.style.display === 'block') {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.view.highlightNext();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.view.highlightPrev();
                } else if (e.key === 'Enter') {
                    const highlighted = this.view.getHighlightedItem();
                    if (highlighted) {
                        e.preventDefault();
                        this.selectItem(highlighted);
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.view.close();
                }
            }
        };
        this.inputElement.addEventListener('keydown', this.keydownHandler);

        // 4. Close dropdown on outside click
        this.outsideClickHandler = (e) => {
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

        // Propagate data to related form inputs
        this._propagateData(processedItem);

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
     * Propagates selected item properties to matching form fields in the surrounding form or container.
     *
     * @param {Object} item
     * @private
     */
    _propagateData(item) {
        if (!item || !Array.isArray(this.options.propagate) || this.options.propagate.length === 0) {
            return;
        }

        const form = this.inputElement.closest('form') || document;

        for (const propName of this.options.propagate) {
            const val = item[propName];
            if (val === undefined) continue;

            // Look for matching input by name, id, or data-field
            const targetInput = form.querySelector(
                `[name="${propName}"], #${propName}, [data-propagate-target="${propName}"]`
            );

            if (targetInput && 'value' in targetInput) {
                targetInput.value = val;
                targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                targetInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
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
