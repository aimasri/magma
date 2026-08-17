'use strict';

/**
 * Title: Combobox Model
 * 
 * Purpose:
 * - Manages the data state, API network requests, debouncing, and in-flight request cancellation for the combobox.
 * 
 * Why / Why this design:
 * - Decouples data fetching from DOM logic (Single Responsibility Principle).
 * - Asynchronous In-Flight Cancellation: Uses native `AbortController` to cancel pending search requests
 *   when the user continues typing, preventing race conditions and stale response overwrites.
 * 
 * Teaching notes:
 * - Accepts either an `ajaxUrl` string endpoint or a custom `dataProvider` function.
 */
export class ComboboxModel {
    /**
     * @param {Function|string} dataProviderOrUrl Function returning Promise<Array> or string URL endpoint.
     * @param {Object} [options={}]
     */
    constructor(dataProviderOrUrl, options = {}) {
        if (!dataProviderOrUrl) {
            throw new Error("MagmaCombobox requires an ajaxUrl string or a dataProvider function.");
        }

        this.provider = dataProviderOrUrl;
        this.isUrl = typeof dataProviderOrUrl === 'string';
        this.options = options;
        this._currentAbortController = null;
    }

    /**
     * Fetches search results for a query string.
     *
     * @param {string} query Search query.
     * @returns {Promise<Array<Object>>} Matching items.
     */
    async fetch(query) {
        if (!query || query.trim() === '') {
            return [];
        }

        // Cancel pending request if one is active
        if (this._currentAbortController) {
            this._currentAbortController.abort();
        }

        this._currentAbortController = new AbortController();
        const signal = this._currentAbortController.signal;

        try {
            if (this.isUrl) {
                const url = new URL(this.provider, window.location.origin);
                url.searchParams.set('q', query);
                if (this.options.extraParams) {
                    for (const [k, v] of Object.entries(this.options.extraParams)) {
                        url.searchParams.set(k, v);
                    }
                }

                const response = await fetch(url.toString(), {
                    signal,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                return Array.isArray(data) ? data : (data.items || data.data || []);
            }

            // Custom function dataProvider
            const results = await this.provider(query, signal);
            return Array.isArray(results) ? results : [];
        } catch (error) {
            if (error.name === 'AbortError') {
                return [];
            }
            console.error("MagmaCombobox data fetch error:", error);
            return [];
        }
    }
}
