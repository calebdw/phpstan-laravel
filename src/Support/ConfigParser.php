<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Support;

use CalebDW\PhpstanLaravel\Internal\FileHelper;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\Parser\ParserErrorsException;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ErrorType;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\Type;
use SplFileInfo;

use function array_key_exists;
use function array_shift;
use function count;
use function explode;
use function is_numeric;

/**
 * Statically parses the configuration files within the configured directories.
 *
 * Only consulted for keys the booted container does not know about, which is
 * typically the case when analysing a package without a host application.
 *
 * @internal
 */
final class ConfigParser
{
    /** @var array<string, SplFileInfo>|null */
    private array|null $files = null;

    /** @var array<string, Return_|null> */
    private array $returns = [];

    /** @var array<string, Type|null> */
    private array $types = [];

    /** @param list<non-empty-string> $configDirectories */
    public function __construct(
        private array $configDirectories,
        private FileHelper $fileHelper,
        private Parser $parser,
        private FileTypeMapper $fileTypeMapper,
        private bool $treatPhpDocTypesAsCertain,
    ) {
    }

    public function hasDirectories(): bool
    {
        return $this->configDirectories !== [];
    }

    public function getType(string $key, Scope $scope): Type|null
    {
        if (! $this->hasDirectories()) {
            return null;
        }

        if (array_key_exists($key, $this->types)) {
            return $this->types[$key];
        }

        $parts = explode('.', $key);
        $file  = array_shift($parts);
        $node  = $this->getReturn($file);

        $type = $node === null
            ? null
            : $this->getTypeFromDocComment($node, $parts, $file)
                ?? $this->getTypeFromExpr($node->expr, $parts, $scope);

        return $this->types[$key] = $type;
    }

    /** @param list<string> $parts */
    private function getTypeFromDocComment(Return_ $node, array $parts, string $file): Type|null
    {
        if (! $this->treatPhpDocTypesAsCertain) {
            return null;
        }

        $docComment = $node->getDocComment();

        if ($docComment === null) {
            return null;
        }

        $path = ($this->getFiles()[$file] ?? null)?->getPathname();

        if ($path === null) {
            return null;
        }

        $returnTag = $this->fileTypeMapper
            ->getResolvedPhpDoc($path, null, null, null, $docComment->getText())
            ->getReturnTag();

        if ($returnTag === null) {
            return null;
        }

        $type = $returnTag->getType();

        foreach ($parts as $part) {
            $type = $type->getOffsetValueType(is_numeric($part)
                ? new ConstantIntegerType((int) $part)
                : new ConstantStringType($part));
        }

        return $type instanceof ErrorType ? null : $type;
    }

    /** @param list<string> $parts */
    private function getTypeFromExpr(Expr|null $expr, array $parts, Scope $scope): Type|null
    {
        $expr = $expr === null ? null : $this->findExpr($expr, $parts, $scope);

        return $expr === null ? null : $this->generalize($scope->getType($expr));
    }

    /** @param list<string> $parts */
    private function findExpr(Expr $expr, array $parts, Scope $scope): Expr|null
    {
        if ($parts === []) {
            return $expr;
        }

        if (! $expr instanceof Array_) {
            return null;
        }

        $part  = array_shift($parts);
        $index = 0;

        foreach ($expr->items as $item) {
            if ($item->unpack) {
                return null;
            }

            if ($item->key === null) {
                $key = (string) $index++;
            } else {
                $keys = $scope->getType($item->key)->getConstantScalarValues();

                if (count($keys) !== 1) {
                    continue;
                }

                $key = (string) $keys[0];
            }

            if ($key !== $part) {
                continue;
            }

            return $this->findExpr($item->value, $parts, $scope);
        }

        return null;
    }

    /**
     * Scalar values are generalized as the value of a given key can
     * change between environments, but any type declared via a
     * docblock is trusted as it was written deliberately.
     */
    private function generalize(Type $type): Type
    {
        $constantArrays = $type->getConstantArrays();

        if (count($constantArrays) === 1 && $constantArrays[0]->equals($type)) {
            $array      = $constantArrays[0];
            $builder    = ConstantArrayTypeBuilder::createEmpty();
            $keyTypes   = $array->getKeyTypes();
            $valueTypes = $array->getValueTypes();

            if (count($keyTypes) > ConstantArrayTypeBuilder::ARRAY_COUNT_LIMIT) {
                $builder->degradeToGeneralArray(true);
            }

            foreach ($keyTypes as $index => $keyType) {
                $builder->setOffsetValueType(
                    $keyType,
                    $this->generalize($valueTypes[$index]),
                    $array->isOptionalKey($index),
                );
            }

            return $builder->getArray();
        }

        if ($type->isConstantScalarValue()->yes()) {
            return $type->generalize(GeneralizePrecision::lessSpecific());
        }

        return $type;
    }

    private function getReturn(string $file): Return_|null
    {
        if (array_key_exists($file, $this->returns)) {
            return $this->returns[$file];
        }

        $path = $this->getFiles()[$file] ?? null;

        if ($path === null) {
            return $this->returns[$file] = null;
        }

        try {
            $stmts = $this->parser->parseFile($path->getPathname());
        } catch (ParserErrorsException) {
            return $this->returns[$file] = null;
        }

        /** @var Return_|null $node */
        $node = (new NodeFinder())->findFirstInstanceOf($stmts, Return_::class);

        return $this->returns[$file] = $node;
    }

    /** @return array<string, SplFileInfo> */
    private function getFiles(): array
    {
        if ($this->files !== null) {
            return $this->files;
        }

        $this->files = [];

        foreach ($this->fileHelper->getFiles($this->configDirectories, '/\.php$/i') as $file) {
            $name = $file->getBasename('.php');

            if (array_key_exists($name, $this->files)) {
                continue;
            }

            $this->files[$name] = $file;
        }

        return $this->files;
    }
}
