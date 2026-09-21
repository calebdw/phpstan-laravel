<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Types;

use CalebDW\PhpstanLaravel\Support\CollectionHelper;
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
use function in_array;

final class CollectionOfTypeNodeResolverExtension implements TypeNodeResolverExtension
{
    private readonly ObjectType $modelType;

    public function __construct(
        private TypeNodeResolver $typeNodeResolver,
        private CollectionHelper $collectionHelper,
    ) {
        $this->modelType = new ObjectType(Model::class);
    }

    public function resolve(TypeNode $typeNode, NameScope $nameScope): Type|null
    {
        if (! $typeNode instanceof GenericTypeNode || $typeNode->type->name !== 'collection-of') {
            return null;
        }

        $genericTypes = $typeNode->genericTypes;
        $count        = count($genericTypes);

        if (! in_array($count, [1, 2], true)) {
            return null;
        }

        $keyType   = $count === 2 ? $this->typeNodeResolver->resolve($genericTypes[0], $nameScope) : null;
        $modelType = $this->typeNodeResolver->resolve($genericTypes[$count - 1], $nameScope);

        if ($this->modelType->isSuperTypeOf($modelType)->no() || $modelType instanceof NeverType) {
            return null;
        }

        return new CollectionOfType($modelType, $this->collectionHelper, $keyType);
    }
}
