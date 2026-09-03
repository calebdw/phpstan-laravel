<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\ReturnTypes\StaticMethods;

use Illuminate\Support\Facades\Date;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function now;

class DateExtension implements DynamicStaticMethodReturnTypeExtension
{
    // if true, then method returns nullable type
    private const array METHODS = [
        'create' => false,
        'createFromDate' => false,
        'createFromFormat' => true,
        'createFromTime' => false,
        'createFromTimeString' => false,
        'createFromTimestamp' => false,
        'createFromTimestampMs' => false,
        'createFromTimestampUTC' => false,
        'createMidnightDate' => false,
        'createSafe' => true,
        'fromSerialized' => false,
        'getTestNow' => true,
        'instance' => false,
        'make' => true,
        'maxValue' => false,
        'minValue' => false,
        'now' => false,
        'parse' => false,
        'today' => false,
        'tomorrow' => false,
        'yesterday' => false,
    ];

    public function getClass(): string
    {
        return Date::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return isset(self::METHODS[$methodReflection->getName()]);
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
    {
        $dateType = new ObjectType(now()::class);

        if (self::METHODS[$methodReflection->getName()] === true) {
            return TypeCombinator::addNull($dateType);
        }

        return $dateType;
    }
}
