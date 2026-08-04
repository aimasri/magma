'use strict';

/**
 * Title: Combobox Model
 * 
 * Purpose:
 * - Manages the data state and network requests for the combobox.
 * 
 * Why / Why this design:
 * - Decouples data fetching from DOM logic (Single Responsibility Principle).
 * 
 * Teaching notes:
 * - The model expects a dataProvider function to fetch results, ensuring it doesn't hardcode specific API endpoints.
 */
export class ComboboxModel {
    constructor(dataProvider) {
        if (typeof dataProvider !== 'function') {
            throw new Error("MagmaCombobox requires a dataProvider function in options.");
        }
        this.dataProvider = dataProvider;
    }

    async fetch(query) {
        if (!query) return [];
        try {
            const items = await this.dataProvider(query);
            return items || [];
        } catch (error) {
            console.error("MagmaCombobox dataProvider error:", error);
            return [];
        }
    }
}
