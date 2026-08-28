<?php

namespace UndefinedConfigNameRule;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function testDefined(): void
{
    Storage::disk('local');
    Cache::store('array');
    DB::connection('mysql');
    Queue::connection('sync');
    Mail::mailer('log');
    Log::channel('single');
    Broadcast::connection('log');
    Auth::guard('web');
}

function testUndefined(): void
{
    Storage::disk('avatars');
    Cache::store('memcache');
    DB::connection('mysqll');
    Queue::connection('rabbitmq');
    Mail::mailer('mailgun');
    Log::channel('sentry');
    Broadcast::connection('socket');
    Auth::guard('sanctum');
}

function testAliases(): void
{
    Storage::drive('local');
    Storage::drive('avatars');
    Cache::driver('array');
    Cache::driver('memcache');
    Log::driver('single');
    Log::driver('sentry');
}

function testConnectionSides(): void
{
    DB::connection('mysql::read');
    DB::connection('mysql::write');
    DB::connection('mysql::direct');
    DB::connection('mysql::replica');
    DB::connection('mysqll::read');
}

function testNotStaticallyKnown(string $name, bool $condition): void
{
    Storage::disk($name);
    Storage::disk();
    Storage::disk(null);
    Cache::store($condition ? 'array' : 'memcache');
}

function testInstanceCalls(FilesystemFactory $filesystem, DatabaseManager $database): void
{
    $filesystem->disk('local');
    $filesystem->disk('avatars');
    $database->connection('foo');
    $database->connection('bar');
}

class Unrelated
{
    public function disk(string $name): void
    {
    }

    public function store(string $name): void
    {
    }

    public function connection(string $name): void
    {
    }

    public function guard(string $name): void
    {
    }
}

function testUnrelated(Unrelated $unrelated): void
{
    $unrelated->disk('avatars');
    $unrelated->store('memcache');
    $unrelated->connection('rabbitmq');
    $unrelated->guard('sanctum');
}

function testCallableAndDynamicMethod(FilesystemFactory $filesystem, string $method): void
{
    $filesystem->disk(...);
    $filesystem->{$method}('avatars');
}

function testStaticExpression(bool $condition): void
{
    $storage = Storage::class;
    $storage::disk('avatars');

    $facade = $condition ? Storage::class : Cache::class;
    $facade::driver('missing');

    $connectionFacade = $condition ? Queue::class : Broadcast::class;
    $connectionFacade::connection('missing');
}

function testExpressionMethodName(FilesystemFactory $filesystem, bool $condition): void
{
    $method = 'disk';
    $filesystem->{$method}('avatars');

    $method = $condition ? 'disk' : 'drive';
    $filesystem->{$method}('avatars');

    $facade = $condition ? Queue::class : Broadcast::class;
    $method = 'connection';
    $facade::$method('missing');
}
