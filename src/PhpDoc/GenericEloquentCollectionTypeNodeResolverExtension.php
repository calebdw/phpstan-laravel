<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\PhpDoc;

use Illuminate\Database\Eloquent\Collection;
use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;

use function count;

/**
 * Interprets the older array-shorthand docblock style:
 *
 * \Illuminate\Database\Eloquent\Collection|\App\Account[] $accounts
 *
 * and transforms it into the generic equivalent:
 *
 * \Illuminate\Database\Eloquent\Collection<int, \App\Account> $accounts
 *
 * so that the element type survives, both for analysis and for editor
 * completion, without the annotation having to be rewritten.
 *
 * @see https://gist.github.com/ondrejmirtes/56af016d0595788d5400b8dfb6520adc
 *      for the original sketch of this approach
 */
class GenericEloquentCollectionTypeNodeResolverExtension implements TypeNodeResolverExtension
{
    public function __construct(private TypeNodeResolver $typeNodeResolver)
    {
    }

    public function resolve(TypeNode $typeNode, NameScope $nameScope): Type|null
    {
        if (! $typeNode instanceof UnionTypeNode || count($typeNode->types) !== 2) {
            return null;
        }

        $arrayTypeNode      = null;
        $identifierTypeNode = null;
        foreach ($typeNode->types as $innerTypeNode) {
            if ($innerTypeNode instanceof ArrayTypeNode) {
                $arrayTypeNode = $innerTypeNode;
                continue;
            }

            if (! ($innerTypeNode instanceof IdentifierTypeNode)) {
                continue;
            }

            $identifierTypeNode = $innerTypeNode;
        }

        if ($arrayTypeNode === null || $identifierTypeNode === null) {
            return null;
        }

        $identifierTypeName = $nameScope->resolveStringName($identifierTypeNode->name);
        if ($identifierTypeName !== Collection::class) {
            return null;
        }

        $innerArrayTypeNode = $arrayTypeNode->type;
        if (! $innerArrayTypeNode instanceof IdentifierTypeNode) {
            return null;
        }

        $resolvedInnerArrayType = $this->typeNodeResolver->resolve($innerArrayTypeNode, $nameScope);

        return new GenericObjectType($identifierTypeName, [
            new IntegerType(),
            $resolvedInnerArrayType,
        ]);
    }
}
