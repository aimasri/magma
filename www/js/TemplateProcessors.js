/**
 * Title: Magma Template Processors
 *
 * Purpose:
 * - Provides modular rendering processors (Conditionals, Loops, Text Binding, Attribute Binding)
 *   that execute sequentially to parse and hydrate DOM nodes.
 *
 * Why / Why this design:
 * - Strategy Pattern: By extracting the parsing logic from the `TemplateEngine` into discrete Processor
 *   classes, we adhere to the Open/Closed Principle (OCP). New parsing abilities (e.g., event bindings)
 *   can be added simply by injecting a new Processor into the engine, without modifying existing code.
 *
 * Teaching notes:
 * - Ensure processors are registered in the correct order in `TemplateEngine`. For example, Conditionals
 *   must be processed before Loops to prevent unnecessary array iteration on hidden blocks.
 */
export class ConditionalProcessor {
    constructor(engine) { this.engine = engine; }
    process(root, data) {
        const conditionals = Array.from(root.querySelectorAll('[data-if], [data-unless]'));
        for (const el of conditionals) {
            const ifKey = el.getAttribute('data-if');
            const unlessKey = el.getAttribute('data-unless');

            let shouldKeep = true;
            if (ifKey !== null && !this.engine._resolveValue(ifKey, data)) {
                shouldKeep = false;
            } else if (unlessKey !== null && this.engine._resolveValue(unlessKey, data)) {
                shouldKeep = false;
            }

            if (!shouldKeep) {
                el.remove();
            } else {
                el.removeAttribute('data-if');
                el.removeAttribute('data-unless');
            }
        }
    }
}

export class LoopProcessor {
    constructor(engine) { this.engine = engine; }
    process(root, data) {
        const loops = Array.from(root.querySelectorAll('[data-loop]'));
        const detached = [];
        for (const loopEl of loops) {
            if (!root.contains(loopEl)) continue;
            const placeholder = document.createComment('loop-placeholder');
            loopEl.parentNode.replaceChild(placeholder, loopEl);
            detached.push({ el: loopEl, placeholder });
        }

        // Loop processor delays its execution until other processors finish on the outer DOM
        // Since we are running in sequence, we handle this by processing detached loops now
        for (const { el, placeholder } of detached) {
            placeholder.parentNode.replaceChild(el, placeholder);
            const key = el.getAttribute('data-loop');
            const items = this.engine._resolveValue(key, data);
            el.removeAttribute('data-loop');

            if (Array.isArray(items)) {
                const parent = el.parentNode;
                if (!parent) continue;

                const loopFragment = document.createDocumentFragment();
                const isTemplate = el.tagName === 'TEMPLATE';
                const nodeToClone = isTemplate ? el.content : el;

                for (let index = 0; index < items.length; index++) {
                    const item = items[index];
                    const itemData = typeof item === 'object' && item !== null
                        ? { ...item, '@index': index, '@first': index === 0, '@last': index === items.length - 1 }
                        : { value: item, '@index': index, '@first': index === 0, '@last': index === items.length - 1 };

                    const clonedNode = nodeToClone.cloneNode(true);
                    this.engine._interpolateFragment(clonedNode, itemData);
                    loopFragment.appendChild(clonedNode);
                }
                parent.replaceChild(loopFragment, el);
            } else {
                el.remove();
            }
        }
    }
}

export class TextBindingProcessor {
    constructor(engine) { this.engine = engine; }
    process(root, data) {
        const textBindings = Array.from(root.querySelectorAll('[data-bind], [data-bind-text]'));
        for (const el of textBindings) {
            // Skip processing if element is inside an unprocessed loop
            if (el.closest('[data-loop]')) continue;
            
            const key = el.getAttribute('data-bind-text') || el.getAttribute('data-bind');
            const val = this.engine._resolveValue(key, data);
            el.textContent = val !== null && val !== undefined ? String(val) : '';
            el.removeAttribute('data-bind');
            el.removeAttribute('data-bind-text');
        }
    }
}

export class AttributeBindingProcessor {
    constructor(engine) { this.engine = engine; }
    process(root, data) {
        const allElements = Array.from(root.querySelectorAll('*'));
        for (const el of allElements) {
            // Skip processing if element is inside an unprocessed loop
            if (el.closest('[data-loop]')) continue;

            const attributesToRemove = [];
            for (let i = 0; i < el.attributes.length; i++) {
                const attr = el.attributes[i];
                if (attr.name.startsWith('data-bind-attr-') || attr.name.startsWith('data-bind-class-')) {
                    this._processAttribute(el, attr, data);
                    attributesToRemove.push(attr.name);
                }
            }

            for (const name of attributesToRemove) {
                el.removeAttribute(name);
            }
        }
    }

    _processAttribute(el, attr, data) {
        if (attr.name.startsWith('data-bind-attr-')) {
            const targetAttrName = attr.name.replace('data-bind-attr-', '');
            const val = this.engine._resolveValue(attr.value, data);

            if (val === false || val === null || val === undefined) {
                el.removeAttribute(targetAttrName);
            } else if (val === true) {
                el.setAttribute(targetAttrName, '');
            } else {
                el.setAttribute(targetAttrName, String(val));
            }
        } else if (attr.name.startsWith('data-bind-class-')) {
            const targetClassName = attr.name.replace('data-bind-class-', '');
            const isTruthy = Boolean(this.engine._resolveValue(attr.value, data));
            el.classList.toggle(targetClassName, isTruthy);
        }
    }
}
