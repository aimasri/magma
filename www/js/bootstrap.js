/**
 * Title: Magma ES6 Modular Frontend Bootstrap Entry Point
 *
 * Purpose:
 * - Serves as the central ES module initialization script for the Magma client-side architecture.
 * - Guarantees resilient DOM lifecycle bootstrapping via `document.readyState === 'loading'` checks.
 * - Automatically auto-discovers and binds declarative action dispatchers, comboboxes, and rich text editors.
 *
 * Why / Why this design:
 * - Module Lifecycle Resilience: When scripts are loaded with `<script type="module">`, execution is deferred.
 *   In cached or dynamic environments, `DOMContentLoaded` may have already fired before the script executes,
 *   causing listeners on `DOMContentLoaded` to never execute. The readyState check prevents silent boot failures.
 * - Declarative Component Auto-Discovery: Scans the DOM for `[data-action]`, `[data-combobox]`, and
 *   `[data-editor]` attributes, instantiating controllers without requiring inline `<script>` tags.
 *
 * Teaching notes:
 * - Include in your HTML layout: `<script type="module" src="/js/bootstrap.js"></script>`
 * - Add components declaratively: `<input data-combobox data-ajax-url="/api/search">`
 */

import { EventBus, eventBus } from './EventBus.js';
import { ObservableStore } from './ObservableStore.js';
import { IdempotentBindingRegistry, bindingRegistry } from './IdempotentBindingRegistry.js';
import { EventDelegator, eventDelegator } from './EventDelegator.js';
import { MagmaActionDispatcher, actionDispatcher } from './MagmaActionDispatcher.js';
import { TemplateEngine, templateEngine } from './TemplateEngine.js';
import { DomSanitizer, domSanitizer } from './DomSanitizer.js';
import { MagmaEditor } from './MagmaEditor.js';
import { MagmaCombobox } from './MagmaCombobox.js';

// Export all modules for consumer ES6 import statements
export {
    EventBus,
    eventBus,
    ObservableStore,
    IdempotentBindingRegistry,
    bindingRegistry,
    EventDelegator,
    eventDelegator,
    MagmaActionDispatcher,
    actionDispatcher,
    TemplateEngine,
    templateEngine,
    DomSanitizer,
    domSanitizer,
    MagmaEditor,
    MagmaCombobox
};

/**
 * Initializes framework frontend components across the document.
 */
export function initMagma() {
    // 1. Initialize declarative action dispatcher
    actionDispatcher.init(document);

    // 2. Auto-initialize declarative Comboboxes
    const comboboxElements = document.querySelectorAll('[data-combobox]:not([data-combobox-initialized])');
    comboboxElements.forEach((el) => {
        try {
            new MagmaCombobox(el);
            el.setAttribute('data-combobox-initialized', 'true');
        } catch (err) {
            console.error('Failed to initialize MagmaCombobox on element:', el, err);
        }
    });

    // 3. Auto-initialize declarative WYSIWYG Editors
    const editorElements = document.querySelectorAll('[data-editor]:not([data-editor-initialized])');
    editorElements.forEach((el) => {
        try {
            new MagmaEditor(el);
            el.setAttribute('data-editor-initialized', 'true');
        } catch (err) {
            console.error('Failed to initialize MagmaEditor on element:', el, err);
        }
    });

    // Emit framework ready event
    eventBus.emit('magma:ready', { timestamp: Date.now() });
}

// Resilient DOM Ready State Check
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initMagma());
} else {
    // DOM is already parsed (or interactive/complete)
    initMagma();
}

// Expose Magma namespace globally for browser debugging & non-module scripts
if (typeof window !== 'undefined') {
    window.Magma = {
        EventBus,
        eventBus,
        ObservableStore,
        IdempotentBindingRegistry,
        bindingRegistry,
        EventDelegator,
        eventDelegator,
        MagmaActionDispatcher,
        actionDispatcher,
        TemplateEngine,
        templateEngine,
        DomSanitizer,
        domSanitizer,
        MagmaEditor,
        MagmaCombobox,
        init: initMagma
    };
}
