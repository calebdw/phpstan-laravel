<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Types;

use CalebDW\PhpstanLaravel\Support\BuilderHelper;
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

final class RelationOfTypeNodeResolverExtension implements TypeNodeResolverExtension
{
    public function __construct(
        private TypeNodeResolver $typeNodeResolver,
        private BuilderHelper $builderHelper,
    ) {
    }

    public function resolve(TypeNode $typeNode, NameScope $nameScope): Type|null
    {
        if (
            ! $typeNode instanceof GenericTypeNode
            || $typeNode->type->name !== 'relation-of'
            || count($typeNode->genericTypes) !== 2
        ) {
            return null;
        }

        $modelType = $this->typeNodeResolver->resolve($typeNode->genericTypes[0], $nameScope);

        if ((new ObjectType(Model::class))->isSuperTypeOf($modelType)->no() || $modelType instanceof NeverType) {
            return null;
        }

        $relationNames = $this->typeNodeResolver->resolve($typeNode->genericTypes[1], $nameScope);

        if (! $relationNames->isString()->yes() || $relationNames instanceof NeverType) {
            return null;
        }

        return new RelationOfType($modelType, $relationNames, $this->builderHelper);
    }
}
