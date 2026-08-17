'use strict';

import { templateEngine } from './TemplateEngine.js';

/**
 * Title: Enhanced Combobox View
 * 
 * Purpose:
 * - Handles DOM rendering, multi-line selected card display, keyboard accessibility, and UI transitions.
 * 
 * Why / Why this design:
 * - Multi-line Selected Card: Renders chosen entity with title + badge on row 1 and specs/description
 *   on row 2, providing immediate visual confirmation of selection.
 * - Instant Edit Transition: Clicking the selected card seamlessly reveals the search input for instant editing.
 * - DocumentFragment Rendering: Uses DocumentFragments to avoid layout thrashing during autocomplete rendering.
 * - Accessibility (a11y): Supports ARIA combobox pattern and keyboard navigation (ArrowUp, ArrowDown, Enter, Escape).
 * 
 * Teaching notes:
 * - The view remains decoupled from data fetching; it receives plain JavaScript objects.
 */
export class ComboboxView {
    /**
     * @param {HTMLElement} inputElement Target input element.
     * @param {Object} [options={}] UI configuration options.
     */
    constructor(inputElement, options = {}) {
        this.input = inputElement;
        this.options = options;
        this.highlightedIndex = -1;
        this.items = [];

        this._initDOM();
    }

    /**
     * Sets up container, dropdown, selected card, and ARIA markup.
     * @private
     */
    _initDOM() {
        // Wrap input in combobox wrapper if not already wrapped
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'magma-combobox-wrapper';

        if (this.input.parentNode) {
            this.input.parentNode.insertBefore(this.wrapper, this.input);
            this.wrapper.appendChild(this.input);
        }

        const baseId = this.input.id || `magma-combo-${Math.random().toString(36).substr(2, 9)}`;
        this.input.id = baseId;

        this.input.classList.add('magma-combobox-input');
        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.setAttribute('aria-controls', `${baseId}-listbox`);
        this.input.setAttribute('autocomplete', 'off');

        // Dropdown container
        this.dropdown = document.createElement('div');
        this.dropdown.id = `${baseId}-listbox`;
        this.dropdown.className = 'magma-combobox-dropdown';
        this.dropdown.setAttribute('role', 'listbox');
        this.dropdown.classList.add('d-none');
        this.wrapper.appendChild(this.dropdown);

        // Selected Multi-line Card Container
        this.selectedCard = document.createElement('div');
        this.selectedCard.className = 'magma-combobox-selected-card';
        this.selectedCard.classList.add('d-none');
        this.wrapper.appendChild(this.selectedCard);
    }

    /**
     * Renders autocomplete dropdown results using a DocumentFragment.
     *
     * @param {Array<Object>} items List of result items.
     * @param {Function|null} [customRenderer=null] Optional custom item template renderer.
     */
    render(items, customRenderer = null) {
        this.items = items;
        this.highlightedIndex = -1;
        this.dropdown.innerHTML = '';

        if (!items || items.length === 0) {
            this.dropdown.innerHTML = '<div class="magma-combobox-empty">No results found</div>';
            this.open();
            return;
        }

        const fragment = document.createDocumentFragment();

        items.forEach((item, index) => {
            let itemEl;

            if (typeof customRenderer === 'function') {
                itemEl = document.createElement('div');
                itemEl.className = 'magma-combobox-item';
                itemEl.innerHTML = customRenderer(item);
            } else {
                if (!this.constructor._defaultItemTemplate) {
                    this.constructor._defaultItemTemplate = document.createElement('template');
                    this.constructor._defaultItemTemplate.innerHTML = `
                        <div class="magma-combobox-item" role="option">
                            <div class="item-row-primary">
                                <span class="item-title" data-bind-text="title"></span>
                                <span class="item-badge" data-bind-text="badge" data-if="badge"></span>
                            </div>
                            <div class="item-row-secondary" data-if="hasSecondary">
                                <span class="item-specs" data-bind-text="specs" data-if="specs"></span>
                                <span class="item-price" data-bind-text="price | currency" data-if="price"></span>
                            </div>
                        </div>
                    `;
                }

                const itemData = {
                    title: item.name || item.title || item.label || '',
                    badge: item.badge || item.category || item.tag || '',
                    specs: item.specs || item.description || item.subtext || '',
                    price: item.price
                };
                itemData.hasSecondary = Boolean(itemData.specs || itemData.price);

                const renderedFragment = templateEngine.render(this.constructor._defaultItemTemplate, itemData);
                itemEl = renderedFragment.firstElementChild;
            }

            if (itemEl) {
                const optionId = `${this.dropdown.id}-option-${index}`;
                itemEl.id = optionId;
                if (!itemEl.hasAttribute('role')) {
                    itemEl.setAttribute('role', 'option');
                }
                itemEl.setAttribute('aria-selected', 'false');
                itemEl.setAttribute('data-index', String(index));
                itemEl.dataset.id = item.id !== undefined ? String(item.id) : '';
                itemEl.dataset.name = item.name || item.title || item.label || '';

                // Store raw item data attributes on dataset for extraction/propagation
                for (const [key, value] of Object.entries(item)) {
                    if (typeof value === 'string' || typeof value === 'number') {
                        itemEl.dataset[key] = String(value);
                    }
                }

                fragment.appendChild(itemEl);
            }
        });

        this.dropdown.appendChild(fragment);
        this.open();
    }

    /**
     * Renders the selected multi-line card and transitions input to hidden.
     *
     * @param {Object} item The selected item record.
     * @param {Function|null} [cardFormatter=null] Custom card formatter function.
     */
    renderSelectedCard(item, cardFormatter = null) {
        if (!item) {
            this.clearSelectedCard();
            return;
        }

        this.selectedCard.innerHTML = '';

        if (typeof cardFormatter === 'function') {
            this.selectedCard.innerHTML = cardFormatter(item);
        } else {
            if (!this.constructor._defaultSelectedTemplate) {
                this.constructor._defaultSelectedTemplate = document.createElement('template');
                this.constructor._defaultSelectedTemplate.innerHTML = `
                    <div class="selected-card-row1">
                        <strong class="selected-card-title" data-bind-text="title"></strong>
                        <span class="selected-card-badge" data-bind-text="badge" data-if="badge"></span>
                        <button type="button" class="selected-card-clear-btn" title="Change selection" aria-label="Clear selection">&times;</button>
                    </div>
                    <div class="selected-card-row2" data-bind-text="secondaryText" data-if="secondaryText"></div>
                `;
            }

            const itemData = {
                title: item.name || item.title || item.label || '',
                badge: item.badge || item.category || '',
                secondaryText: [item.description, item.specs, item.price ? `Price: ${item.price}` : null].filter(Boolean).join(' • ')
            };

            const fragment = templateEngine.render(this.constructor._defaultSelectedTemplate, itemData);
            this.selectedCard.appendChild(fragment);
        }

        // Hide input, show card
        this.input.classList.add('d-none');
        this.selectedCard.classList.remove('d-none');
        this.close();
    }

    /**
     * Clears the selected card and switches back to input search mode.
     */
    clearSelectedCard() {
        this.selectedCard.innerHTML = '';
        this.selectedCard.classList.add('d-none');
        this.input.classList.remove('d-none');
        this.input.value = '';
        this.input.focus();
    }

    open() {
        this.dropdown.classList.remove('d-none');
        this.input.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.dropdown.classList.add('d-none');
        this.input.setAttribute('aria-expanded', 'false');
        this.highlightedIndex = -1;
    }

    isOpen() {
        return !this.dropdown.classList.contains('d-none');
    }

    clear() {
        this.dropdown.innerHTML = '';
        this.close();
    }

    setInputValue(value) {
        this.input.value = value;
    }

    highlightNext() {
        if (this.items.length === 0) return;
        this.highlightedIndex = (this.highlightedIndex + 1) % this.items.length;
        this._updateHighlight();
    }

    highlightPrev() {
        if (this.items.length === 0) return;
        this.highlightedIndex = (this.highlightedIndex - 1 + this.items.length) % this.items.length;
        this._updateHighlight();
    }

    _updateHighlight() {
        const optionEls = this.dropdown.querySelectorAll('.magma-combobox-item');
        let activeId = null;
        optionEls.forEach((el, idx) => {
            const isHighlighted = idx === this.highlightedIndex;
            el.classList.toggle('is-highlighted', isHighlighted);
            el.setAttribute('aria-selected', isHighlighted ? 'true' : 'false');
            if (isHighlighted) {
                el.scrollIntoView({ block: 'nearest' });
                activeId = el.id;
            }
        });
        if (activeId) {
            this.input.setAttribute('aria-activedescendant', activeId);
        } else {
            this.input.removeAttribute('aria-activedescendant');
        }
    }

    getHighlightedItem() {
        if (this.highlightedIndex >= 0 && this.highlightedIndex < this.items.length) {
            return this.items[this.highlightedIndex];
        }
        return null;
    }

    /**
     * Binds input typing with debouncing.
     */
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

    /**
     * Binds selection click and clear-card interactions.
     */
    onSelect(callback) {
        this.selectHandler = (e) => {
            const itemEl = e.target.closest('.magma-combobox-item');
            if (itemEl) {
                const index = parseInt(itemEl.getAttribute('data-index') || '-1', 10);
                const itemData = index >= 0 && this.items[index] ? this.items[index] : { ...itemEl.dataset };
                callback(itemData);
            }
        };
        this.dropdown.addEventListener('click', this.selectHandler);

        // Click on selected card clear button or card body to edit
        this.cardClickHandler = (e) => {
            if (e.target.closest('.selected-card-clear-btn') || e.target.closest('.magma-combobox-selected-card')) {
                this.clearSelectedCard();
            }
        };
        this.selectedCard.addEventListener('click', this.cardClickHandler);
    }

    /**
     * Destroys DOM nodes and event listeners.
     */
    destroy() {
        if (this.inputHandler) {
            this.input.removeEventListener('input', this.inputHandler);
        }
        if (this.selectHandler) {
            this.dropdown.removeEventListener('click', this.selectHandler);
        }
        if (this.cardClickHandler) {
            this.selectedCard.removeEventListener('click', this.cardClickHandler);
        }
        if (this.wrapper && this.wrapper.parentNode) {
            this.wrapper.parentNode.insertBefore(this.input, this.wrapper);
            this.wrapper.parentNode.removeChild(this.wrapper);
        }
    }
}
