<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\RelationExistenceHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Type\ObjectType;

use function array_merge;
use function in_array;
use function str_starts_with;
use function strtolower;

/** @implements Rule<Node\Expr\CallLike> */
final class RelationExistenceRule implements Rule
{
    private const array METHODS = [
        'has'                            => 1,
        'orhas'                          => 1,
        'doesnthave'                     => 1,
        'ordoesnthave'                   => 1,
        'wherehas'                       => 1,
        'withwherehas'                   => 1,
        'orwherehas'                     => 1,
        'wheredoesnthave'                => 1,
        'orwheredoesnthave'              => 1,
        'whererelation'                  => 1,
        'orwhererelation'                => 1,
        'withwhererelation'              => 1,
        'wheredoesnthaverelation'        => 1,
        'orwheredoesnthaverelation'      => 1,
        'hasmorph'                       => 1,
        'orhasmorph'                     => 1,
        'doesnthavemorph'                => 1,
        'ordoesnthavemorph'              => 1,
        'wherehasmorph'                  => 1,
        'orwherehasmorph'                => 1,
        'wheredoesnthavemorph'           => 1,
        'orwheredoesnthavemorph'         => 1,
        'wheremorphrelation'             => 1,
        'orwheremorphrelation'           => 1,
        'wheremorphdoesnthaverelation'   => 1,
        'orwheremorphdoesnthaverelation' => 1,
        'with'                           => 1,
        'withonly'                       => 1,
        'load'                           => 1,
        'loadmissing'                    => 1,
    ];

    private const array AGGREGATES = [
        'withaggregate' => 1,
        'withcount'     => 1,
        'withmax'       => 1,
        'withmin'       => 1,
        'withsum'       => 1,
        'withavg'       => 1,
        'withexists'    => 1,
        'loadaggregate' => 1,
        'loadcount'     => 1,
        'loadmax'       => 1,
        'loadmin'       => 1,
        'loadsum'       => 1,
        'loadavg'       => 1,
        'loadexists'    => 1,
    ];

    public function __construct(
        private RelationExistenceHelper $relationExistenceHelper,
        private CallHelper $callHelper,
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    /** @return RuleError[] */
    public function processNode(Node $node, Scope $scope): array
    {
        if ((! $node instanceof MethodCall && ! $node instanceof NullsafeMethodCall && ! $node instanceof Node\Expr\StaticCall) || ! $node->name instanceof Node\Identifier || $node->isFirstClassCallable() || $node->getAttribute('virtualNullsafeMethodCall', false)) {
            return [];
        }

        $method = strtolower($node->name->name);

        if (! isset(self::METHODS[$method]) && ! isset(self::AGGREGATES[$method])) {
            return [];
        }

        $aggregate = isset(self::AGGREGATES[$method]);

        $args = $node->getArgs();

        if ($args === [] || $args[0]->unpack) {
            return [];
        }

        foreach ($args as $arg) {
            if ($arg->name !== null && in_array($arg->name->toString(), ['relation', 'relations'], true)) {
                $args = [$arg];
                break;
            }
        }

        $type       = $this->callHelper->receiverType($node, $scope);
        $collection = (new ObjectType(Collection::class))->isSuperTypeOf($type)->yes();

        if ($collection && str_starts_with($method, 'load')) {
            $type = $type->getTemplateType(Collection::class, 'TModel');
        } elseif ($collection) {
            return [];
        }

        $variadic = in_array($method, ['with', 'load', 'loadmissing', 'withcount'], true)
            || ($method === 'loadcount' && ! $collection && (new ObjectType(Model::class))->isSuperTypeOf($type)->yes());
        $args     = $variadic && $scope->getType($args[0]->value)->isString()->yes() ? $args : [$args[0]];
        $errors   = [];

        foreach ($args as $arg) {
            if ($arg->unpack) {
                continue;
            }

            $errors = array_merge($errors, $this->relationExistenceHelper->check($scope->getType($arg->value), $type, $node, $scope, $aggregate));
        }

        return $errors;
    }
}
