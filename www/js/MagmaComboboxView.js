'use strict';

import { templateEngine } from './TemplateEngine.js';

/**
 * Title: Magma Combobox View & Renderer
 *
 * Purpose:
 * - Handles the visual rendering lifecycle for the Combobox component.
 * - Dynamically constructs dropdown lists, handles empty states, and builds multi-line selected cards.
 * - Manages accessibility (a11y) attributes, active descendant tracking, and scroll-into-view behavior.
 *
 * Why / Why this design:
 * - Single Responsibility Principle (SRP): By decoupling the View logic from the MagmaCombobox Controller,
 *   we ensure that DOM manipulation, accessibility ARIA tag management, and HTML construction remain
 *   isolated from network fetch logic and event debouncing.
 * - Safer DOM construction: Uses `document.createElement` instead of `innerHTML` strings where possible to prevent XSS.
 *
 * Teaching notes:
 * - The `ComboboxDefaultRenderer` can be bypassed entirely by passing a `customRenderer` function
 *   in the MagmaCombobox options, allowing complete visual flexibility.
 */
export class ComboboxDefaultRenderer {
    constructor(options = {}) {
        this.itemTemplateId = options.itemTemplateId || null;
        this.selectedTemplateId = options.selectedTemplateId || null;
        this.emptyMessage = options.emptyMessage || 'No results found';
    }

    renderList(items, dropdown, customRenderer = null) {
        dropdown.innerHTML = '';
        if (!items || items.length === 0) {
            this._renderEmptyMessage(dropdown);
            return;
        }
        
        const fragment = document.createDocumentFragment();
        items.forEach((item, index) => {
            const itemEl = this._createItemElement(item, index, customRenderer, dropdown.id);
            if (itemEl) fragment.appendChild(itemEl);
        });
        dropdown.appendChild(fragment);
    }

    _renderEmptyMessage(dropdown) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'magma-combobox-empty';
        emptyDiv.textContent = this.emptyMessage;
        dropdown.appendChild(emptyDiv);
    }

    _createItemElement(item, index, customRenderer, dropdownId) {
        let itemEl;
        if (typeof customRenderer === 'function') {
            itemEl = document.createElement('div');
            itemEl.className = 'magma-combobox-item';
            itemEl.innerHTML = customRenderer(item);
        } else {
            const tpl = this._resolveItemTemplate();
            const itemData = this._formatItemData(item);
            const renderedFragment = templateEngine.render(tpl, itemData);
            itemEl = renderedFragment.firstElementChild;
        }
        if (itemEl) {
            this._applyItemAttributes(itemEl, item, index, dropdownId);
        }
        return itemEl;
    }

    _resolveItemTemplate() {
        let tpl = this.itemTemplateId ? document.querySelector(this.itemTemplateId) : null;
        if (!tpl && !this._defaultItemTemplate) {
            this._defaultItemTemplate = document.createElement('template');
            
            const wrapper = document.createElement('div');
            wrapper.className = 'magma-combobox-item';
            wrapper.setAttribute('role', 'option');
            
            const row1 = document.createElement('div');
            row1.className = 'magma-combobox__item-row-primary';
            const titleSpan = document.createElement('span');
            titleSpan.className = 'magma-combobox__item-title';
            titleSpan.setAttribute('data-bind-text', 'title');
            const badgeSpan = document.createElement('span');
            badgeSpan.className = 'magma-combobox__item-badge';
            badgeSpan.setAttribute('data-bind-text', 'badge');
            badgeSpan.setAttribute('data-if', 'badge');
            row1.appendChild(titleSpan);
            row1.appendChild(badgeSpan);
            
            const row2 = document.createElement('div');
            row2.className = 'magma-combobox__item-row-secondary';
            row2.setAttribute('data-if', 'hasSecondary');
            const specsSpan = document.createElement('span');
            specsSpan.className = 'magma-combobox__item-specs';
            specsSpan.setAttribute('data-bind-text', 'specs');
            specsSpan.setAttribute('data-if', 'specs');
            const priceSpan = document.createElement('span');
            priceSpan.className = 'magma-combobox__item-price';
            priceSpan.setAttribute('data-bind-text', 'price | currency');
            priceSpan.setAttribute('data-if', 'price');
            row2.appendChild(specsSpan);
            row2.appendChild(priceSpan);
            
            wrapper.appendChild(row1);
            wrapper.appendChild(row2);
            
            this._defaultItemTemplate.content.appendChild(wrapper);
        }
        return tpl || this._defaultItemTemplate;
    }

    _formatItemData(item) {
        const itemData = {
            title: item.name || item.title || item.label || '',
            badge: item.badge || item.category || item.tag || '',
            specs: item.specs || item.description || item.subtext || '',
            price: item.price
        };
        itemData.hasSecondary = Boolean(itemData.specs || itemData.price);
        return itemData;
    }

    _applyItemAttributes(itemEl, item, index, dropdownId) {
        const optionId = `${dropdownId}-option-${index}`;
        itemEl.id = optionId;
        if (!itemEl.hasAttribute('role')) {
            itemEl.setAttribute('role', 'option');
        }
        itemEl.setAttribute('aria-selected', 'false');
        itemEl.setAttribute('data-index', String(index));
        itemEl.dataset.id = item.id !== undefined ? String(item.id) : '';
        itemEl.dataset.name = item.name || item.title || item.label || '';

        for (const [key, value] of Object.entries(item)) {
            if (typeof value === 'string' || typeof value === 'number') {
                itemEl.dataset[key] = String(value);
            }
        }
    }

    renderSelectedCard(item, selectedCardElement, cardFormatter = null) {
        selectedCardElement.innerHTML = '';
        if (typeof cardFormatter === 'function') {
            selectedCardElement.innerHTML = cardFormatter(item);
        } else {
            let tpl = this.selectedTemplateId ? document.querySelector(this.selectedTemplateId) : null;
            if (!tpl && !this._defaultSelectedTemplate) {
                this._defaultSelectedTemplate = document.createElement('template');
                
                const row1 = document.createElement('div');
                row1.className = 'magma-combobox__selected-card-row1';
                const title = document.createElement('strong');
                title.className = 'magma-combobox__selected-card-title';
                title.setAttribute('data-bind-text', 'title');
                const badge = document.createElement('span');
                badge.className = 'magma-combobox__selected-card-badge';
                badge.setAttribute('data-bind-text', 'badge');
                badge.setAttribute('data-if', 'badge');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'magma-combobox__selected-card-clear-btn';
                btn.title = 'Change selection';
                btn.setAttribute('aria-label', 'Clear selection');
                btn.textContent = '×';
                row1.appendChild(title);
                row1.appendChild(badge);
                row1.appendChild(btn);
                
                const row2 = document.createElement('div');
                row2.className = 'magma-combobox__selected-card-row2';
                row2.setAttribute('data-bind-text', 'secondaryText');
                row2.setAttribute('data-if', 'secondaryText');
                
                this._defaultSelectedTemplate.content.appendChild(row1);
                this._defaultSelectedTemplate.content.appendChild(row2);
            }
            
            tpl = tpl || this._defaultSelectedTemplate;

            const itemData = {
                title: item.name || item.title || item.label || '',
                badge: item.badge || item.category || '',
                secondaryText: [item.description, item.specs, item.price ? `Price: ${item.price}` : null].filter(Boolean).join(' • ')
            };

            const fragment = templateEngine.render(tpl, itemData);
            selectedCardElement.appendChild(fragment);
        }
    }
}

export class ComboboxAccessibilityManager {
    constructor(inputElement) {
        this.input = inputElement;
    }

    updateActiveDescendant(highlightedIndex, dropdown) {
        const optionEls = dropdown.querySelectorAll('.magma-combobox-item');
        let activeId = null;
        optionEls.forEach((el, idx) => {
            const isHighlighted = idx === highlightedIndex;
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
}

export class ComboboxView {
    constructor(inputElement, options = {}) {
        this.input = inputElement;
        this.options = options;
        this.highlightedIndex = -1;
        this.items = [];
        this.renderer = options.renderer || new ComboboxDefaultRenderer();
        this.a11y = new ComboboxAccessibilityManager(this.input);

        this._initDOM();
    }

    _initDOM() {
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

        this.dropdown = document.createElement('div');
        this.dropdown.id = `${baseId}-listbox`;
        this.dropdown.className = 'magma-combobox-dropdown';
        this.dropdown.setAttribute('role', 'listbox');
        this.dropdown.classList.add('d-none');
        this.wrapper.appendChild(this.dropdown);

        this.selectedCard = document.createElement('div');
        this.selectedCard.className = 'magma-combobox-selected-card';
        this.selectedCard.classList.add('d-none');
        this.wrapper.appendChild(this.selectedCard);
    }

    render(items, customRenderer = null) {
        this.items = items;
        this.highlightedIndex = -1;
        this.renderer.renderList(items, this.dropdown, customRenderer);
        this.open();
    }

    renderSelectedCard(item, cardFormatter = null) {
        if (!item) {
            this.clearSelectedCard();
            return;
        }

        this.renderer.renderSelectedCard(item, this.selectedCard, cardFormatter);
        
        this.input.classList.add('d-none');
        this.selectedCard.classList.remove('d-none');
        this.close();
    }

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
        this.a11y.updateActiveDescendant(this.highlightedIndex, this.dropdown);
    }

    getHighlightedItem() {
        if (this.highlightedIndex >= 0 && this.highlightedIndex < this.items.length) {
            return this.items[this.highlightedIndex];
        }
        return null;
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
            const itemEl = e.target.closest('.magma-combobox-item');
            if (itemEl) {
                const index = parseInt(itemEl.getAttribute('data-index') || '-1', 10);
                const itemData = index >= 0 && this.items[index] ? this.items[index] : { ...itemEl.dataset };
                callback(itemData);
            }
        };
        this.dropdown.addEventListener('click', this.selectHandler);

        this.cardClickHandler = (e) => {
            if (e.target.closest('.magma-combobox__selected-card-clear-btn') || e.target.closest('.magma-combobox-selected-card')) {
                this.clearSelectedCard();
            }
        };
        this.selectedCard.addEventListener('click', this.cardClickHandler);
    }

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
