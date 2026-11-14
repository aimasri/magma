<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Title: Superglobal Ban Rule (PHPStan AST Guard)
 *
 * Purpose:
 * - Analyzes PHP AST nodes during static analysis to detect and reject direct access to raw PHP superglobals.
 *
 * Why / Why this design:
 * - Enforces Dependency Inversion and HTTP/Session boundary encapsulation (`RequestInterface`, `SessionInterface`, `Config`) at the AST level rather than brittle regex checks, while living inside `tests/PHPStan/` (`autoload-dev`) so `magma/` remains 100% vendor-free.
 *
 * Teaching notes:
 * - Registered in `phpstan.neon` on the `lava` testing branch. Any new controller, service, or core class touching `$_POST`, `$_GET`, or `$_SESSION` fails static analysis immediately.
 *
 * @implements Rule<Variable>
 */
final class SuperglobalBanRule implements Rule
{
    /**
     * @var array<string, bool>
     */
    private array $bannedSuperglobals = [
        '_POST' => true,
        '_GET' => true,
        '_REQUEST' => true,
        '_SESSION' => true,
        '_FILES' => true,
        '_SERVER' => true,
        '_COOKIE' => true,
        '_ENV' => true,
    ];

    /**
     * Canonical boundary adapters whose sole responsibility is encapsulating PHP superglobals.
     *
     * @var array<string, bool>
     */
    private array $allowedBoundaryAdapters = [
        \Magma\http\Request::class => true,
        \Magma\http\Session::class => true,
        \Magma\config\Config::class => true,
    ];

    /**
     * Returns the PhpParser AST node class inspected by this rule.
     */
    public function getNodeType(): string
    {
        return Variable::class;
    }

    /**
     * Inspects variable nodes and reports an identifier error if a banned superglobal is accessed directly.
     *
     * @param Variable $node
     * @param Scope $scope
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!is_string($node->name)) {
            return [];
        }

        $className = $scope->getClassReflection()?->getName();
        if ($className !== null && isset($this->allowedBoundaryAdapters[$className])) {
            return [];
        }

        if (isset($this->bannedSuperglobals[$node->name])) {
            return [
                RuleErrorBuilder::message(
                    sprintf('Direct access to superglobal $%s is banned. Use the core HTTP abstractions instead.', $node->name)
                )->identifier('magma.superglobal')->build(),
            ];
        }

        return [];
    }
}
