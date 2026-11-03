<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use ArrayAccess;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\TrinaryLogic;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

#[AutowiredService]
final class ArrHelper
{
    public function hasOffset(Type $type, Type $key): TrinaryLogic
    {
        return $type->isArray()->and($type->hasOffsetValueType($key->toArrayKey()))
            ->or((new ObjectType(ArrayAccess::class))->isSuperTypeOf($type)->result->and($type->hasOffsetValueType($key)));
    }
}
