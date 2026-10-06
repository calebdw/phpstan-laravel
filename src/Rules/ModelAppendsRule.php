<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Schema\SchemaDependencyTracker;
use CalebDW\PhpstanLaravel\Support\ModelPropertyHelper;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

use function sprintf;

/**
 * This rule validates that properties in the $appends array
 * both exist in the model and are computed properties.
 *
 * Accessors (attributes that modify the value of a database field)
 * **are not** considered computed properties and should not
 * be in the $appends or they will always be null.
 *
 * @implements Rule<Property>
 */
final class ModelAppendsRule implements Rule
{
    public function __construct(
        private ModelPropertyHelper $modelPropertyHelper,
        private SchemaDependencyTracker $schemaDependencyTracker,
    ) {
    }

    public function getNodeType(): string
    {
        return Property::class;
    }

    /**
     * @param  Scope&DependencyTracker $scope
     *
     * @return RuleError[]
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->props[0]->name->toString() !== 'appends') {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        if (! $classReflection?->is(Model::class)) {
            return [];
        }

        $value = $node->props[0]->default;

        if (! $value instanceof Array_) {
            return [];
        }

        // The model's own file does not depend on the model as a class, so the
        // declaration tracked below every column read would not reach it.
        $this->schemaDependencyTracker->trackScope($scope);

        $errors = [];

        foreach ($value->items as $appended) {
            if (! $appended->value instanceof String_) {
                continue;
            }

            $name = $appended->value->value;

            $hasDatabaseProperty = $this->modelPropertyHelper->hasDatabaseProperty($classReflection, $name);
            $hasAccessor         = $this->modelPropertyHelper->hasAccessor($classReflection, $name);

            if ($hasDatabaseProperty) {
                $errors[] = RuleErrorBuilder::message(sprintf("Property '%s' is not a computed property, remove from \$appends.", $name))
                    ->identifier('laravel.modelAppends')
                    ->line($appended->getStartLine())
                    ->file($scope->getFile())
                    ->build();

                continue;
            }

            if ($hasAccessor) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf("Property '%s' does not exist in model.", $name))
                ->identifier('laravel.modelAppends')
                ->line($appended->getStartLine())
                ->file($scope->getFile())
                ->build();
        }

        return $errors;
    }
}
