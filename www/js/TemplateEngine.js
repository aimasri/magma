/**
 * Title: Client-Side Safe Template Engine (<template> Driven)
 *
 * Purpose:
 * - Strict client-side HTML `<template>` cloning and interpolation engine.
 * - Permanently eliminates `innerHTML` XSS injection vectors and ensures strict CSP (Content Security Policy) compliance.
 *
 * Why / Why this design:
 * - Security & XSS Elimination: Using `element.innerHTML = \`<div>\${userInput}</div>\`` is the #1 source of
 *   stored and DOM-based XSS. This engine uses native `<template>` DOM cloning, manipulating text nodes
 *   exclusively via `Node.textContent` and attributes via `Element.setAttribute()`.
 * - High Performance: Browsers parse `<template>` contents into an inactive `DocumentFragment` once.
 *   Subsequent clones with `cloneNode(true)` run in native C++ code without re-parsing HTML strings.
 * - Logic-less Declarative Bindings: Enforces separation between UI layout and business logic.
 *
 * Teaching notes:
 * - Define a template in HTML:
 *   <template id="menu-card-tpl">
 *     <div class="card">
 *       <h3 data-bind-text="name"></h3>
 *       <span data-bind-text="price"></span>
 *       <button data-bind-attr-data-id="id" data-action="menu:select">Select</button>
 *     </div>
 *   </template>
 * - Render it: `const fragment = templateEngine.render('menu-card-tpl', itemData);`
 * - Append: `container.appendChild(fragment);`
 */
export class TemplateEngine {
    constructor() {
        /** @type {Map<string, HTMLTemplateElement>} */
        this._templateCache = new Map();
        /** @type {Map<string, Function>} */
        this._helpers = new Map();

        // Built-in formatting helpers
        this.registerHelper('currency', (val) => {
            const num = parseFloat(val) || 0;
            return '$' + num.toFixed(2);
        });
        this.registerHelper('uppercase', (val) => String(val || '').toUpperCase());
        this.registerHelper('lowercase', (val) => String(val || '').toLowerCase());
    }

    /**
     * Registers a custom formatting helper function.
     *
     * @param {string} name Helper identifier.
     * @param {Function} fn Function receiving `(value, ...args) => formattedValue`.
     * @returns {this}
     */
    registerHelper(name, fn) {
        if (typeof fn !== 'function') {
            throw new TypeError(`Helper [${name}] must be a function.`);
        }
        this._helpers.set(name, fn);
        return this;
    }

    /**
     * Renders a `<template>` element with data into a populated DocumentFragment.
     *
     * Execution Flow:
     * 1. Resolve target `<template>` element from DOM or cache.
     * 2. Clone the template's content fragment via `cloneNode(true)`.
     * 3. Process conditional directives (`data-if`, `data-unless`).
     * 4. Process loop directives (`<template data-loop="...">`).
     * 5. Process text bindings (`data-bind-text`, `data-bind`).
     * 6. Process attribute bindings (`data-bind-attr-*`).
     * 7. Process class toggle bindings (`data-bind-class-*`).
     * 8. Return the populated DocumentFragment ready for immediate DOM insertion.
     *
     * @param {string|HTMLTemplateElement} templateIdOrElement Template ID string or element.
     * @param {Object} [data={}] Data dictionary for interpolation.
     * @returns {DocumentFragment} Populated fragment.
     */
    render(templateIdOrElement, data = {}) {
        const template = this._resolveTemplate(templateIdOrElement);
        if (!template) {
            throw new Error(`TemplateEngine: Unable to locate template [${templateIdOrElement}].`);
        }

        const clone = template.content.cloneNode(true);
        this._interpolateFragment(clone, data);

        return clone;
    }

    /**
     * Renders a template and returns its serialized HTML string representation.
     *
     * @param {string|HTMLTemplateElement} templateIdOrElement
     * @param {Object} [data={}]
     * @returns {string} Safe compiled HTML string.
     */
    renderToString(templateIdOrElement, data = {}) {
        const fragment = this.render(templateIdOrElement, data);
        const container = document.createElement('div');
        container.appendChild(fragment);
        return container.innerHTML;
    }

    /**
     * Recursively interpolates bindings on a DocumentFragment or Element tree.
     *
     * @param {DocumentFragment|Element} root
     * @param {Object} data
     * @private
     */
    _interpolateFragment(root, data) {
        // 1. Process Conditionals first (data-if, data-unless)
        const conditionals = Array.from(root.querySelectorAll('[data-if], [data-unless]'));
        for (const el of conditionals) {
            if (el.hasAttribute('data-if')) {
                const key = el.getAttribute('data-if');
                const val = this._resolveValue(key, data);
                if (!val) {
                    el.remove();
                    continue;
                }
                el.removeAttribute('data-if');
            }

            if (el.hasAttribute('data-unless')) {
                const key = el.getAttribute('data-unless');
                const val = this._resolveValue(key, data);
                if (val) {
                    el.remove();
                    continue;
                }
                el.removeAttribute('data-unless');
            }
        }

        // 2. Process Loops (<template data-loop="items"> or <div data-loop="items">)
        const loops = Array.from(root.querySelectorAll('[data-loop]'));
        for (const loopEl of loops) {
            const key = loopEl.getAttribute('data-loop');
            const items = this._resolveValue(key, data);
            loopEl.removeAttribute('data-loop');

            if (Array.isArray(items)) {
                const parent = loopEl.parentNode;
                if (!parent) continue;

                const loopFragment = document.createDocumentFragment();

                if (loopEl.tagName === 'TEMPLATE') {
                    for (let index = 0; index < items.length; index++) {
                        const item = items[index];
                        const itemData = typeof item === 'object' && item !== null
                            ? { ...item, '@index': index, '@first': index === 0, '@last': index === items.length - 1 }
                            : { value: item, '@index': index, '@first': index === 0, '@last': index === items.length - 1 };

                        const childClone = loopEl.content.cloneNode(true);
                        this._interpolateFragment(childClone, itemData);
                        loopFragment.appendChild(childClone);
                    }
                    parent.replaceChild(loopFragment, loopEl);
                } else {
                    // Clone the element itself for each array item
                    for (let index = 0; index < items.length; index++) {
                        const item = items[index];
                        const itemData = typeof item === 'object' && item !== null
                            ? { ...item, '@index': index, '@first': index === 0, '@last': index === items.length - 1 }
                            : { value: item, '@index': index, '@first': index === 0, '@last': index === items.length - 1 };

                        const clonedNode = loopEl.cloneNode(true);
                        this._interpolateFragment(clonedNode, itemData);
                        loopFragment.appendChild(clonedNode);
                    }
                    parent.replaceChild(loopFragment, loopEl);
                }
            } else {
                loopEl.remove();
            }
        }

        // 3. Process Text Bindings (data-bind, data-bind-text)
        const textBindings = Array.from(root.querySelectorAll('[data-bind], [data-bind-text]'));
        for (const el of textBindings) {
            const key = el.getAttribute('data-bind-text') || el.getAttribute('data-bind');
            const val = this._resolveValue(key, data);
            el.textContent = val !== null && val !== undefined ? String(val) : '';
            el.removeAttribute('data-bind');
            el.removeAttribute('data-bind-text');
        }

        // 4. Process Attribute Bindings (data-bind-attr-*)
        const allElements = Array.from(root.querySelectorAll('*'));
        for (const el of allElements) {
            const attributesToRemove = [];
            for (let i = 0; i < el.attributes.length; i++) {
                const attr = el.attributes[i];
                if (attr.name.startsWith('data-bind-attr-')) {
                    const targetAttrName = attr.name.replace('data-bind-attr-', '');
                    const val = this._resolveValue(attr.value, data);

                    if (val === false || val === null || val === undefined) {
                        el.removeAttribute(targetAttrName);
                    } else if (val === true) {
                        el.setAttribute(targetAttrName, '');
                    } else {
                        el.setAttribute(targetAttrName, String(val));
                    }
                    attributesToRemove.push(attr.name);
                } else if (attr.name.startsWith('data-bind-class-')) {
                    const targetClassName = attr.name.replace('data-bind-class-', '');
                    const isTruthy = Boolean(this._resolveValue(attr.value, data));
                    el.classList.toggle(targetClassName, isTruthy);
                    attributesToRemove.push(attr.name);
                }
            }

            for (const name of attributesToRemove) {
                el.removeAttribute(name);
            }
        }
    }

    /**
     * Resolves dot-notated keys and helper pipes from data objects.
     * Example: 'user.profile.name' or 'amount | currency'
     *
     * @param {string} keyExpr
     * @param {Object} data
     * @returns {*}
     * @private
     */
    _resolveValue(keyExpr, data) {
        if (!keyExpr) return '';

        // Check for helper pipe (e.g., 'price | currency')
        let [key, helperName] = keyExpr.split('|').map(s => s.trim());

        let val = data;
        const parts = key.split('.');

        for (const part of parts) {
            if (val === null || val === undefined) {
                val = '';
                break;
            }
            val = val[part];
        }

        if (helperName && this._helpers.has(helperName)) {
            const helper = this._helpers.get(helperName);
            return helper(val);
        }

        return val;
    }

    /**
     * Resolves a template element by ID string or reference.
     *
     * @param {string|HTMLTemplateElement} target
     * @returns {HTMLTemplateElement|null}
     * @private
     */
    _resolveTemplate(target) {
        if (target instanceof HTMLTemplateElement) {
            return target;
        }

        if (typeof target === 'string') {
            const id = target.startsWith('#') ? target.slice(1) : target;
            if (this._templateCache.has(id)) {
                return this._templateCache.get(id);
            }

            const el = document.getElementById(id) || document.querySelector(`template[id="${id}"]`);
            if (el instanceof HTMLTemplateElement) {
                this._templateCache.set(id, el);
                return el;
            }
        }

        return null;
    }
}

/** Default singleton TemplateEngine instance. */
export const templateEngine = new TemplateEngine();
