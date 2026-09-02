<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Collectors;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Illuminate\View\Component;
use Illuminate\View\ViewName;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

use function array_map;

/** @implements Collector<CallLike, list<string>> */
final class UsedViewCollector implements Collector
{
    private const array FUNCTIONS = [
        [
            'functions' => ['view'],
            'parameter' => 'view',
            'position' => 0,
        ],
    ];

    private const array METHODS = [
        [
            'methods' => ['make'],
            'parameter' => 'view',
            'position' => 0,
            'receivers' => [Factory::class, View::class],
        ],
        [
            'methods' => ['view'],
            'parameter' => 'view',
            'position' => 1,
            'receivers' => [Router::class, Route::class],
        ],
        [
            'methods' => ['view', 'markdown'],
            'parameter' => 'view',
            'position' => 0,
            'receivers' => [Mailable::class, MailMessage::class],
        ],
        [
            'methods' => ['text'],
            'parameter' => 'textView',
            'position' => 0,
            'receivers' => [Mailable::class, MailMessage::class],
        ],
        [
            'methods' => ['view'],
            'parameter' => 'view',
            'position' => 0,
            'receivers' => [ResponseFactory::class, Component::class],
            'trait' => InteractsWithViews::class,
        ],
        [
            'methods' => ['assertViewIs'],
            'parameter' => 'value',
            'position' => 0,
            'receivers' => [TestResponse::class],
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
     * @return list<string>|null
     */
    public function processNode(Node $node, Scope $scope): array|null
    {
        $arg = $this->callHelper->matchingArg($node, $scope, self::FUNCTIONS, self::METHODS);

        if ($arg === null) {
            return null;
        }

        $views = $this->typeHelper->constantStrings($scope->getType($arg));

        return array_map(ViewName::normalize(...), $views) ?: null;
    }
}
