<?php

namespace Magma\phpstan;

use PHPStan\Rules\Rule;
use PHPStan\Analyser\Scope;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;

/**
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
