<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\Methods;

use CalebDW\PhpstanLaravel\Support\ColumnHelper;
use Illuminate\Support\Enumerable;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function array_reverse;

/**
 * groupBy() returns a collection of collections, nested one level per grouper.
 *
 * The stub cannot express that, since the nesting depth depends on how many
 * groupers were passed, so it gives up and widens the values to mixed as soon
 * as an array is involved.
 */
final class EnumerableGroupByExtension implements DynamicMethodReturnTypeExtension
{
    public function __construct(private ColumnHelper $columnHelper)
    {
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
        $class        = $calledOnType->getObjectClassNames()[0] ?? null;

        if ($class === null) {
            return null;
        }

        $valueType = $calledOnType->getTemplateType(Enumerable::class, 'TValue');

        // groupBy() builds each group with newInstance(), so every level is the
        // same class as the receiver. Built innermost first.
        $type = new GenericObjectType($class, [
            $this->getInnerKeyType($calledOnType, $methodCall, $scope),
            $valueType,
        ]);

        foreach (array_reverse($this->getGroupers($groupByArg, $scope)) as $grouper) {
            $type = new GenericObjectType($class, [
                $this->getGroupKeyType($valueType, $grouper, $scope),
                $type,
            ]);
        }

        return $type;
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
