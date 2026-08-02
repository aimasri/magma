/**
 * Title: Application Bootstrap
 * 
 * Purpose:
 * - Initializes the core Magma application environment.
 * - Serves as the primary entry point for global setups.
 * 
 * Why/Why this design:
 * - Adheres to a single-entry-point architecture to keep global scope clean and initialization order predictable.
 * - Separating init logic from the DOMContentLoaded listener simplifies unit testing.
 * 
 * Teaching notes:
 * - Compare to main.js in frameworks like Vue or React where root component mounting occurs.
 * - To extend, inject dependencies (e.g., store, router) into the init function instead of relying on global singletons.
 */

/**
 * Initializes global application context.
 * 
 * Logic behind the logic:
 * Bootstraps the application only when explicitly called, allowing precise control 
 * over execution timing, which is particularly useful for environments requiring 
 * asynchronous prerequisites (like auth tokens) before initialization.
 */
export function init() {
    console.log("Magma App initialized");
    // Global initializations can occur here
}

/**
 * Global entry point listener.
 * 
 * Logic behind the logic:
 * Waits for the DOM to be fully loaded before invoking initialization, ensuring 
 * that any DOM elements required by the application are available.
 */
document.addEventListener('DOMContentLoaded', () => {
    init();
});
