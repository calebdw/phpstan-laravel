<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Contracts\Foundation\Application;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

use function array_intersect;
use function in_array;
use function is_string;

/** @implements Rule<MethodCall> */
class OctaneCompatibilityRule implements Rule
{
    private NodeFinder $nodeFinder;

    public function __construct(
        private CallHelper $callHelper,
        private TypeHelper $typeHelper,
    ) {
        $this->nodeFinder = new NodeFinder();
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @return RuleError[] */
    public function processNode(Node $node, Scope $scope): array
    {
        $methods = $this->callHelper->matchingNames($node, $scope, ['singleton', 'bind']);

        if ($methods === []) {
            return [];
        }

        if (! $this->callHelper->isCalledOn($node, $scope, Application::class)) {
            return [];
        }

        $concrete = $node->getArg('concrete', 1)?->value;

        if (! $concrete instanceof Closure && ! $concrete instanceof ArrowFunction) {
            return [];
        }

        $params = $concrete->getParams();

        if ($params === []) {
            return $this->errors($this->thisAppNodes($concrete, $scope));
        }

        if (! in_array('singleton', $methods, true)) {
            return [];
        }

        $container = $params[0]->var;

        if (! $container instanceof Variable) {
            return [];
        }

        return $this->errors($this->containerInjectionNodes($concrete, $scope, $container));
    }

    /** @return Node[] */
    private function thisAppNodes(Closure|ArrowFunction $concrete, Scope $scope): array
    {
        return $this->nodeFinder->find($concrete, function (Node $node) use ($scope): bool {
            return $node instanceof PropertyFetch
                && $this->isVariable($node->var, 'this', $scope)
                && $this->propertyIs($node, 'app', $scope);
        });
    }

    /** @return Node[] */
    private function containerInjectionNodes(Closure|ArrowFunction $concrete, Scope $scope, Variable $container): array
    {
        return $this->nodeFinder->find($concrete, function (Node $node) use ($scope, $container): bool {
            if (! $node instanceof New_) {
                return false;
            }

            $args = $node->getArgs();

            if ($args === []) {
                return false;
            }

            $value = $args[0]->value;

            if ($value instanceof Variable) {
                return $this->sameVariable($value, $container, $scope);
            }

            if (! $value instanceof ArrayDimFetch || $value->dim === null || ! $value->var instanceof Variable) {
                return false;
            }

            if (! $this->sameVariable($value->var, $container, $scope)) {
                return false;
            }

            return array_intersect($this->typeHelper->constantStrings($scope->getType($value->dim)), ['request', 'config']) !== [];
        });
    }

    /**
     * @param  Node[] $nodes
     *
     * @return RuleError[]
     */
    private function errors(array $nodes): array
    {
        $errors = [];

        foreach ($nodes as $node) {
            $errors[] = $this->dependencyInjectionError($node);
        }

        return $errors;
    }

    private function isVariable(Expr $expr, string $name, Scope $scope): bool
    {
        return $expr instanceof Variable && $this->variableIs($expr, $name, $scope);
    }

    private function sameVariable(Variable $left, Variable $right, Scope $scope): bool
    {
        if (is_string($left->name) && is_string($right->name)) {
            return $left->name === $right->name;
        }

        return $this->variableNames($left, $scope) !== []
            && array_intersect($this->variableNames($left, $scope), $this->variableNames($right, $scope)) !== [];
    }

    /** @return list<string> */
    private function variableNames(Variable $variable, Scope $scope): array
    {
        if (is_string($variable->name)) {
            return [$variable->name];
        }

        return $this->typeHelper->constantStrings($scope->getType($variable->name));
    }

    private function variableIs(Variable $variable, string $name, Scope $scope): bool
    {
        return in_array($name, $this->variableNames($variable, $scope), true);
    }

    private function propertyIs(PropertyFetch $fetch, string $name, Scope $scope): bool
    {
        if ($fetch->name instanceof Identifier) {
            return $fetch->name->toString() === $name;
        }

        return in_array($name, $this->typeHelper->constantStrings($scope->getType($fetch->name)), true);
    }

    private function dependencyInjectionError(Node $node): RuleError
    {
        return RuleErrorBuilder::message('Consider using bind method instead or pass a closure.')
            ->identifier('laravel.octaneCompatibility')
            ->tip('See: https://laravel.com/docs/octane#dependency-injection-and-octane')
            ->line($node->getStartLine())
            ->build();
    }
}
