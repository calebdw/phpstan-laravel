<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Rules;

use CalebDW\PhpstanLaravel\Support\ConfigHelper;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Type;

use function array_intersect;
use function array_map;
use function explode;
use function in_array;
use function sprintf;
use function str_contains;

/** @implements Rule<Node\Expr\CallLike> */
final class UndefinedConfigNameRule implements Rule
{
    private const string IDENTIFIER = 'laravel.undefinedConfigName';

    private const array LOOKUPS = [
        [
            'receivers' => [Storage::class, FilesystemFactory::class],
            'methods' => ['disk', 'drive'],
            'key' => 'filesystems.disks',
            'message' => 'Disk [%s] does not have a configured driver.',
        ],
        [
            'receivers' => [Cache::class, CacheFactory::class],
            'methods' => ['store', 'driver'],
            'key' => 'cache.stores',
            'message' => 'Cache store [%s] is not defined.',
        ],
        [
            'receivers' => [DB::class, ConnectionResolverInterface::class],
            'methods' => ['connection'],
            'key' => 'database.connections',
            'message' => 'Database connection [%s] not configured.',
        ],
        [
            'receivers' => [Queue::class, QueueFactory::class],
            'methods' => ['connection'],
            'key' => 'queue.connections',
            'message' => 'The [%s] queue connection has not been configured.',
        ],
        [
            'receivers' => [Mail::class, MailFactory::class],
            'methods' => ['mailer'],
            'key' => 'mail.mailers',
            'message' => 'Mailer [%s] is not defined.',
        ],
        [
            'receivers' => [Log::class, LogManager::class],
            'methods' => ['channel', 'driver'],
            'key' => 'logging.channels',
            'message' => 'Log [%s] is not defined.',
        ],
        [
            'receivers' => [Broadcast::class, BroadcastFactory::class],
            'methods' => ['connection'],
            'key' => 'broadcasting.connections',
            'message' => 'Broadcast connection [%s] is not defined.',
        ],
        [
            'receivers' => [Auth::class, AuthFactory::class],
            'methods' => ['guard'],
            'key' => 'auth.guards',
            'message' => 'Auth guard [%s] is not defined.',
        ],
    ];

    private const array CONNECTION_SUFFIXES = ['read', 'write', 'direct'];

    public function __construct(private ConfigHelper $configHelper)
    {
    }

    public function getNodeType(): string
    {
        return Node\Expr\CallLike::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof MethodCall && ! $node instanceof StaticCall) {
            return [];
        }

        $args = $node->getArgs();

        if ($args === []) {
            return [];
        }

        $methods  = $node->name instanceof Identifier
            ? [$node->name->name]
            : array_map(static fn ($s) => $s->getValue(), $scope->getType($node->name)->getConstantStrings());
        $receiver = $this->receiverType($node, $scope);
        $errors   = [];

        foreach (self::LOOKUPS as $lookup) {
            if (array_intersect($methods, $lookup['methods']) === []) {
                continue;
            }

            if (! $this->isCalledOn($receiver, $lookup)) {
                continue;
            }

            $errors = [...$errors, ...$this->check($args[0], $scope, $lookup, $node->getStartLine())];
        }

        return $errors;
    }

    /**
     * @param value-of<self::LOOKUPS> $lookup
     *
     * @return list<IdentifierRuleError>
     */
    private function check(Arg $arg, Scope $scope, array $lookup, int $line): array
    {
        $requested = $scope->getType($arg->value);

        if ($requested->isNull()->yes()) {
            return [];
        }

        $defined = $this->configHelper->getKeyType($lookup['key'], $scope);

        if ($defined === null || ! $defined->isArray()->yes()) {
            return [];
        }

        $errors = [];

        foreach ($requested->getConstantStrings() as $constantString) {
            $name = $this->configuredName($constantString->getValue(), $lookup);

            if (! $defined->hasOffsetValueType(new ConstantStringType($name))->no()) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf($lookup['message'], $name))
                ->identifier(self::IDENTIFIER)
                ->line($line)
                ->build();
        }

        return $errors;
    }

    /** @param value-of<self::LOOKUPS> $lookup */
    private function configuredName(string $name, array $lookup): string
    {
        if ($lookup['key'] !== 'database.connections' || ! str_contains($name, '::')) {
            return $name;
        }

        [$connection, $side] = explode('::', $name, 2);

        return in_array($side, self::CONNECTION_SUFFIXES, true) ? $connection : $name;
    }

    private function receiverType(MethodCall|StaticCall $node, Scope $scope): Type
    {
        if ($node instanceof StaticCall) {
            $type = $node->class instanceof Node\Name
                ? $scope->resolveTypeByName($node->class)
                : $scope->getType($node->class);

            return $type->getObjectTypeOrClassStringObjectType();
        }

        return $scope->getType($node->var);
    }

    /** @param value-of<self::LOOKUPS> $lookup */
    private function isCalledOn(Type $receiver, array $lookup): bool
    {
        foreach ($receiver->getObjectClassReflections() as $reflection) {
            foreach ($lookup['receivers'] as $supportedClass) {
                if ($reflection->is($supportedClass)) {
                    return true;
                }
            }
        }

        return false;
    }
}
