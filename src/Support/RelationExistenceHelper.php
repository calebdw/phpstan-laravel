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
    public function check(Type $relations, Type $modelType, Node $node, Scope $scope, bool $aggregate = false): array
    {
        $errors = [];

        foreach (array_unique($this->relationNames($relations)) as $name) {
            $name = explode(':', $name)[0];

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

    /** @return string[] */
    private function relationNames(Type $type, string $prefix = ''): array
    {
        $names = [];

        foreach ($type->getConstantStrings() as $name) {
            $names[] = $prefix . $name->getValue();
        }

        foreach ($type->getConstantArrays() as $array) {
            foreach ($array->getKeyTypes() as $index => $key) {
                $value = $array->getValueTypes()[$index];

                if ($key->isString()->yes()) {
                    foreach ($key->getConstantStrings() as $constant) {
                        $name    = $prefix . $constant->getValue();
                        $names[] = $name;

                        if (! $value->isArray()->yes()) {
                            continue;
                        }

                        $names = array_merge($names, $this->relationNames($value, explode(':', $name)[0] . '.'));
                    }
                } else {
                    foreach ($value->getConstantStrings() as $name) {
                        $names[] = $prefix . $name->getValue();
                    }
                }
            }
        }

        return $names;
    }
}
