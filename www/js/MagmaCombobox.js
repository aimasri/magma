'use strict';

import { MenuItemState } from './MenuItemState.js';
import { ComboboxModel } from './MagmaComboboxModel.js';
import { ComboboxView } from './MagmaComboboxView.js';

/**
 * Title: Magma Combobox Controller
 * 
 * Purpose:
 * - Orchestrates interaction between the ComboboxView and ComboboxModel.
 * 
 * Why / Why this design:
 * - Acts as the mediator in the MVC pattern, ensuring the View and Model remain strictly decoupled from one another.
 * 
 * Teaching notes:
 * - Event binding happens during initialization.
 */
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

    /**
     * Binds input and select events from the View to Model data fetches.
     * 
     * Execution steps:
     * 1. Bind to onInput on the view. On input, request data from the model.
     * 2. On fetch success, pass the data to the view for rendering.
     * 3. Bind to onSelect on the view to update internal state.
     * 
     * Core architectural reasoning:
     * - The controller is the only component that knows both about the View and the Model.
     */
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
