<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

use function array_reverse;
use function count;

/**
 * groupBy() returns a collection of collections, nested one level per grouper.
 *
 * The stub cannot express that, since the nesting depth depends on how many
 * groupers were passed, so it gives up and widens the values to mixed as soon
 * as an array is involved.
 */
final class EnumerableGroupByExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private ColumnHelper $columnHelper,
        private CollectionHelper $collectionHelper,
    ) {
    }

    public function getClass(): string
    {
        return Enumerable::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'groupBy';
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type|null
    {
        $groupByArg = $methodCall->getArg('groupBy', 0);

        if ($groupByArg === null) {
            return null;
        }

        $calledOnType = $scope->getType($methodCall->var);

        if ($calledOnType->getObjectClassNames() === []) {
            return null;
        }

        $calledOnTypes = $calledOnType instanceof UnionType ? $calledOnType->getTypes() : [$calledOnType];
        $groupers      = array_reverse($this->getGroupers($groupByArg, $scope));
        $results       = [];

        foreach ($calledOnTypes as $receiver) {
            $valueType = $receiver->getTemplateType(Enumerable::class, 'TValue');
            $innerKey  = $this->getInnerKeyType($receiver, $methodCall, $scope);

            foreach ($receiver->getObjectClassReflections() as $reflection) {
                $class       = $reflection->getName();
                $nestedClass = $reflection->is(LazyCollection::class) ? Collection::class : $class;
                $type        = $this->collectionHelper->generic($nestedClass, $innerKey, $valueType);

                foreach ($groupers as $index => $grouper) {
                    $type = $this->collectionHelper->generic(
                        $index === count($groupers) - 1 ? $class : $nestedClass,
                        $this->getGroupKeyType($valueType, $grouper, $scope),
                        $type,
                    );
                }

                $results[] = $type;
            }
        }

        return $results === [] ? null : TypeCombinator::union(...$results);
    }

    /**
     * One grouper per level of nesting.
     *
     * An array argument means successive levels here, which is the opposite of
     * pluck() and keyBy(), where an array is the segments of a single key.
     *
     * @return list<Arg>
     */
    private function getGroupers(Arg $groupByArg, Scope $scope): array
    {
        $expr = $groupByArg->value;

        // A callable is a single grouper even when it is an array callable.
        if (! $expr instanceof Array_ || $scope->getType($expr)->isCallable()->yes()) {
            return [$groupByArg];
        }

        $groupers = [];

        foreach ($expr->items as $item) {
            // Spreading hides how many levels there are.
            if ($item->unpack) {
                return [$groupByArg];
            }

            $groupers[] = new Arg($item->value);
        }

        return $groupers === [] ? [$groupByArg] : $groupers;
    }

    /** The keys of the innermost collection, which $preserveKeys decides. */
    private function getInnerKeyType(Type $calledOnType, MethodCall $methodCall, Scope $scope): Type
    {
        $preserveArg = $methodCall->getArg('preserveKeys', 1);

        if ($preserveArg === null) {
            return new IntegerType();
        }

        $preserve = $scope->getType($preserveArg->value);
        $keyType  = $calledOnType->getTemplateType(Enumerable::class, 'TKey');

        return match (true) {
            $preserve->isTrue()->yes() => $keyType,
            $preserve->isTrue()->maybe() => TypeCombinator::union($keyType, new IntegerType()),
            default => new IntegerType(),
        };
    }

    private function getGroupKeyType(Type $valueType, Arg $grouper, Scope $scope): Type
    {
        return $this->columnHelper->normalizeGroupKey(
            $this->columnHelper->getKeyType($valueType, $grouper, $scope),
        );
    }
}
