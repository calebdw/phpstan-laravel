<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use Illuminate\Database\Eloquent\Relations\Relation;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function array_merge;
use function array_unique;
use function array_values;
use function explode;
use function preg_match;
use function sprintf;

final class RelationExistenceHelper
{
    private ObjectType $relationType;

    public function __construct(private ModelRuleHelper $modelRuleHelper)
    {
        $this->relationType = new ObjectType(Relation::class);
    }

    /** @return RuleError[] */
    public function check(Type $relations, Type $modelType, Node $node, Scope $scope, bool $aggregate = false, bool $constrained = false): array
    {
        $errors = [];

        foreach (array_unique($this->relationNames($relations, $constrained)) as $name) {
            if ($aggregate && preg_match('/^(.*?)\s+as\s+/i', $name, $alias) === 1) {
                $name = $alias[1];
            }

            $calledOnType = $modelType;

            foreach ($aggregate ? [$name] : explode('.', $name) as $relationName) {
                $models = $this->modelRuleHelper->findModelReflectionsFromType($calledOnType);

                if ($models === []) {
                    break;
                }

                $next = [];

                foreach ($models as $model) {
                    if (
                        ! $model->hasMethod($relationName)
                        || ! $this->relationType->isSuperTypeOf(
                            ParametersAcceptorSelector::selectFromArgs($scope, [], $model->getMethod($relationName, $scope)->getVariants())->getReturnType(),
                        )->yes()
                    ) {
                        $errors[$model->getName() . '::' . $relationName] = RuleErrorBuilder::message(sprintf(
                            "Relation '%s' is not found in %s model.",
                            $relationName,
                            $model->getName(),
                        ))->identifier('laravel.relationExistence')->line($node->getStartLine())->build();

                        continue;
                    }

                    $next[] = $scope->getType(new MethodCall(new Node\Expr\New_(new Node\Name\FullyQualified($model->getName())), $relationName));
                }

                if ($next === []) {
                    break;
                }

                $calledOnType = TypeCombinator::union(...$next);
            }
        }

        return array_values($errors);
    }

    /**
     * A column selection such as `posts:id` is parsed only for a bare name or a
     * nested array. A name paired with a callback is used verbatim.
     *
     * @return string[]
     */
    private function relationNames(Type $type, bool $constrained, string $prefix = ''): array
    {
        $names = [];

        foreach ($type->getConstantStrings() as $name) {
            $names[] = $prefix . $this->relationName($name->getValue(), $constrained);
        }

        foreach ($type->getConstantArrays() as $array) {
            foreach ($array->getKeyTypes() as $index => $key) {
                $value = $array->getValueTypes()[$index];

                if ($key->isString()->yes()) {
                    foreach ($key->getConstantStrings() as $constant) {
                        $segment = $constant->getValue();
                        $parsed  = explode(':', $segment)[0];
                        $names[] = $prefix . ($value->isArray()->no() ? $segment : $parsed);

                        if (! $value->isArray()->yes()) {
                            continue;
                        }

                        $names = array_merge($names, $this->relationNames($value, false, $prefix . $parsed . '.'));
                    }
                } else {
                    foreach ($value->getConstantStrings() as $name) {
                        $names[] = $prefix . $this->relationName($name->getValue(), false);
                    }
                }
            }
        }

        return $names;
    }

    private function relationName(string $name, bool $constrained): string
    {
        return $constrained ? $name : explode(':', $name)[0];
    }
}
