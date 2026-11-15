/**
 * Title: Magma Developer Diagnostics Client Controller
 *
 * Purpose:
 * - Handles interactive client-side behavior for `magma/error/templates/debug_error.php`:
 *   clipboard export of structured diagnostics JSON and raw stack trace, page reload,
 *   stack frame accordion toggling, and live filtering of the 404 routes table.
 *
 * Why / Why this design:
 * - Zero Global Pollution & Clean Event Delegation: Encapsulates all logic inside a strict ES6 IIFE
 *   and binds via `data-*` attributes and element IDs rather than polluting `window.*` or using inline HTML event handlers (`onclick`, `onkeyup`).
 * - Safe Data Boundary: Reads diagnostic payloads from `<script type="application/json" id="magma-debug-payload">`
 *   via `JSON.parse()` instead of interpolating PHP variables directly into executable JavaScript source.
 *
 * Teaching notes:
 * - Always encode JSON data inside a dedicated `application/json` script block with `JSON_HEX_TAG` flags
 *   on the PHP side so untrusted exception messages can never break out of `<script>` tags.
 */

(function () {
    'use strict';

    /**
     * Parses the embedded diagnostic JSON payload safely.
     *
     * @returns {{ rawTrace: string, fullDiagnostics: Object }}
     */
    function readDiagnosticPayload() {
        const payloadEl = document.getElementById('magma-debug-payload');
        if (!payloadEl) {
            return { rawTrace: '', fullDiagnostics: {} };
        }

        try {
            const parsed = JSON.parse(payloadEl.textContent || '{}');
            return {
                rawTrace: typeof parsed.rawTrace === 'string' ? parsed.rawTrace : '',
                fullDiagnostics: parsed.fullDiagnostics && typeof parsed.fullDiagnostics === 'object'
                    ? parsed.fullDiagnostics
                    : {}
            };
        } catch (_err) {
            return { rawTrace: '', fullDiagnostics: {} };
        }
    }

    let toastTimer = null;

    /**
     * Displays a transient confirmation toast message.
     *
     * @param {string} msg
     */
    function showToast(msg) {
        const toast = document.getElementById('toast');
        if (!toast) {
            return;
        }
        toast.textContent = msg;
        toast.classList.add('visible');
        if (toastTimer !== null) {
            window.clearTimeout(toastTimer);
        }
        toastTimer = window.setTimeout(() => {
            toast.classList.remove('visible');
        }, 2500);
    }

    /**
     * Copies a string or serializable object to the browser clipboard when in a secure context.
     *
     * @param {string|Object} contentPayload
     * @param {string} successMessage
     */
    function copyToClipboard(contentPayload, successMessage) {
        const content = (typeof contentPayload === 'string')
            ? contentPayload
            : JSON.stringify(contentPayload, null, 2);
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(content).then(() => showToast(successMessage));
        }
    }

    const payload = readDiagnosticPayload();

    document.querySelectorAll('[data-debug-action]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.getAttribute('data-debug-action');
            if (action === 'copy-json') {
                copyToClipboard(payload.fullDiagnostics, 'Diagnostics JSON copied to clipboard');
            } else if (action === 'copy-trace') {
                copyToClipboard(payload.rawTrace, 'Stack trace copied to clipboard');
            } else if (action === 'reload') {
                window.location.reload();
            }
        });
    });

    document.querySelectorAll('[data-frame-toggle]').forEach((header) => {
        header.addEventListener('click', () => {
            const index = header.getAttribute('data-frame-toggle');
            if (!index) {
                return;
            }
            const content = document.getElementById('frame-content-' + index);
            if (content) {
                content.classList.toggle('open');
            }
        });
    });

    const routesSearch = document.getElementById('routesSearch');
    if (routesSearch) {
        routesSearch.addEventListener('input', () => {
            const query = routesSearch.value.toLowerCase();
            document.querySelectorAll('.routes-table tbody tr').forEach((row) => {
                const text = (row.innerText || row.textContent || '').toLowerCase();
                const match = text.includes(query);
                row.classList.toggle('route-row-hidden', !match);
            });
        });
    }
}());
