<?php

declare(strict_types=1);

namespace Magma\view;

/**
 * Title: Decoupled PHP Template Engine
 *
 * Purpose:
 * - Render PHP view templates, decoupled layout wrappers, and modular partial components into safe HTML strings.
 * - Support namespaced modular views (e.g. 'Services::index', 'Menu::item_card') via ViewLoaderInterface.
 * - Isolate output buffers so rendering errors never leak malformed HTML markup to the client.
 *
 * Why / Why this design:
 * - Decoupled Layouts & Partials: Separating `layoutPath` (full page shells) from `partialsPath`
 *   (reusable UI sub-components) prevents path collision and enables clear directory organization.
 * - Dependency Inversion Principle (DIP): View loading is abstracted behind `ViewLoaderInterface`,
 *   allowing local filesystem, database, or cached template resolution without modifying the engine.
 * - Two-Stage Output Buffer Isolation: Protects HTTP output by trapping buffer levels and swallowing
 *   partial output if an unhandled exception occurs inside a template.
 *
 * Teaching notes:
 * - Controller data and global shared variables (CSRF token, flash messages, user session) are merged
 *   at runtime. Specific controller data overrides shared globals.
 * - Use `escape()` for all dynamic user input to prevent Cross-Site Scripting (XSS).
 */
class TemplateEngine
{
    /** @var string Root directory for specific page templates. */
    private string $viewsPath;

    /** @var string Directory for shared layouts (e.g., default.php, auth.php). */
    private string $layoutPath = '';

    /** @var string Directory for reusable UI partials (e.g., header.php, sidebar.php). */
    private string $partialsPath = '';

    /** @var ViewLoaderInterface|null Optional decoupled view loader for namespaced templates. */
    private ?ViewLoaderInterface $loader = null;

    /** @var array<string, mixed> Shared data accessible across partials and layouts. */
    private array $viewData = [];

    /** @var array<string, mixed> Global data injected by middleware (e.g., vendor theme, auth user). */
    private array $sharedData = [];

    /** @var array<string, string> Cache for resolved layout paths. */
    private array $resolvedLayoutCache = [];

    /**
     * Initializes the template engine with directory paths and optional view loader.
     *
     * Execution Flow:
     * 1. Normalize view, layout, and partial directory paths with trailing directory separators.
     * 2. Store or instantiate the ViewLoaderInterface instance.
     * 3. Register root paths with the loader if namespaces are supported.
     *
     * @param string $viewsPath Base directory for application view templates.
     * @param string $layoutPath Directory for full-page layout shells.
     * @param string $partialsPath Directory for modular UI partials.
     * @param ViewLoaderInterface|null $loader Optional decoupled view loader instance.
     */
    public function __construct(
        string $viewsPath = '',
        string $layoutPath = '',
        string $partialsPath = '',
        ?ViewLoaderInterface $loader = null
    ) {
        $this->viewsPath = !empty($viewsPath) ? rtrim($viewsPath, '/\\') . DIRECTORY_SEPARATOR : '';
        $this->layoutPath = !empty($layoutPath) ? rtrim($layoutPath, '/\\') . DIRECTORY_SEPARATOR : '';
        $this->partialsPath = !empty($partialsPath) ? rtrim($partialsPath, '/\\') . DIRECTORY_SEPARATOR : '';

        if ($loader !== null) {
            $this->loader = $loader;
        } elseif (!empty($this->viewsPath)) {
            $this->loader = new LocalFileViewLoader($this->viewsPath);
        }
    }

    /**
     * Gets the current base views directory path.
     *
     * @return string Normalized views directory path.
     */
    public function getViewsPath(): string
    {
        return $this->viewsPath;
    }

    /**
     * Sets the base views directory path.
     *
     * @param string $path Directory path for application views.
     * @return void
     */
    public function setViewsPath(string $path): void
    {
        $this->viewsPath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
        if ($this->loader instanceof LocalFileViewLoader) {
            $this->loader = new LocalFileViewLoader($this->viewsPath);
        }
    }

    /**
     * Gets the current layouts directory path.
     *
     * @return string Normalized layout directory path.
     */
    public function getLayoutPath(): string
    {
        return $this->layoutPath;
    }

    /**
     * Sets the layouts directory path.
     *
     * @param string $path Directory path for layout shells.
     * @return void
     */
    public function setLayoutPath(string $path): void
    {
        $this->layoutPath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Gets the current partials directory path.
     *
     * @return string Normalized partials directory path.
     */
    public function getPartialsPath(): string
    {
        return $this->partialsPath;
    }

    /**
     * Sets the partials directory path.
     *
     * @param string $path Directory path for UI partials.
     * @return void
     */
    public function setPartialsPath(string $path): void
    {
        $this->partialsPath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Gets the underlying view loader instance.
     *
     * @return ViewLoaderInterface|null
     */
    public function getLoader(): ?ViewLoaderInterface
    {
        return $this->loader;
    }

    /**
     * Sets the decoupled view loader instance.
     *
     * @param ViewLoaderInterface $loader
     * @return void
     */
    public function setLoader(ViewLoaderInterface $loader): void
    {
        $this->loader = $loader;
    }

    /**
     * Globally shares a variable with all rendered templates, layouts, and partials.
     *
     * @param string $key Variable name.
     * @param mixed $value Variable value.
     * @return void
     */
    public function share(string $key, mixed $value): void
    {
        $this->sharedData[$key] = $value;
    }

    /**
     * Retrieves all globally shared variables.
     *
     * @return array<string, mixed>
     */
    public function getSharedData(): array
    {
        return $this->sharedData;
    }

    /**
     * Transforms a PHP template and optional layout shell into a compiled HTML string.
     *
     * Execution Flow:
     * 1. Merge global shared data with specific template data.
     * 2. Expose the TemplateEngine instance as `$data['engine']`.
     * 3. Resolve the template file path (handling namespaced templates via ViewLoader if available).
     * 4. Execute the template in an isolated output buffer.
     * 5. If a layout is requested, resolve the layout template, inject `$data['content']`, and wrap the output.
     * 6. Return the fully compiled HTML markup.
     *
     * @param string $template The view file name or namespaced identifier (e.g., 'welcome' or 'Services::index').
     * @param array<string, mixed> $data Variables to pass to the view.
     * @param string|null $layout The layout wrapper template name (defaults to 'default', null for standalone).
     * @return string The rendered HTML markup.
     * @throws \RuntimeException If the view or layout file cannot be found.
     */
    public function render(string $template, array $data = [], ?string $layout = 'default'): string
    {
        $data = array_merge($this->sharedData, $data);
        $data['engine'] = $this;
        $this->viewData = $data;

        $templateFile = $this->resolveTemplatePath($template, $this->viewsPath);
        $content = $this->loadFile($templateFile, $data);

        if ($layout !== null && $layout !== '') {
            $layoutFile = $this->resolveLayoutPath($layout);
            if ($layoutFile !== null) {
                $data['content'] = $content;
                return $this->loadFile($layoutFile, $data);
            }
        }

        return $content;
    }

    /**
     * Injects a partial template directly into the current active output buffer.
     *
     * Execution Flow:
     * 1. Resolve the partial file path using the dedicated `partialsPath` (falling back to `layoutPath` then `viewsPath`).
     * 2. Merge global shared data, parent view data, and partial-specific data.
     * 3. Execute the partial file within an isolated buffer and echo its content to the parent stream.
     *
     * @param string $template The partial template name or namespaced identifier (e.g., 'sidebar' or 'Menu::card').
     * @param array<string, mixed> $data Local variables specific to this partial instance.
     * @return void
     * @throws \RuntimeException If the partial file cannot be found.
     */
    public function partial(string $template, array $data = []): void
    {
        $mergedData = array_merge($this->sharedData, $this->viewData, $data);
        $mergedData['engine'] = $this;

        $partialFile = $this->resolvePartialPath($template);
        echo $this->loadFile($partialFile, $mergedData);
    }

    /**
     * Resolves a view template path using the ViewLoader or local directory fallback.
     *
     * @param string $template Template identifier.
     * @param string $fallbackDir Fallback base directory.
     * @return string Absolute file path.
     * @throws \RuntimeException If file cannot be resolved.
     */
    private function resolveTemplatePath(string $template, string $fallbackDir): string
    {
        // 1. Namespaced template (e.g. 'Services::index')
        if (str_contains($template, '::') && $this->loader !== null) {
            return $this->loader->resolvePath($template);
        }

        // 2. Direct file lookup via fallback directory
        if (!empty($fallbackDir)) {
            $file = $fallbackDir . ltrim($template, '/\\');
            if (!str_ends_with($file, '.php')) {
                $file .= '.php';
            }
            if (file_exists($file)) {
                return $file;
            }
        }

        // 3. Fallback to loader if available
        if ($this->loader !== null && $this->loader->exists($template)) {
            return $this->loader->resolvePath($template);
        }

        throw new \RuntimeException("View template file not found: {$template} in base path '{$fallbackDir}'");
    }

    /**
     * Resolves a layout template path.
     *
     * @param string $layout Layout identifier.
     * @return string|null Absolute file path, or null if layout cannot be resolved.
     * @throws \RuntimeException If layout is specified but missing.
     */
    private function resolveLayoutPath(string $layout): ?string
    {
        if (isset($this->resolvedLayoutCache[$layout])) {
            return $this->resolvedLayoutCache[$layout];
        }

        if (str_contains($layout, '::') && $this->loader !== null) {
            $path = $this->loader->resolvePath($layout);
            $this->resolvedLayoutCache[$layout] = $path;
            return $path;
        }

        $searchPaths = array_filter([
            $this->layoutPath,
            $this->viewsPath . 'layouts',
            $this->viewsPath . 'partials',
            $this->viewsPath,
            $this->partialsPath,
        ]);

        foreach ($searchPaths as $baseDir) {
            $candidate = rtrim((string)$baseDir, '/\\') . DIRECTORY_SEPARATOR . ltrim($layout, '/\\');
            if (!str_ends_with($candidate, '.php')) {
                $candidate .= '.php';
            }
            if (file_exists($candidate)) {
                $this->resolvedLayoutCache[$layout] = $candidate;
                return $candidate;
            }
        }

        throw new \RuntimeException("Layout file not found: {$layout}");
    }

    /**
     * Resolves a partial template path, prioritizing partialsPath -> layoutPath -> viewsPath.
     *
     * @param string $template Partial identifier.
     * @return string Absolute file path.
     * @throws \RuntimeException If partial cannot be resolved.
     */
    private function resolvePartialPath(string $template): string
    {
        if (str_contains($template, '::') && $this->loader !== null) {
            return $this->loader->resolvePath($template);
        }

        $searchPaths = array_filter([
            $this->partialsPath,
            $this->layoutPath,
            $this->viewsPath,
        ]);

        foreach ($searchPaths as $baseDir) {
            $candidate = $baseDir . ltrim($template, '/\\');
            if (!str_ends_with($candidate, '.php')) {
                $candidate .= '.php';
            }
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        if ($this->loader !== null && $this->loader->exists($template)) {
            return $this->loader->resolvePath($template);
        }

        $searched = implode(', ', $searchPaths);
        throw new \RuntimeException("Partial view file not found: '{$template}' (searched in: [{$searched}])");
    }

    /**
     * Executes a PHP template file within an isolated output buffer.
     *
     * Execution Flow:
     * 1. Record current buffer depth via `ob_get_level()`.
     * 2. Start a new output buffer.
     * 3. Require the template file in local scope (with `$data` available).
     * 4. Retrieve and clean buffer contents.
     * 5. If an unhandled exception/error occurs, unwind buffers to original level before rethrowing.
     *
     * @param string $path Absolute filesystem path to the PHP template file.
     * @param array<string, mixed> $data Variables exposed to the template scope.
     * @return string Rendered HTML content.
     * @throws \Throwable If rendering encounters an error.
     */
    private function loadFile(string $path, array $data): string
    {
        $level = ob_get_level();
        ob_start();
        try {
            require $path;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    /**
     * Sanitizes strings for safe HTML output, preventing Cross-Site Scripting (XSS).
     *
     * @param string|null $value Potentially unsafe string input.
     * @return string HTML-escaped string.
     */
    public function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
