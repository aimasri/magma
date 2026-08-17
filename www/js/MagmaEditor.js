import { DomSanitizer, domSanitizer } from './DomSanitizer.js';

/**
 * Title: Lightweight Zero-Dependency Vanilla ES6 WYSIWYG Editor
 *
 * Purpose:
 * - Provides a clean, dependency-free rich text editor interfacing with native browser commands.
 * - Integrates DomSanitizer to ensure all pasted or inserted content remains clean, semantic, and XSS-safe.
 * - Automatically synchronizes content with underlying HTML `<textarea>` inputs for standard form submissions.
 *
 * Why / Why this design:
 * - Minimal Footprint: Eliminates heavy third-party WYSIWYG dependencies (such as TinyMCE, Quill, or CKEditor)
 *   when only standard formatting (bold, italic, lists, quotes, hyperlinks) is required.
 * - Robust Sanitization: Intercepts clipboard paste events and runs content through `DomSanitizer`, preventing
 *   the injection of rogue inline styles or unsafe attributes.
 * - Form Compatibility: Works as a drop-in enhancement over existing `<textarea name="...">` fields.
 *
 * Teaching notes:
 * - Initialize on a textarea or container: `const editor = new MagmaEditor(document.querySelector('#description'));`
 */
export class MagmaEditor {
    /**
     * Initializes the MagmaEditor.
     *
     * @param {HTMLElement|string} target Textarea element or CSS selector.
     * @param {Object} [options={}] Configuration options.
     */
    constructor(target, options = {}) {
        this.target = typeof target === 'string' ? document.querySelector(target) : target;
        if (!this.target) {
            throw new Error(`MagmaEditor: Target element [${target}] not found.`);
        }

        this.options = {
            placeholder: options.placeholder || 'Enter content...',
            minHeight: options.minHeight || '140px',
            sanitizer: options.sanitizer || domSanitizer,
            toolbar: options.toolbar || ['bold', 'italic', 'underline', 'bulletList', 'numberedList', 'blockquote', 'link', 'clear'],
            ...options
        };

        this._abortController = new AbortController();
        this._initDOM();
        this._bindEvents();
    }

    /**
     * Builds and inserts the editor wrapper, toolbar, and contenteditable surface.
     *
     * @private
     */
    _initDOM() {
        // Create editor container
        this.container = document.createElement('div');
        this.container.className = 'magma-editor-container';

        // Create toolbar
        this.toolbar = document.createElement('div');
        this.toolbar.className = 'magma-editor-toolbar';

        // Build toolbar buttons
        this._buildToolbarButtons();

        // Create contenteditable area
        this.contentArea = document.createElement('div');
        this.contentArea.className = 'magma-editor-content';
        this.contentArea.contentEditable = 'true';
        this.contentArea.setAttribute('role', 'textbox');
        this.contentArea.setAttribute('aria-multiline', 'true');
        this.contentArea.setAttribute('data-placeholder', this.options.placeholder);
        this.contentArea.style.minHeight = this.options.minHeight;

        // Initialize content from target textarea/input if present
        const initialValue = this.target.value !== undefined ? this.target.value : this.target.innerHTML;
        this.contentArea.innerHTML = this.options.sanitizer.sanitizeHtml(initialValue || '');

        // Assemble DOM
        this.container.appendChild(this.toolbar);
        this.container.appendChild(this.contentArea);

        // Insert container into DOM
        if (this.target.parentNode) {
            this.target.parentNode.insertBefore(this.container, this.target);
            // Hide original textarea
            this.target.style.display = 'none';
        }
    }

    /**
     * Constructs toolbar action buttons.
     *
     * @private
     */
    _buildToolbarButtons() {
        const buttonDefs = {
            bold: { icon: 'B', title: 'Bold (Ctrl+B)', cmd: 'bold', label: 'Bold' },
            italic: { icon: 'I', title: 'Italic (Ctrl+I)', cmd: 'italic', label: 'Italic' },
            underline: { icon: 'U', title: 'Underline (Ctrl+U)', cmd: 'underline', label: 'Underline' },
            bulletList: { icon: '• List', title: 'Bullet List', cmd: 'insertUnorderedList', label: 'Bullet List' },
            numberedList: { icon: '1. List', title: 'Numbered List', cmd: 'insertOrderedList', label: 'Numbered List' },
            blockquote: { icon: '“ Quote', title: 'Blockquote', cmd: 'formatBlock', value: 'blockquote', label: 'Quote' },
            link: { icon: '🔗 Link', title: 'Insert Link', custom: () => this._promptLink(), label: 'Link' },
            clear: { icon: '🧹 Clear', title: 'Clear Formatting', cmd: 'removeFormat', label: 'Clear' }
        };

        for (const key of this.options.toolbar) {
            const def = buttonDefs[key];
            if (!def) continue;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `magma-editor-btn magma-editor-btn--${key}`;
            btn.title = def.title;
            btn.setAttribute('aria-label', def.label);
            btn.textContent = def.icon;

            btn.addEventListener('mousedown', (e) => {
                e.preventDefault(); // Prevent focus loss from contentArea
                if (def.custom) {
                    def.custom();
                } else {
                    this.execCommand(def.cmd, def.value || null);
                }
                this._sync();
            }, { signal: this._abortController.signal });

            this.toolbar.appendChild(btn);
        }
    }

    /**
     * Binds input, keyboard shortcuts, paste sanitization, and sync handlers.
     *
     * @private
     */
    _bindEvents() {
        const signal = this._abortController.signal;

        // Content synchronization
        this.contentArea.addEventListener('input', () => this._sync(), { signal });
        this.contentArea.addEventListener('blur', () => this._sync(), { signal });

        // Paste sanitization
        this.options.sanitizer.attachPasteSanitizer(this.contentArea, {
            onPaste: () => this._sync()
        });

        // Keyboard shortcuts (Ctrl+B, Ctrl+I, Ctrl+U)
        this.contentArea.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                const key = e.key.toLowerCase();
                if (key === 'b') {
                    e.preventDefault();
                    this.execCommand('bold');
                    this._sync();
                } else if (key === 'i') {
                    e.preventDefault();
                    this.execCommand('italic');
                    this._sync();
                } else if (key === 'u') {
                    e.preventDefault();
                    this.execCommand('underline');
                    this._sync();
                }
            }
        }, { signal });
    }

    /**
     * Prompts for URL and inserts a safe link.
     *
     * @private
     */
    _promptLink() {
        const selection = window.getSelection();
        if (!selection || selection.isCollapsed) {
            alert('Please highlight the text you wish to link.');
            return;
        }

        const url = prompt('Enter the link URL (e.g. https://example.com):', 'https://');
        if (url && this.options.sanitizer.isSafeUrl(url)) {
            this.execCommand('createLink', url);
        }
    }

    /**
     * Executes a native formatting command on the content area.
     *
     * @param {string} command
     * @param {*} [value=null]
     * @returns {boolean}
     */
    execCommand(command, value = null) {
        this.contentArea.focus();
        return document.execCommand(command, false, value);
    }

    /**
     * Synchronizes content from the contenteditable area back into the target input/textarea.
     *
     * @private
     */
    _sync() {
        const dirty = this.contentArea.innerHTML;
        const clean = this.options.sanitizer.sanitizeHtml(dirty);

        if (this.target.value !== undefined) {
            this.target.value = clean;
            // Dispatch native change and input events
            this.target.dispatchEvent(new Event('input', { bubbles: true }));
        } else {
            this.target.innerHTML = clean;
        }
    }

    /**
     * Retrieves the clean HTML content of the editor.
     *
     * @returns {string}
     */
    getContent() {
        return this.options.sanitizer.sanitizeHtml(this.contentArea.innerHTML);
    }

    /**
     * Sets the editor's HTML content.
     *
     * @param {string} html
     */
    setContent(html) {
        const clean = this.options.sanitizer.sanitizeHtml(html || '');
        this.contentArea.innerHTML = clean;
        this._sync();
    }

    /**
     * Retrieves the plain text content (without HTML tags).
     *
     * @returns {string}
     */
    getText() {
        return this.contentArea.textContent || '';
    }

    /**
     * Checks if the editor is empty.
     *
     * @returns {boolean}
     */
    isEmpty() {
        const text = this.getText().trim();
        return text.length === 0;
    }

    /**
     * Focuses the editor content area.
     */
    focus() {
        this.contentArea.focus();
    }

    /**
     * Destroys the editor instance and restores original textarea.
     */
    destroy() {
        this._abortController.abort();
        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }
        if (this.target) {
            this.target.style.display = '';
        }
    }
}
