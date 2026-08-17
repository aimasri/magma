'use strict';

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

        this.input.classList.add('magma-combobox-input');
        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.setAttribute('autocomplete', 'off');

        // Dropdown container
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'magma-combobox-dropdown';
        this.dropdown.setAttribute('role', 'listbox');
        this.dropdown.style.display = 'none';
        this.wrapper.appendChild(this.dropdown);

        // Selected Multi-line Card Container
        this.selectedCard = document.createElement('div');
        this.selectedCard.className = 'magma-combobox-selected-card';
        this.selectedCard.style.display = 'none';
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
            const itemEl = document.createElement('div');
            itemEl.className = 'magma-combobox-item';
            itemEl.setAttribute('role', 'option');
            itemEl.setAttribute('data-index', String(index));
            itemEl.dataset.id = item.id !== undefined ? String(item.id) : '';
            itemEl.dataset.name = item.name || item.title || item.label || '';

            // Store raw item data attributes on dataset for extraction/propagation
            for (const [key, value] of Object.entries(item)) {
                if (typeof value === 'string' || typeof value === 'number') {
                    itemEl.dataset[key] = String(value);
                }
            }

            if (typeof customRenderer === 'function') {
                itemEl.innerHTML = customRenderer(item);
            } else {
                // Default Multi-Line Layout: Row 1 (Title + Badge), Row 2 (Specs)
                const row1 = document.createElement('div');
                row1.className = 'item-row-primary';

                const title = document.createElement('span');
                title.className = 'item-title';
                title.textContent = item.name || item.title || item.label || '';
                row1.appendChild(title);

                if (item.badge || item.category || item.tag) {
                    const badge = document.createElement('span');
                    badge.className = 'item-badge';
                    badge.textContent = item.badge || item.category || item.tag;
                    row1.appendChild(badge);
                }

                itemEl.appendChild(row1);

                if (item.description || item.specs || item.subtext || item.price) {
                    const row2 = document.createElement('div');
                    row2.className = 'item-row-secondary';

                    const specs = document.createElement('span');
                    specs.className = 'item-specs';
                    specs.textContent = item.specs || item.description || item.subtext || '';
                    row2.appendChild(specs);

                    if (item.price) {
                        const price = document.createElement('span');
                        price.className = 'item-price';
                        price.textContent = typeof item.price === 'number' ? `$${item.price.toFixed(2)}` : String(item.price);
                        row2.appendChild(price);
                    }

                    itemEl.appendChild(row2);
                }
            }

            fragment.appendChild(itemEl);
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
            // Row 1: Title + Badge
            const row1 = document.createElement('div');
            row1.className = 'selected-card-row1';

            const title = document.createElement('strong');
            title.className = 'selected-card-title';
            title.textContent = item.name || item.title || item.label || '';
            row1.appendChild(title);

            if (item.badge || item.category) {
                const badge = document.createElement('span');
                badge.className = 'selected-card-badge';
                badge.textContent = item.badge || item.category;
                row1.appendChild(badge);
            }

            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'selected-card-clear-btn';
            clearBtn.title = 'Change selection';
            clearBtn.setAttribute('aria-label', 'Clear selection');
            clearBtn.innerHTML = '&times;';
            row1.appendChild(clearBtn);

            this.selectedCard.appendChild(row1);

            // Row 2: Specs / Description / Price
            if (item.specs || item.description || item.price) {
                const row2 = document.createElement('div');
                row2.className = 'selected-card-row2';
                row2.textContent = [item.description, item.specs, item.price ? `Price: ${item.price}` : null]
                    .filter(Boolean)
                    .join(' • ');
                this.selectedCard.appendChild(row2);
            }
        }

        // Hide input, show card
        this.input.style.display = 'none';
        this.selectedCard.style.display = 'block';
        this.close();
    }

    /**
     * Clears the selected card and switches back to input search mode.
     */
    clearSelectedCard() {
        this.selectedCard.innerHTML = '';
        this.selectedCard.style.display = 'none';
        this.input.style.display = '';
        this.input.value = '';
        this.input.focus();
    }

    open() {
        this.dropdown.style.display = 'block';
        this.input.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.dropdown.style.display = 'none';
        this.input.setAttribute('aria-expanded', 'false');
        this.highlightedIndex = -1;
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
        optionEls.forEach((el, idx) => {
            const isHighlighted = idx === this.highlightedIndex;
            el.classList.toggle('is-highlighted', isHighlighted);
            if (isHighlighted) {
                el.scrollIntoView({ block: 'nearest' });
            }
        });
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
