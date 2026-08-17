/**
 * Title: Declarative Action Dispatcher & Controller Router
 *
 * Purpose:
 * - Declarative client-side event routing utility that maps `data-action="namespace:action"` attributes
 *   to registered ES6 controller handlers.
 * - Permanently eliminates inline `onclick="..."` markup and global `window.*` variable pollution.
 *
 * Why / Why this design:
 * - Single Responsibility & Declarative HTML: Allows HTML templates to declare interactive behaviors
 *   declaratively (e.g., `<button data-action="menu:delete-item" data-id="42">Delete</button>`) without
 *   embedding raw JavaScript strings in views.
 * - Controller Namespace Isolation: Groups action handlers into clean, testable ES6 classes/modules
 *   (e.g., `MenuController`, `StaffController`, `CartController`).
 * - Lifecycle Teardown: Uses `AbortController` internally so the entire dispatcher can be torn down
 *   cleanly on SPA route transitions.
 *
 * Teaching notes:
 * - Register a controller: `actionDispatcher.registerController('menu', new MenuController());`
 * - In HTML: `<button data-action="menu:toggleStatus" data-id="10">Toggle</button>`
 * - In MenuController: `toggleStatus(event, element, dataset) { ... }`
 */
export class MagmaActionDispatcher {
    /**
     * Initializes the MagmaActionDispatcher.
     *
     * @param {Element|Document} [root=document] Root element to observe.
     */
    constructor(root = document) {
        this.root = root;
        /** @type {Map<string, Object>} */
        this._controllers = new Map();
        /** @type {Map<string, Function>} */
        this._directActions = new Map();
        /** @type {AbortController|null} */
        this._abortController = null;
        this._isInitialized = false;
    }

    /**
     * Registers a controller instance under a namespace.
     *
     * @param {string} namespace Unique controller key (e.g. 'menu', 'services', 'auth').
     * @param {Object} controller Controller instance with action methods.
     * @returns {this}
     */
    registerController(namespace, controller) {
        if (!controller || typeof controller !== 'object') {
            throw new TypeError(`Controller for namespace [${namespace}] must be an object.`);
        }
        this._controllers.set(namespace.toLowerCase(), controller);
        return this;
    }

    /**
     * Registers a direct action handler callback.
     *
     * @param {string} actionName Exact action string (e.g. 'modal:open', 'global:logout').
     * @param {Function} handler Callback receiving `(event, element, payload)`.
     * @returns {this}
     */
    registerAction(actionName, handler) {
        if (typeof handler !== 'function') {
            throw new TypeError(`Handler for action [${actionName}] must be a function.`);
        }
        this._directActions.set(actionName, handler);
        return this;
    }

    /**
     * Initializes global event listeners on the root container.
     *
     * @param {Element|Document} [root=null] Optional root override.
     * @returns {this}
     */
    init(root = null) {
        if (this._isInitialized) {
            return this;
        }

        if (root) {
            this.root = root;
        }

        this._abortController = new AbortController();
        const signal = this._abortController.signal;

        const supportedEvents = ['click', 'submit', 'change', 'input'];

        for (const eventType of supportedEvents) {
            this.root.addEventListener(
                eventType,
                (event) => this._handleEvent(event, eventType),
                { signal, capture: eventType === 'submit' || eventType === 'change' }
            );
        }

        this._isInitialized = true;
        return this;
    }

    /**
     * Internal event processor routing triggered events to matching action handlers.
     *
     * Execution Flow:
     * 1. Inspect event.target and locate nearest element with `[data-action]`.
     * 2. Verify if the element specifies a custom trigger event via `data-action-event` (defaults to match context).
     * 3. Extract dataset attributes into a payload object.
     * 4. Check for `data-action-prevent` or `data-action-stop` modifier flags.
     * 5. Dispatch to matching controller method or direct action handler.
     *
     * @param {Event} event
     * @param {string} eventType
     * @private
     */
    _handleEvent(event, eventType) {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const actionElement = target.closest('[data-action]');
        if (!actionElement) return;

        // Check if matching element is inside our root boundary
        if (this.root !== document && !this.root.contains(actionElement)) return;

        const actionString = actionElement.dataset.action;
        if (!actionString) return;

        // Determine expected trigger event type
        const specifiedEvent = actionElement.dataset.actionEvent;
        const expectedEvent = specifiedEvent || this._getDefaultEventType(actionElement);

        if (expectedEvent !== eventType) {
            return;
        }

        // Modifiers
        if (actionElement.hasAttribute('data-action-prevent') || actionElement.tagName === 'A' || actionElement.tagName === 'FORM') {
            if (actionElement.dataset.actionPrevent !== 'false') {
                event.preventDefault();
            }
        }

        if (actionElement.hasAttribute('data-action-stop')) {
            event.stopPropagation();
        }

        // Build payload from dataset
        const payload = this._extractPayload(actionElement);

        this.dispatch(actionString, event, actionElement, payload);
    }

    /**
     * Executes the handler associated with an action string.
     *
     * @param {string} actionString E.g., 'menu:deleteItem' or 'openModal'.
     * @param {Event} event The native DOM event.
     * @param {Element} element The triggering DOM element.
     * @param {Object} [payload={}] Extracted dataset attributes.
     * @returns {*} Return value from the invoked action handler.
     */
    dispatch(actionString, event, element, payload = {}) {
        // 1. Direct action lookup
        if (this._directActions.has(actionString)) {
            const handler = this._directActions.get(actionString);
            return handler(event, element, payload);
        }

        // 2. Controller namespace lookup (e.g. 'menu:delete' or 'menu.delete')
        let namespace = '';
        let action = '';

        if (actionString.includes(':')) {
            [namespace, action] = actionString.split(':', 2);
        } else if (actionString.includes('.')) {
            [namespace, action] = actionString.split('.', 2);
        } else {
            namespace = 'global';
            action = actionString;
        }

        const controller = this._controllers.get(namespace.toLowerCase());
        if (!controller) {
            console.warn(`MagmaActionDispatcher: No controller registered for namespace [${namespace}]. (Action: ${actionString})`);
            return null;
        }

        // Resolve method name (supports kebab-case, snake_case, and camelCase)
        const methodName = this._resolveMethodName(controller, action);
        if (!methodName || typeof controller[methodName] !== 'function') {
            console.warn(`MagmaActionDispatcher: Action method [${action}] not found on controller [${namespace}].`);
            return null;
        }

        try {
            return controller[methodName](event, element, payload);
        } catch (error) {
            console.error(`MagmaActionDispatcher error executing [${actionString}]:`, error);
            throw error;
        }
    }

    /**
     * Infers default event type based on HTML element tag.
     *
     * @param {Element} element
     * @returns {string}
     * @private
     */
    _getDefaultEventType(element) {
        const tagName = element.tagName.toLowerCase();
        if (tagName === 'form') return 'submit';
        if (tagName === 'select') return 'change';
        if (tagName === 'input' && (element.type === 'checkbox' || element.type === 'radio' || element.type === 'file')) {
            return 'change';
        }
        return 'click';
    }

    /**
     * Extracts dataset into a plain object, excluding action metadata keys.
     *
     * @param {Element} element
     * @returns {Object}
     * @private
     */
    _extractPayload(element) {
        const payload = {};
        for (const [key, value] of Object.entries(element.dataset)) {
            if (key !== 'action' && key !== 'actionEvent' && key !== 'actionPrevent' && key !== 'actionStop') {
                payload[key] = value;
            }
        }
        return payload;
    }

    /**
     * Finds matching method on controller taking into account naming conventions.
     *
     * @param {Object} controller
     * @param {string} action
     * @returns {string|null}
     * @private
     */
    _resolveMethodName(controller, action) {
        if (typeof controller[action] === 'function') {
            return action;
        }

        // Convert kebab-case or snake_case to camelCase
        const camel = action.replace(/[-_]([a-z])/g, (_, char) => char.toUpperCase());
        if (typeof controller[camel] === 'function') {
            return camel;
        }

        // Check handleAction (e.g. handleToggle)
        const handleMethod = 'handle' + camel.charAt(0).toUpperCase() + camel.slice(1);
        if (typeof controller[handleMethod] === 'function') {
            return handleMethod;
        }

        return null;
    }

    /**
     * Tears down all active event listeners and clears state.
     */
    destroy() {
        if (this._abortController) {
            this._abortController.abort();
            this._abortController = null;
        }
        this._controllers.clear();
        this._directActions.clear();
        this._isInitialized = false;
    }
}

/** Default document-wide singleton dispatcher. */
export const actionDispatcher = new MagmaActionDispatcher();
