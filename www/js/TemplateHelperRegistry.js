/**
 * Title: Template Helper Registry
 *
 * Purpose:
 * - Manages custom formatting helpers for the TemplateEngine.
 *
 * Why / Why this design:
 * - SRP / OCP: Extracts helper management out of the TemplateEngine, 
 *   allowing new helpers to be registered without modifying the core engine logic.
 *
 * Teaching notes:
 * - Helpers are simple pure functions.
 */
export class TemplateHelperRegistry {
    constructor() {
        /** @type {Map<string, Function>} */
        this._helpers = new Map();

        // Built-in formatting helpers
        this.register('currency', (val) => {
            const num = parseFloat(val) || 0;
            return '$' + num.toFixed(2);
        });
        this.register('uppercase', (val) => String(val || '').toUpperCase());
        this.register('lowercase', (val) => String(val || '').toLowerCase());
    }

    /**
     * Registers a custom formatting helper function.
     *
     * @param {string} name Helper identifier.
     * @param {Function} fn Function receiving `(value, ...args) => formattedValue`.
     * @returns {this}
     */
    register(name, fn) {
        if (typeof fn !== 'function') {
            throw new TypeError(`Helper [${name}] must be a function.`);
        }
        this._helpers.set(name, fn);
        return this;
    }

    /**
     * Checks if a helper is registered.
     * @param {string} name 
     * @returns {boolean}
     */
    has(name) {
        return this._helpers.has(name);
    }

    /**
     * Gets a registered helper.
     * @param {string} name 
     * @returns {Function}
     */
    get(name) {
        return this._helpers.get(name);
    }
}
