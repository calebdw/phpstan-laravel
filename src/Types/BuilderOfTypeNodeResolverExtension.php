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

final class BuilderOfTypeNodeResolverExtension implements TypeNodeResolverExtension
{
    public function __construct(
        private TypeNodeResolver $typeNodeResolver,
        private BuilderHelper $builderHelper,
    ) {
    }

    public function resolve(TypeNode $typeNode, NameScope $nameScope): Type|null
    {
        if (! $typeNode instanceof GenericTypeNode || $typeNode->type->name !== 'builder-of') {
            return null;
        }

        if (count($typeNode->genericTypes) !== 1) {
            return null;
        }

        $genericType = $this->typeNodeResolver->resolve($typeNode->genericTypes[0], $nameScope);

        if ((new ObjectType(Model::class))->isSuperTypeOf($genericType)->no()) {
            return null;
        }

        if ($genericType instanceof NeverType) {
            return null;
        }

        return new BuilderOfType($genericType, $this->builderHelper);
    }
}
