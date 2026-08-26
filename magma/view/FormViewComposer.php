<?php

declare(strict_types=1);

namespace Magma\view;

/**
 * Title: Form View Composer
 *
 * Purpose:
 * - Provides a unified server-side form composition contract that allows identical view partials
 *   and input templates to render seamlessly in both "Create" (empty DTO) and "Edit/View" (hydrated DTO) modes.
 * - Handles HTTP method spoofing, form action URLs, CSRF token binding, submit button labels, and
 *   coalesced value resolution (Old Input -> Entity Value -> Default Value).
 *
 * Why / Why this design:
 * - DRY View Templates: Eliminates redundant duplication between create and edit form views
 *   (e.g., maintaining separate 500-line create_form.php and edit_form.php files).
 * - Separation of Concerns (SoC): View templates remain 100% declarative without requiring inline
 *   ternary logic (`<?= isset($item) ? $item->name : (old('name') ?? '') ?>`).
 * - Security & Method Spoofing: Standardizes HTML form handling for RESTful verbs (PUT, PATCH, DELETE)
 *   via hidden `_method` inputs.
 *
 * Teaching notes:
 * - Instantiate this composer inside your controller action or FormViewModel and pass the resulting
 *   composed dictionary or instance directly into the view data array.
 */
class FormViewComposer
{
    /** @var object|array<string, mixed>|null The underlying domain entity or DTO. */
    private object|array|null $entity;

    /** @var string The form submission target URL. */
    private string $actionUrl;

    /** @var string The logical HTTP verb (e.g., 'POST', 'PUT', 'PATCH', 'GET', 'DELETE'). */
    private string $httpMethod;

    /** @var array<string, mixed> Flash data from previous validation failure. */
    private array $oldInput;

    /** @var array<string, string> Field validation error messages. */
    private array $errors;

    /** @var string|null CSRF token value. */
    private ?string $csrfToken;

    /** @var string Submit button text label. */
    private string $submitLabel;

    /** @var string Form heading / page title. */
    private string $formTitle;

    /** @var bool Flag indicating whether the form is in edit mode. */
    private bool $isEdit;

    /**
     * Initializes the FormViewComposer with entity data, routing context, and session flash state.
     *
     * Execution Flow:
     * 1. Detect edit mode based on whether an entity exists and has an ID.
     * 2. Configure HTTP method and spoofed verbs.
     * 3. Set sensible default submit labels and titles if not explicitly provided.
     *
     * @param object|array<string, mixed>|null $entity Entity or DTO instance (null for create mode).
     * @param string $actionUrl Form action submission endpoint.
     * @param string $httpMethod Desired REST verb (POST, PUT, PATCH, GET, DELETE). Defaults to POST/PUT based on entity state.
     * @param array<string, mixed> $oldInput Repopulation array from session flash.
     * @param array<string, string> $errors Validation errors array from session flash.
     * @param string|null $csrfToken Active CSRF session token.
     * @param string|null $submitLabel Custom button label (auto-generated if null).
     * @param string|null $formTitle Custom form title (auto-generated if null).
     */
    public function __construct(
        object|array|null $entity = null,
        string $actionUrl = '',
        string $httpMethod = '',
        array $oldInput = [],
        array $errors = [],
        ?string $csrfToken = null,
        ?string $submitLabel = null,
        ?string $formTitle = null
    ) {
        $this->entity = $entity;
        $this->actionUrl = $actionUrl;
        $this->oldInput = $oldInput;
        $this->errors = $errors;
        $this->csrfToken = $csrfToken;

        $this->isEdit = $this->determineEditMode($entity);

        if (empty($httpMethod)) {
            $this->httpMethod = $this->isEdit ? 'PUT' : 'POST';
        } else {
            $this->httpMethod = strtoupper($httpMethod);
        }

        $this->submitLabel = $submitLabel ?? ($this->isEdit ? 'Save Changes' : 'Create Record');
        $this->formTitle = $formTitle ?? ($this->isEdit ? 'Edit Record' : 'New Record');
    }

    /**
     * Static factory method for fluid builder instantiation.
     *
     * @param object|array<string, mixed>|null $entity
     * @param array{action?: string, method?: string, old?: array<string, mixed>, errors?: array<string, string>, csrf_token?: string, submit_label?: string, title?: string} $options
     * @return self
     */
    public static function make(object|array|null $entity = null, array $options = []): self
    {
        return new self(
            $entity,
            $options['action'] ?? '',
            $options['method'] ?? '',
            $options['old'] ?? [],
            $options['errors'] ?? [],
            $options['csrf_token'] ?? null,
            $options['submit_label'] ?? null,
            $options['title'] ?? null
        );
    }

    /**
     * Determines whether the form is in edit mode.
     *
     * @return bool True for edit/update mode, false for create mode.
     */
    public function isEditMode(): bool
    {
        return $this->isEdit;
    }

    /**
     * Gets the form action target URL.
     *
     * @return string
     */
    public function getActionUrl(): string
    {
        return $this->actionUrl;
    }

    /**
     * Gets the actual HTML form method attribute value ('GET' or 'POST').
     *
     * @return string
     */
    public function getFormMethod(): string
    {
        return $this->httpMethod === 'GET' ? 'GET' : 'POST';
    }

    /**
     * Gets the spoofed REST method if the form uses PUT, PATCH, or DELETE.
     *
     * @return string|null The spoofed verb (e.g., 'PUT', 'PATCH', 'DELETE'), or null if standard POST/GET.
     */
    public function getSpoofedMethod(): ?string
    {
        if (in_array($this->httpMethod, ['PUT', 'PATCH', 'DELETE'], true)) {
            return $this->httpMethod;
        }
        return null;
    }

    /**
     * Generates the hidden HTML `<input type="hidden" name="_method" value="...">` tag if method spoofing is required.
     *
     * @return string
     */
    public function renderMethodSpoofInput(): string
    {
        $spoofed = $this->getSpoofedMethod();
        if ($spoofed === null) {
            return '';
        }
        return '<input type="hidden" name="_method" value="' . htmlspecialchars($spoofed, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Generates the hidden HTML CSRF input field.
     *
     * @return string
     */
    public function renderCsrfInput(): string
    {
        if (empty($this->csrfToken)) {
            return '';
        }
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($this->csrfToken, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Resolves a field value using strict coalescing precedence:
     * 1. Old Flash Input (from previous validation redirect).
     * 2. Hydrated Entity property / array key / getter method.
     * 3. Provided fallback default.
     *
     * @param string $field The field name.
     * @param mixed $default Fallback value if no old input or entity value exists.
     * @return mixed Coalesced field value.
     */
    public function getValue(string $field, mixed $default = null): mixed
    {
        // 1. Check old session flash input
        if (array_key_exists($field, $this->oldInput)) {
            return $this->oldInput[$field];
        }

        // 2. Check underlying entity
        if ($this->entity !== null) {
            if (is_array($this->entity) && array_key_exists($field, $this->entity)) {
                return $this->entity[$field];
            }

            if (is_object($this->entity)) {
                // Check getter method (e.g. getName())
                $getter = 'get' . str_replace('_', '', ucwords($field, '_'));
                if (method_exists($this->entity, $getter)) {
                    return $this->entity->{$getter}();
                }

                // Check isGetter (e.g. isActive())
                $isGetter = 'is' . str_replace('_', '', ucwords($field, '_'));
                if (method_exists($this->entity, $isGetter)) {
                    return $this->entity->{$isGetter}();
                }

                // Check public property
                if (isset($this->entity->{$field})) {
                    return $this->entity->{$field};
                }
            }
        }

        // 3. Fallback default
        return $default;
    }

    /**
     * Checks whether a validation error exists for a specific field.
     *
     * @param string $field Field name.
     * @return bool True if error exists.
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Retrieves the error message for a specific field.
     *
     * @param string $field Field name.
     * @return string|null Error message string, or null if no error.
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Returns all validation errors.
     *
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Gets the submit button label text.
     *
     * @return string
     */
    public function getSubmitLabel(): string
    {
        return $this->submitLabel;
    }

    /**
     * Gets the form title.
     *
     * @return string
     */
    public function getFormTitle(): string
    {
        return $this->formTitle;
    }

    /**
     * Exports the form state as a structured associative array for view consumption.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'isEdit' => $this->isEdit,
            'actionUrl' => $this->actionUrl,
            'formMethod' => $this->getFormMethod(),
            'spoofedMethod' => $this->getSpoofedMethod(),
            'submitLabel' => $this->submitLabel,
            'formTitle' => $this->formTitle,
            'errors' => $this->errors,
            'oldInput' => $this->oldInput,
            'composer' => $this,
        ];
    }

    /**
     * Internal helper to determine if an entity represents an existing record.
     *
     * @param object|array<string, mixed>|null $entity
     * @return bool
     */
    private function determineEditMode(object|array|null $entity): bool
    {
        if ($entity === null) {
            return false;
        }

        if (is_array($entity)) {
            return !empty($entity['id']);
        }

        if (is_object($entity)) {
            if (method_exists($entity, 'getId')) {
                return !empty($entity->getId());
            }
            if (isset($entity->id)) {
                return !empty($entity->id);
            }
        }

        return true;
    }
}
