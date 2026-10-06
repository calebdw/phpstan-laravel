<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Collectors;

use CalebDW\PhpstanLaravel\Support\CallHelper;
use CalebDW\PhpstanLaravel\Support\TypeHelper;
use CalebDW\PhpstanLaravel\Support\ViewFileHelper;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Illuminate\View\Component;
use Illuminate\View\ViewName;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\DependencyTracker;
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
            'receivers' => [Content::class],
        ],
        [
            'methods' => ['html'],
            'parameter' => 'html',
            'position' => 0,
            'receivers' => [Content::class],
        ],
        [
            'methods' => ['text'],
            'parameter' => 'text',
            'position' => 0,
            'receivers' => [Content::class],
        ],
        [
            'methods' => ['markdown'],
            'parameter' => 'markdown',
            'position' => 0,
            'receivers' => [Content::class],
        ],
        [
            'methods' => ['send'],
            'parameter' => 'view',
            'position' => 0,
            'receivers' => [Mailer::class, Mail::class],
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

    public function __construct(
        private CallHelper $callHelper,
        private TypeHelper $typeHelper,
        private ViewFileHelper $viewFileHelper,
    ) {
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @param  CallLike                $node
     * @param  Scope&DependencyTracker $scope
     *
     * @return list<string>|null
     */
    public function processNode(Node $node, Scope $scope): array|null
    {
        if ($node instanceof New_ && $this->callHelper->isCalledOn($node, $scope, Content::class)) {
            $views = [];

            foreach ([['view', 0], ['html', 1], ['text', 2], ['markdown', 3]] as [$name, $position]) {
                $arg = $node->getArg($name, $position);

                if ($arg === null) {
                    continue;
                }

                $views = [...$views, ...$this->typeHelper->constantStrings($scope->getType($arg->value))];
            }

            return $this->collect($views, $scope);
        }

        $arg = $this->callHelper->matchingArg($node, $scope, self::FUNCTIONS, self::METHODS);

        if ($arg === null) {
            return null;
        }

        return $this->collect($this->typeHelper->constantStrings($scope->getType($arg)), $scope);
    }

    /**
     * Declares the files behind every view named here, as well as collecting
     * the names.
     *
     * `view-string` asks whether a view exists while PHPStan resolves a type,
     * which gets no scope to declare anything on, so without this the result
     * cache keeps reporting a view as missing after it has been written. This
     * is where a call site is already known to name a view, and it is the same
     * set of call sites `view-string` is checked at.
     *
     * @param  list<string>            $views
     * @param  Scope&DependencyTracker $scope
     *
     * @return list<string>|null
     */
    private function collect(array $views, Scope $scope): array|null
    {
        $views = array_map(ViewName::normalize(...), $views);

        foreach ($views as $view) {
            foreach ($this->viewFileHelper->getViewFilePaths($view) as $path) {
                $scope->trackFileDependency($path);
            }
        }

        return $views ?: null;
    }
}
