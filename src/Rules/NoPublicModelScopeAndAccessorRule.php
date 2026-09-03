<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Modifiers;
use PhpParser\Node;
use PHPStan\Analyser\Scope as AnalyserScope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

use function sprintf;
use function str_ends_with;
use function str_starts_with;

/** @implements Rule<InClassMethodNode> */
class NoPublicModelScopeAndAccessorRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, AnalyserScope $scope): array
    {
        $classReflection = $node->getClassReflection();

        if (! $classReflection->is(Model::class)) {
            return [];
        }

        $parentClass = $classReflection->getParentClass();

        if ($parentClass !== null && $parentClass->hasNativeMethod($node->getMethodReflection()->getName())) {
            return [];
        }

        if ($this->isScopeMethod($node) && ! $node->getOriginalNode()->isProtected()) {
            return [
                $this->visibilityError(
                    $node,
                    $scope,
                    "Local query scope method '%s' should be declared as protected.",
                    'laravel.modelMethodVisibility.scope',
                ),
            ];
        }

        if ($this->isAccessorMethod($node) && ! $node->getOriginalNode()->isProtected()) {
            return [
                $this->visibilityError(
                    $node,
                    $scope,
                    "Model accessor method '%s' should be declared as protected.",
                    'laravel.modelMethodVisibility.accessor',
                ),
            ];
        }

        return [];
    }

    private function visibilityError(
        InClassMethodNode $node,
        AnalyserScope $scope,
        string $message,
        string $identifier,
    ): IdentifierRuleError {
        /** @phpstan-ignore method.internal (still experimental) */
        return RuleErrorBuilder::message(sprintf($message, $node->getMethodReflection()->getName()))
            ->identifier($identifier)
            ->line($node->getStartLine())
            ->file($scope->getFile())
            ->fixNode($node->getOriginalNode(), static function (Node $node) {
                $node->flags &= ~Modifiers::PUBLIC;
                $node->flags &= ~Modifiers::PRIVATE;
                $node->flags |= Modifiers::PROTECTED;

                return $node;
            })
            ->build();
    }

    private function isScopeMethod(InClassMethodNode $method): bool
    {
        $methodName = $method->getMethodReflection()->getName();

        if (str_starts_with($methodName, 'scope')) {
            $original = $method->getOriginalNode();

            if ($original->params !== [] && $original->params[0]->type !== null) {
                $firstParamType = $original->params[0]->type;

                if ($firstParamType instanceof Node\Name) {
                    $typeName = $firstParamType->toString();

                    if ($typeName === 'Builder' || str_ends_with($typeName, '\Builder')) {
                        return true;
                    }
                }
            }
        }

        foreach ($method->getOriginalNode()->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === 'Illuminate\Database\Eloquent\Attributes\Scope') {
                    return true;
                }
            }
        }

        return false;
    }

    private function isAccessorMethod(InClassMethodNode $method): bool
    {
        $returnType = $method->getMethodReflection()->getVariants()[0]->getReturnType();

        return (new ObjectType(Attribute::class))->isSuperTypeOf($returnType)->yes();
    }
}
