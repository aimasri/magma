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
import { TemplateHelperRegistry } from './TemplateHelperRegistry.js';
import { ConditionalProcessor, LoopProcessor, TextBindingProcessor, AttributeBindingProcessor } from './TemplateProcessors.js';

export class TemplateEngine {
    constructor(helperRegistry = null, processors = null) {
        /** @type {Map<string, HTMLTemplateElement>} */
        this._templateCache = new Map();
        /** @type {TemplateHelperRegistry} */
        this._helpers = helperRegistry || new TemplateHelperRegistry();
        
        this.processors = processors || [
            new ConditionalProcessor(this),
            new LoopProcessor(this),
            new TextBindingProcessor(this),
            new AttributeBindingProcessor(this)
        ];
    }

    /**
     * Registers a custom formatting helper function.
     *
     * @param {string} name Helper identifier.
     * @param {Function} fn Function receiving `(value, ...args) => formattedValue`.
     * @returns {this}
     */
    registerHelper(name, fn) {
        this._helpers.register(name, fn);
        return this;
    }

    /**
     * Renders a `<template>` element with data into a populated DocumentFragment.
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
     */
    renderToString(templateIdOrElement, data = {}) {
        const fragment = this.render(templateIdOrElement, data);
        const container = document.createElement('div');
        container.appendChild(fragment);
        return container.innerHTML;
    }

    _interpolateFragment(root, data) {
        // Run processors in order
        this.processors.forEach(processor => processor.process(root, data));
    }

    /**
     * Resolves dot-notated keys and helper pipes from data objects.
     */
    _resolveValue(keyExpr, data) {
        if (!keyExpr) return '';

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
