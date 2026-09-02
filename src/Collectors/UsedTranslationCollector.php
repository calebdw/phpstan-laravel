<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Collectors;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Lang;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

use function array_map;

/** @implements Collector<CallLike, list<array{0: string, 1: int}>> */
final class UsedTranslationCollector implements Collector
{
    private const array FUNCTIONS = [
        [
            'functions' => ['__', 'trans', 'trans_choice'],
            'parameter' => 'key',
            'position' => 0,
        ],
    ];

    private const array METHODS = [
        [
            'methods' => ['get', 'choice'],
            'parameter' => 'key',
            'position' => 0,
            'receivers' => [Translator::class, Lang::class],
        ],
    ];

    public function __construct(private CallHelper $callHelper, private TypeHelper $typeHelper)
    {
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @param CallLike $node
     *
     * @return list<array{0: string, 1: int}>|null
     */
    public function processNode(Node $node, Scope $scope): array|null
    {
        $arg = $this->callHelper->matchingArg($node, $scope, self::FUNCTIONS, self::METHODS);

        if ($arg === null) {
            return null;
        }

        $keys = $this->typeHelper->constantStrings($scope->getType($arg));
        $line = $node->getStartLine();

        return array_map(static fn ($k) => [$k, $line], $keys) ?: null;
    }
}
