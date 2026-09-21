<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Types;

use CalebDW\PhpstanLaravel\Support\FactoryHelper;
use Illuminate\Database\Eloquent\Model;
use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

use function count;

final class FactoryOfTypeNodeResolverExtension implements TypeNodeResolverExtension
{
    private readonly ObjectType $modelType;

    public function __construct(
        private TypeNodeResolver $typeNodeResolver,
        private FactoryHelper $factoryHelper,
    ) {
        $this->modelType = new ObjectType(Model::class);
    }

    public function resolve(TypeNode $typeNode, NameScope $nameScope): Type|null
    {
        if (
            ! $typeNode instanceof GenericTypeNode
            || $typeNode->type->name !== 'factory-of'
            || count($typeNode->genericTypes) !== 1
        ) {
            return null;
        }

        $modelType = $this->typeNodeResolver->resolve($typeNode->genericTypes[0], $nameScope);

        if ($this->modelType->isSuperTypeOf($modelType)->no() || $modelType instanceof NeverType) {
            return null;
        }

        return new FactoryOfType($modelType, $this->factoryHelper);
    }
}
