<?php

namespace Magma\phpstan;

use PHPStan\Rules\Rule;
use PHPStan\Analyser\Scope;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;

/**
 * Title: Superglobal Ban Rule (PHPStan)
 *
 * Purpose:
 * - Analyzes AST nodes during static analysis to detect and flag direct access to PHP superglobals.
 *
 * Why this design:
 * - Enforces the Dependency Inversion Principle (DIP). By physically banning $_POST, $_GET, etc., developers are forced to use the injected Request object, which is essential for testability, stateless execution, and PSR-7 compliance.
 *
 * Teaching notes:
 * - AST-level enforcement prevents technical debt from leaking in during code reviews. It is much more resilient than regex-based linting.
 *
 * @implements Rule<Variable>
 */
class SuperglobalBanRule implements Rule
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

    public function getNodeType(): string
    {
        return Variable::class;
    }

    /**
     * @param Variable $node
     * @param Scope $scope
     * @return array<string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!is_string($node->name)) {
            return [];
        }

        if (isset($this->bannedSuperglobals[$node->name])) {
            return [
                sprintf('Direct access to superglobal $%s is banned. Use the core HTTP abstractions instead.', $node->name)
            ];
        }

        return [];
    }
}
