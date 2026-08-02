<?php

namespace Magma\validation;

use Magma\validation\rules\RequiredRule;
use Magma\validation\rules\EmailRule;
use Magma\validation\rules\MinRule;
use Magma\validation\rules\MaxRule;
use Magma\validation\rules\NumericRule;
use Magma\validation\rules\ConfirmedRule;

/**
 * Extensible Rule-Based Validation Engine
 *
 * Purpose:
 * - Offer an expressive, pipe-delimited rule syntax (e.g., `required|email|min:8`) 
 *   for common data constraints.
 * - Dynamically parse rules and execute corresponding validation methods from a registry.
 * - Collect and structure all validation failures into an error array.
 *
 * Why/Why this design:
 * - By transitioning to a rule registry map of callables, the Validator adheres 
 *   strictly to the Open/Closed Principle. It is closed for modification (no need 
 *   to edit this class to add rules) but completely open for extension (via the `extend` method).
 *
 * Teaching notes:
 * - Each rule is now an invokable class object. This completely fixes static analysis 
 *   blind spots caused by `$this->$method()` dynamic dispatch.
 */
class Validator
{
    protected array $errors = [];
    protected array $data = []; // Store data for rules that need full context
    private array $rules = [];

    /**
     * Initializes the Validator with a default set of common validation rules.
     */
    public function __construct()
    {
        // Register default rules
        $this->extend('required', new RequiredRule());
        $this->extend('email', new EmailRule());
        $this->extend('min', new MinRule());
        $this->extend('max', new MaxRule());
        $this->extend('numeric', new NumericRule());
        $this->extend('confirmed', new ConfirmedRule());
    }

    /**
     * Registers a new validation rule callable.
     * 
     * @param string $name The rule name (e.g., 'unique')
     * @param callable $rule The callable validation logic.
     */
    public function extend(string $name, callable $rule): void
    {
        $this->rules[$name] = $rule;
    }

    /**
     * Executes the validation logic against a given data set.
     * 
     * Execution Flow:
     * 1. Iterate through the array of field rules.
     * 2. Split pipe-delimited rules (e.g., "required|min:8") into individual constraints.
     * 3. For each constraint, check if it contains parameters (separated by a colon).
     * 4. Look up the rule callable in the registry.
     * 5. Execute the callable, passing the field, value, params, and full data array.
     * 6. Stop processing rules for a specific field on the first failure.
     * 
     * Logic behind the logic:
     * - Breaking on the first failure per field prevents redundant error messages.
     * - Throwing a LogicException on an unrecognized rule prevents silent security bypasses.
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];
        $this->data = $data;

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $fieldRules = explode('|', $ruleString);

            foreach ($fieldRules as $ruleName) {
                $params = [];
                if (str_contains($ruleName, ':')) {
                    [$ruleName, $paramString] = explode(':', $ruleName);
                    $params = explode(',', $paramString);
                }

                if (isset($this->rules[$ruleName])) {
                    $ruleCallable = $this->rules[$ruleName];
                    $errorMessage = $ruleCallable($field, $value, $params, $data);
                    
                    if (is_string($errorMessage)) {
                        $this->addError($field, $errorMessage);
                        break; 
                    }
                } else {
                    throw new \LogicException(sprintf(
                        "Validation rule '%s' is not supported by %s. Did you make a typo?",
                        $ruleName,
                        self::class
                    ));
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Executes validation and throws a ValidationException if it fails.
     */
    public function validateOrFail(array $data, array $rules): void
    {
        if (!$this->validate($data, $rules)) {
            throw new ValidationException($this->getErrors());
        }
    }

    /**
     * Retrieves the array of collected validation errors.
     * 
     * @return array Map of field names to their respective error messages.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Adds an error message for a specific field if one does not already exist.
     * 
     * @param string $field The field that failed validation.
     * @param string $message The failure message.
     */
    protected function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }
}