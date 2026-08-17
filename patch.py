import sys

# Patch ObservableStore.js
file1 = '/home/ahmed/projects/Magma/www/js/ObservableStore.js'
with open(file1, 'r') as f:
    content1 = f.read()

content1 = content1.replace('Object.freeze({ ...initialState })', 'this._deepFreeze({ ...initialState })')
content1 = content1.replace('Object.freeze({ ...prevState, ...partial })', 'this._deepFreeze({ ...prevState, ...partial })')
content1 = content1.replace('this._state = Object.freeze({});\n    }', '''this._state = this._deepFreeze({});
    }

    /**
     * Recursively freezes an object to ensure deep immutability.
     *
     * @param {Object} obj
     * @returns {Object}
     * @private
     */
    _deepFreeze(obj) {
        if (obj === null || typeof obj !== 'object') {
            return obj;
        }

        Object.freeze(obj);

        for (const key of Object.keys(obj)) {
            const prop = obj[key];
            if (prop !== null && (typeof prop === 'object' || typeof prop === 'function') && !Object.isFrozen(prop)) {
                this._deepFreeze(prop);
            }
        }

        return obj;
    }''')

with open(file1, 'w') as f:
    f.write(content1)

# Patch MagmaComboboxView.js
file2 = '/home/ahmed/projects/Magma/www/js/MagmaComboboxView.js'
with open(file2, 'r') as f:
    content2 = f.read()

content2 = content2.replace('''        this.input.classList.add('magma-combobox-input');
        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-autocomplete', 'list');
        this.input.setAttribute('autocomplete', 'off');

        // Dropdown container
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'magma-combobox-dropdown';''', '''        const baseId = this.input.id || `magma-combo-${Math.random().toString(36).substr(2, 9)}`;
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
        this.dropdown.className = 'magma-combobox-dropdown';''')

content2 = content2.replace('''                if (!this._defaultItemTemplate) {
                    this._defaultItemTemplate = document.createElement('template');
                    this._defaultItemTemplate.innerHTML = `''', '''                if (!this.constructor._defaultItemTemplate) {
                    this.constructor._defaultItemTemplate = document.createElement('template');
                    this.constructor._defaultItemTemplate.innerHTML = `''')

content2 = content2.replace('''                const renderedFragment = templateEngine.render(this._defaultItemTemplate, itemData);''', '''                const renderedFragment = templateEngine.render(this.constructor._defaultItemTemplate, itemData);''')

content2 = content2.replace('''            if (itemEl) {
                if (!itemEl.hasAttribute('role')) {
                    itemEl.setAttribute('role', 'option');
                }
                itemEl.setAttribute('data-index', String(index));''', '''            if (itemEl) {
                const optionId = `${this.dropdown.id}-option-${index}`;
                itemEl.id = optionId;
                if (!itemEl.hasAttribute('role')) {
                    itemEl.setAttribute('role', 'option');
                }
                itemEl.setAttribute('aria-selected', 'false');
                itemEl.setAttribute('data-index', String(index));''')

content2 = content2.replace('''            if (!this._defaultSelectedTemplate) {
                this._defaultSelectedTemplate = document.createElement('template');
                this._defaultSelectedTemplate.innerHTML = `''', '''            if (!this.constructor._defaultSelectedTemplate) {
                this.constructor._defaultSelectedTemplate = document.createElement('template');
                this.constructor._defaultSelectedTemplate.innerHTML = `''')

content2 = content2.replace('''            const fragment = templateEngine.render(this._defaultSelectedTemplate, itemData);''', '''            const fragment = templateEngine.render(this.constructor._defaultSelectedTemplate, itemData);''')

content2 = content2.replace('''    _updateHighlight() {
        const optionEls = this.dropdown.querySelectorAll('.magma-combobox-item');
        optionEls.forEach((el, idx) => {
            const isHighlighted = idx === this.highlightedIndex;
            el.classList.toggle('is-highlighted', isHighlighted);
            if (isHighlighted) {
                el.scrollIntoView({ block: 'nearest' });
            }
        });
    }''', '''    _updateHighlight() {
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
    }''')

with open(file2, 'w') as f:
    f.write(content2)

print("Patch applied successfully.")
