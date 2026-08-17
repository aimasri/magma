<?php

declare(strict_types=1);

namespace Magma\view;

/**
 * Title: Abstract Form View Model
 *
 * Purpose:
 * - Serves as the base class for strongly-typed form view models.
 * - Encapsulates form presentation state, REST method spoofing, error mapping, and field value resolution.
 * - Replaces inline PHP logic inside view templates with clean object-oriented presenter accessors.
 *
 * Why / Why this design:
 * - Single Responsibility Principle (SRP): Isolates form presentation concerns (labels, validation error
 *   rendering, button texts, routing endpoints) from domain entities and persistence models.
 * - Testability: View logic (e.g., determining whether a toggle switch should be checked or what action URL
 *   to post to) can be thoroughly unit-tested without rendering HTML templates or mocking the HTTP pipeline.
 *
 * Teaching notes:
 * - Extend this class for each domain entity form (e.g. `StaffFormViewModel`, `MenuItemFormViewModel`).
 * - Pass the ViewModel instance directly to `TemplateEngine::render()`.
 */
abstract class AbstractFormViewModel extends ViewModel
{
    /** @var FormViewComposer The underlying form view composer instance. */
    protected FormViewComposer $composer;

    /**
     * Initializes the FormViewModel.
     *
     * @param FormViewComposer $composer Form composer handling value resolution and routing context.
     */
    public function __construct(FormViewComposer $composer)
    {
        $this->composer = $composer;
    }

    /**
     * Indicates whether the form is rendering in Edit mode (vs Create mode).
     *
     * @return bool True if editing existing record, false if creating.
     */
    public function isEdit(): bool
    {
        return $this->composer->isEditMode();
    }

    /**
     * Retrieves the form action endpoint URL.
     *
     * @return string
     */
    public function getAction(): string
    {
        return $this->composer->getActionUrl();
    }

    /**
     * Retrieves the HTML form method attribute value ('POST' or 'GET').
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->composer->getFormMethod();
    }

    /**
     * Retrieves the spoofed REST method ('PUT', 'PATCH', 'DELETE'), or null if standard POST.
     *
     * @return string|null
     */
    public function getSpoofedMethod(): ?string
    {
        return $this->composer->getSpoofedMethod();
    }

    /**
     * Renders the hidden `_method` spoof input element.
     *
     * @return string
     */
    public function renderMethodSpoof(): string
    {
        return $this->composer->renderMethodSpoofInput();
    }

    /**
     * Renders the hidden `csrf_token` input element.
     *
     * @return string
     */
    public function renderCsrf(): string
    {
        return $this->composer->renderCsrfInput();
    }

    /**
     * Resolves a field value from session flash, domain entity, or fallback default.
     *
     * @param string $field Field name.
     * @param mixed $default Fallback default.
     * @return mixed
     */
    public function getValue(string $field, mixed $default = null): mixed
    {
        return $this->composer->getValue($field, $default);
    }

    /**
     * Checks if a field has a validation error.
     *
     * @param string $field Field name.
     * @return bool
     */
    public function hasError(string $field): bool
    {
        return $this->composer->hasError($field);
    }

    /**
     * Retrieves the validation error message for a specific field.
     *
     * @param string $field Field name.
     * @return string|null
     */
    public function getError(string $field): ?string
    {
        return $this->composer->getError($field);
    }

    /**
     * Retrieves all validation error messages.
     *
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->composer->getErrors();
    }

    /**
     * Retrieves the submit button label.
     *
     * @return string
     */
    public function getSubmitLabel(): string
    {
        return $this->composer->getSubmitLabel();
    }

    /**
     * Retrieves the form heading title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->composer->getFormTitle();
    }

    /**
     * Converts the form view model into an array of variables for the template engine.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->composer->toArray(), [
            'isEdit' => $this->isEdit(),
            'action' => $this->getAction(),
            'method' => $this->getMethod(),
            'spoofedMethod' => $this->getSpoofedMethod(),
            'submitLabel' => $this->getSubmitLabel(),
            'title' => $this->getTitle(),
            'viewModel' => $this,
        ]);
    }
}
