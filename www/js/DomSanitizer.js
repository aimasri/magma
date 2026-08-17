/**
 * Title: Strict Client-Side DOM Clipboard & Rich Text Sanitizer
 *
 * Purpose:
 * - Provides recursive DOM node tree parsing and sanitization for rich text editors and clipboard paste events.
 * - Enforces strict HTML tag allowlists, attribute stripping, and safe URL protocol filtering.
 *
 * Why / Why this design:
 * - XSS & Layout Destruction Prevention: Copying rich text from external software (Microsoft Word,
 *   Google Docs, external websites) injects malformed markup, proprietary XML tags (`<o:p>`), dangerous
 *   inline stylesheets (`style="position:absolute;..."`), and potential malicious scripts (`<img onerror="...">`).
 * - Zero Dependencies: Implements recursive DOM parsing natively using the browser's `DOMParser` without
 *   requiring heavy external libraries (like DOMPurify).
 * - Safe URL Scheme Enforcement: Strictly disallows `javascript:`, `vbscript:`, and `data:` URI attack vectors.
 *
 * Teaching notes:
 * - Sanitize raw HTML before inserting into the DOM: `const cleanHtml = domSanitizer.sanitizeHtml(dirtyHtml);`
 * - Attach to contenteditable inputs: `const { destroy } = domSanitizer.attachPasteSanitizer(editorElement);`
 */
export class DomSanitizer {
    constructor(options = {}) {
        /** @type {Set<string>} Allowed uppercase tag names */
        this.allowedTags = new Set(options.allowedTags || [
            'B', 'I', 'U', 'STRONG', 'EM', 'P', 'BR',
            'UL', 'OL', 'LI', 'A', 'BLOCKQUOTE', 'CODE', 'PRE', 'SPAN'
        ]);

        /** @type {Set<string>} Allowed URL protocols for hyperlinks */
        this.allowedProtocols = new Set(options.allowedProtocols || [
            'http:', 'https:', 'mailto:', 'tel:'
        ]);
    }

    /**
     * Sanitizes an HTML string and returns safe, filtered HTML markup.
     *
     * Execution Flow:
     * 1. Parse dirty HTML string into a detached DOM Document via `DOMParser`.
     * 2. Recursively sanitize nodes from the root body.
     * 3. Serialize and return the clean inner HTML.
     *
     * @param {string} dirtyHtml Untrusted HTML string.
     * @returns {string} Safe sanitized HTML markup.
     */
    sanitizeHtml(dirtyHtml) {
        if (!dirtyHtml || typeof dirtyHtml !== 'string') {
            return '';
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString(dirtyHtml, 'text/html');
        const cleanFragment = document.createDocumentFragment();

        const childNodes = Array.from(doc.body.childNodes);
        for (const child of childNodes) {
            const cleanNode = this.sanitizeNode(child);
            if (cleanNode) {
                cleanFragment.appendChild(cleanNode);
            }
        }

        const container = document.createElement('div');
        container.appendChild(cleanFragment);
        return container.innerHTML;
    }

    /**
     * Recursively inspects and sanitizes a single DOM Node.
     *
     * Execution Flow:
     * 1. If TextNode, return cloned text node.
     * 2. If ElementNode:
     *    a. Check if tag is in allowedTags allowlist.
     *    b. If forbidden, recursively salvage its allowed child nodes without the wrapper tag.
     *    c. If allowed, create a clean replacement element.
     *    d. Filter and sanitize attributes (stripping arbitrary styles, IDs, and on* handlers).
     *    e. If tag is 'A', validate href protocol and set safe `rel="noopener noreferrer"`.
     *    f. Recursively sanitize and append child nodes.
     * 3. Return cleaned node or DocumentFragment.
     *
     * @param {Node} node
     * @returns {Node|DocumentFragment|null}
     */
    sanitizeNode(node) {
        // 1. Text node: always safe
        if (node.nodeType === Node.TEXT_NODE) {
            return document.createTextNode(node.textContent || '');
        }

        // 2. Element node
        if (node.nodeType === Node.ELEMENT_NODE) {
            const el = /** @type {HTMLElement} */ (node);
            const tagName = el.tagName.toUpperCase();

            // Tag is not in allowlist: Unwrap and preserve children
            if (!this.allowedTags.has(tagName)) {
                const fragment = document.createDocumentFragment();
                const children = Array.from(el.childNodes);
                for (const child of children) {
                    const cleanChild = this.sanitizeNode(child);
                    if (cleanChild) {
                        fragment.appendChild(cleanChild);
                    }
                }
                return fragment;
            }

            // Tag is allowed: create pristine clean element
            const cleanElement = document.createElement(tagName.toLowerCase());

            // Handle special attribute rules for <a> tags
            if (tagName === 'A') {
                const href = el.getAttribute('href');
                if (href && this.isSafeUrl(href)) {
                    cleanElement.setAttribute('href', href);
                    
                    const title = el.getAttribute('title');
                    if (title) {
                        cleanElement.setAttribute('title', title.slice(0, 200));
                    }

                    const target = el.getAttribute('target');
                    if (target === '_blank') {
                        cleanElement.setAttribute('target', '_blank');
                        cleanElement.setAttribute('rel', 'noopener noreferrer');
                    }
                } else {
                    // Invalid link: unwrap children without <a> tag
                    const fragment = document.createDocumentFragment();
                    const children = Array.from(el.childNodes);
                    for (const child of children) {
                        const cleanChild = this.sanitizeNode(child);
                        if (cleanChild) fragment.appendChild(cleanChild);
                    }
                    return fragment;
                }
            }

            // Recursively sanitize children
            const children = Array.from(el.childNodes);
            for (const child of children) {
                const cleanChild = this.sanitizeNode(child);
                if (cleanChild) {
                    cleanElement.appendChild(cleanChild);
                }
            }

            return cleanElement;
        }

        return null;
    }

    /**
     * Validates whether a URL protocol or path is safe.
     *
     * @param {string} url
     * @returns {boolean} True if safe, false if dangerous scheme detected.
     */
    isSafeUrl(url) {
        if (!url || typeof url !== 'string') return false;

        const trimmed = url.trim();

        // Relative paths and anchors are safe
        if (trimmed.startsWith('/') || trimmed.startsWith('./') || trimmed.startsWith('#') || trimmed.startsWith('?')) {
            return true;
        }

        try {
            const parsed = new URL(trimmed, window.location.origin);
            return this.allowedProtocols.has(parsed.protocol);
        } catch {
            return false;
        }
    }

    /**
     * Intercepts paste events on contenteditable elements to sanitize rich text or plain text clipboard data.
     *
     * @param {HTMLElement} targetElement Contenteditable container.
     * @param {Object} [options={}] Optional configuration.
     * @returns {{ destroy: Function }} Lifecycle teardown object.
     */
    attachPasteSanitizer(targetElement, options = {}) {
        if (!targetElement || !(targetElement instanceof HTMLElement)) {
            throw new TypeError("attachPasteSanitizer requires a valid HTMLElement.");
        }

        const abortController = new AbortController();

        const handlePaste = (e) => {
            e.preventDefault();
            const clipboardData = e.clipboardData || window.clipboardData;
            if (!clipboardData) return;

            let html = clipboardData.getData('text/html');
            let cleanContent = '';

            if (html) {
                cleanContent = this.sanitizeHtml(html);
            } else {
                const text = clipboardData.getData('text/plain') || '';
                cleanContent = this.escapeHtml(text).replace(/\n/g, '<br>');
            }

            // Insert sanitized content using native execCommand
            document.execCommand('insertHTML', false, cleanContent);

            if (typeof options.onPaste === 'function') {
                options.onPaste(cleanContent);
            }
        };

        targetElement.addEventListener('paste', handlePaste, { signal: abortController.signal });

        return {
            destroy: () => abortController.abort()
        };
    }

    /**
     * Strips all HTML tags, returning plain text.
     *
     * @param {string} html
     * @returns {string} Plain text.
     */
    stripTags(html) {
        if (!html) return '';
        const doc = new DOMParser().parseFromString(html, 'text/html');
        return doc.body.textContent || '';
    }

    /**
     * Escapes unsafe characters for HTML text insertion.
     *
     * @param {string} text
     * @returns {string}
     */
    escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}

/** Default singleton DomSanitizer instance. */
export const domSanitizer = new DomSanitizer();
