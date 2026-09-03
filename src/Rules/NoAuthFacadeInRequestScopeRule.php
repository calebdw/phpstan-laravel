<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

use function sprintf;

/** @implements Rule<StaticCall> */
class NoAuthFacadeInRequestScopeRule implements Rule
{
    public function __construct(
        private CallHelper $callHelper,
        private TypeHelper $typeHelper,
    ) {
    }

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @return list<IdentifierRuleError>
     *
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->typeHelper->isCalledOn($this->callHelper->receiverType($node, $scope), Auth::class)) {
            return [];
        }

        $methodName = $this->callHelper->matchingNames($node, $scope, ['check', 'user', 'guest'])[0] ?? null;

        if ($methodName === null) {
            return [];
        }

        $variable = $this->requestVariable($scope);

        if ($variable === null) {
            return [];
        }

        $message = match ($methodName) {
            'check' => 'Do not use Auth::check() in a class that has access to the request. Use $%s->user() !== null instead.',
            'user' => 'Do not use Auth::user() in a class that has access to the request. Use $%s->user() instead.',
            'guest' => 'Do not use Auth::guest() in a class that has access to the request. Use $%s->user() === null instead.',
            default => throw new ShouldNotHappenException(),
        };

        $replacement = $this->replacement($variable, $methodName);

        return [
            /** @phpstan-ignore method.internal (still experimental) */
            RuleErrorBuilder::message(sprintf($message, $variable))
                ->identifier('laravel.authInRequestScope.facade')
                ->fixNode($node, static fn () => $replacement)
                ->build(),
        ];
    }

    private function requestVariable(Scope $scope): string|null
    {
        if ($scope->isInClass() && $scope->getClassReflection()->is(Request::class)) {
            return 'this';
        }

        if (
            $scope->hasVariableType('request')->yes()
            && $this->typeHelper->isCalledOn($scope->getVariableType('request'), Request::class)
        ) {
            return 'request';
        }

        return null;
    }

    private function replacement(string $variable, string $methodName): Node
    {
        $var = new Node\Expr\Variable($variable);

        return match ($methodName) {
            'check' => new NotIdentical(new MethodCall($var, 'user', []), new Node\Expr\ConstFetch(new Name('null'))),
            'user' => new MethodCall($var, 'user', []),
            'guest' => new Identical(new MethodCall($var, 'user', []), new Node\Expr\ConstFetch(new Name('null'))),
            default => throw new ShouldNotHappenException(),
        };
    }
}
