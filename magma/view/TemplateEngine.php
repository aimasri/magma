<?php

namespace Magma\view;

/**
 * PHP-based View Renderer
 *
 * Purpose:
 * - Render PHP templates into safe HTML strings.
 * - Support global layouts and reusable partials.
 * - Return compiled HTML markup as a raw string rather than echoing directly to the output buffer.
 *
 * Why / Why this design:
 * - Keeps presentation logic strictly in templates using PHP as the engine. The two-stage 
 *   output buffering approach (`ob_start()`) enables composition (page -> layout -> string) 
 *   and ensures that if a rendering error occurs, partial HTML is never leaked to the client.
 *
 * Teaching notes:
 * - Flash data (like `errors` and `old` input) and CSRF tokens are injected from outside 
 *   (e.g., by global middleware or view composers) prior to rendering to keep the engine 
 *   completely independent of the HTTP transport layer. In production, this lightweight engine 
 *   might be replaced by a dedicated templating system (like Twig) that provides stricter 
 *   sandboxing and automatic XSS escaping by default.
 */
class TemplateEngine
{
    /** @var ViewLoaderInterface */
    private ViewLoaderInterface $loader;

    /** @var string Root directory for specific page templates. */
    private string $viewsPath;

    /** @var string Directory for shared layouts and UI partials. */
    private string $layoutPath = '';


    /** @var array Shared data accessible across partials and layouts. */
    private array $viewData = [];

    /** @var array Global data injected by middleware (e.g., vendor theme). */
    private array $sharedData = [];

    /**
     * Initializes the engine with paths for templates and layouts.
     * 
     * Execution Flow:
     * 1. Accept the injected ViewLoaderInterface.
     * 2. Accept the base views path and an optional layout path.
     * 3. Normalize the paths with a trailing directory separator.
     * 4. Store the loader and paths in the class properties for later rendering.
     * 
     * Logic behind the logic:
     * - The `ViewLoaderInterface` is injected rather than instantiated here (Dependency Inversion).
     *   This allows the framework to swap local file loading with cached or database-driven 
     *   loaders without touching this engine.
     * - Paths are typically defined in the bootstrap process relative to 
     *   the application root. Normalizing the paths here ensures developers 
     *   don't have to worry about whether they included a trailing slash.
     */
    public function __construct(ViewLoaderInterface $loader, string $viewsPath = '', string $layoutPath = '')
    {
        $this->loader = $loader;
        $this->viewsPath = rtrim($viewsPath, '/\\') . DIRECTORY_SEPARATOR;
        if (!empty($layoutPath)) {
            $this->layoutPath = rtrim($layoutPath, '/\\') . DIRECTORY_SEPARATOR;
        }
    }


    /**
     * Globally shares a variable with all templates.
     * 
     * Execution Flow:
     * 1. Accept a string key and any mixed value.
     * 2. Store it in the internal `$sharedData` dictionary.
     * 
     * Logic behind the logic:
     * - Storing these separately from `$viewData` allows us to merge them at 
     *   the exact moment of rendering, ensuring that specifically passed 
     *   controller data can override global defaults if necessary.
     */
    public function share(string $key, mixed $value): void
    {
        $this->sharedData[$key] = $value;
    }


    /**
     * Transforms a PHP template and its layout into a compiled HTML string.
     * 
     * Execution Flow:
     * 1. Start output buffering to capture the inner page template content.
     * 2. Pass the `$data` array directly into the local scope for the view.
     * 3. Capture the template output into `$content` and clear the buffer.
     * 4. If a layout is specified, start a second buffer, append `$content` to `$data`, and include the layout.
     * 5. Return the final captured HTML string.
     * 
     * Logic behind the logic:
     * - By avoiding `extract()`, we prevent scope pollution and make it immediately clear 
     *   where template variables originate (`$data['key']`).
     * 
     * @param string $template The view file to load.
     * @param array $data Variables to make available to the view.
     * @param string|null $layout The layout wrapper (defaults to 'default').
     * @return string The rendered HTML markup.
     */
    public function render(string $template, array $data = [], ?string $layout = 'default'): string
    {
        // Merge global shared data with specific template data. 
        // Specific data overrides shared data.
        $data = array_merge($this->sharedData, $data);
        $data['engine'] = $this;

        $this->viewData = $data;
        $templateFile = $this->viewsPath . $template . '.php';
        if (!$this->loader->exists($templateFile)) {
            throw new \RuntimeException("View file not found: {$templateFile}");
        }

        // Defer loading to the injected loader
        $content = $this->loader->load($templateFile, $data);

        if ($layout && !empty($this->layoutPath)) {
            $layoutFile = $this->layoutPath . $layout . '.php';
            if (!$this->loader->exists($layoutFile)) {
                throw new \RuntimeException("Layout file not found: {$layoutFile}");
            }
            
            $data['content'] = $content;
            $finalContent = $this->loader->load($layoutFile, $data);

            return $finalContent;
        }

        return $content;
    }

    /**
     * Injects a partial template directly into the current output buffer.
     * 
     * Execution Flow:
     * 1. Determine the correct directory path based on whether a layout path is defined.
     * 2. Construct the full path to the partial view file and verify its existence.
     * 3. Merge the global shared data, parent view data, and the specific partial data.
     * 4. Make the merged data array available as `$data` in the local scope.
     * 5. Require the partial file, executing it directly into the current output buffer.
     * 
     * Logic behind the logic:
     * - Partials are "sub-templates" used for building reusable components like sidebars 
     *   or nav menus. Unlike render(), partial() is designed to be called from within 
     *   another template (while an output buffer is already active) to promote UI modularity 
     *   without creating nested output buffers.
     * 
     * @param string $template The partial file name (without .php extension).
     * @param array $data Local variables specific to this partial instance.
     */
    public function partial(string $template, array $data = []): void
    {
        $path = !empty($this->layoutPath) ? $this->layoutPath : $this->viewsPath;
        $templateFile = $path . $template . '.php';

        if (!$this->loader->exists($templateFile)) {
            throw new \RuntimeException("Partial view file not found: {$templateFile}");
        }

        $data = array_merge($this->sharedData, $this->viewData, $data);
        $data['engine'] = $this;
        echo $this->loader->load($templateFile, $data);
    }

    /**
     * Sanitizes strings for safe HTML output.
     * 
     * Execution Flow:
     * 1. Receive a potentially null or unsafe string.
     * 2. Pass the string through `htmlspecialchars()`.
     * 3. Return the sanitized string safe for browser rendering.
     * 
     * Logic behind the logic:
     * - This is the primary defense against Cross-Site Scripting (XSS). 
     *   ENT_QUOTES ensures both single and double quotes are escaped. 
     *   A null coalescing operator is used because database results or optional 
     *   form inputs might legitimately be null.
     */
    public function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
