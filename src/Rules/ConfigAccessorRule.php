<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\ConfigHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Config;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

use function array_keys;
use function count;
use function sprintf;

/**
 * Catches the config repository's typed accessors being called for keys
 * that do not hold the required type. Each throws an
 * InvalidArgumentException at runtime, as none of them coerce.
 *
 * @implements Rule<Node\Expr\CallLike>
 */
final class ConfigAccessorRule implements Rule
{
    /**
     * The wording mirrors the runtime exception, so the reported message
     * matches what the call would actually throw.
     */
    private const array REQUIREMENTS = [
        'array'      => 'an array',
        'collection' => 'an array',
        'string'     => 'a string',
        'integer'    => 'an integer',
        'float'      => 'a float',
        'boolean'    => 'a boolean',
    ];

    public function __construct(
        private ConfigHelper $configHelper,
        private CallHelper $callHelper,
        private TypeHelper $typeHelper,
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof MethodCall && ! $node instanceof StaticCall) {
            return [];
        }

        if (! $this->isConfigCall($node, $scope)) {
            return [];
        }

        $args = $node->getArgs();

        if (count($args) === 0) {
            return [];
        }

        $errors = [];

        foreach ($this->callHelper->matchingNames($node, $scope, array_keys(self::REQUIREMENTS)) as $method) {
            foreach ($this->typeHelper->constantStrings($scope->getType($args[0]->value)) as $key) {
                $type = $this->configHelper->getKeyType($key, $scope);

                // A key neither the container nor the parser knows about could
                // hold anything, so there is nothing to report.
                if ($type === null || ! $this->accepts($type, $method)->no()) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf(
                    "Config key '%s' is %s, but '%s' requires %s.",
                    $key,
                    $type->describe(VerbosityLevel::typeOnly()),
                    $method,
                    self::REQUIREMENTS[$method],
                ))
                    ->identifier('laravel.configAccessor')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /** None of the accessors coerce, so each maps to a strict check. */
    private function accepts(Type $type, string $method): TrinaryLogic
    {
        return match ($method) {
            'string' => $type->isString(),
            'integer' => $type->isInteger(),
            'float' => $type->isFloat(),
            'boolean' => $type->isBoolean(),
            default => $type->isArray(),
        };
    }

    private function isConfigCall(MethodCall|StaticCall $node, Scope $scope): bool
    {
        return $this->callHelper->isCalledOn($node, $scope, [Config::class, Repository::class]);
    }
}
