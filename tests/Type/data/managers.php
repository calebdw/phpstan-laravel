<?php

namespace Managers;

use Illuminate\Support\Manager;

use function PHPStan\Testing\assertType;

interface DummyDriverContract
{
    public function type(): string;
}

class DummyDriver implements DummyDriverContract
{
    public function type(): string
    {
        return 'dummy';
    }

    public function notInContract(): int
    {
        return 1;
    }
}

class OtherDriver
{
    public function other(): float
    {
        return 1.0;
    }
}

class ContractManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'dummy';
    }

    protected function createDummyDriver(): DummyDriverContract
    {
        return new DummyDriver();
    }

    protected function createOtherDriver(): OtherDriver
    {
        return new OtherDriver();
    }
}

class ImplManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'dummy';
    }

    protected function createDummyDriver(): DummyDriver
    {
        return new DummyDriver();
    }
}

class UndeclaredManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'dummy';
    }

    protected function createDummyDriver()
    {
        return new DummyDriver();
    }
}

function testDriver(
    ContractManager $contract,
    ImplManager $impl,
    UndeclaredManager $undeclared,
    string $name,
    bool $condition,
): void {
    assertType('Managers\DummyDriverContract', $contract->driver());
    assertType('Managers\DummyDriverContract', $contract->driver(null));
    assertType('Managers\DummyDriverContract', $contract->driver('dummy'));
    assertType('Managers\OtherDriver', $contract->driver('other'));
    assertType('Managers\DummyDriverContract|Managers\OtherDriver', $contract->driver($condition ? 'dummy' : 'other'));
    assertType('mixed', $contract->driver($name));
    assertType('mixed', $contract->driver('missing'));

    assertType('Managers\DummyDriver', $impl->driver());

    // Creators without a declared return type fall back to the resolved driver.
    assertType('Managers\DummyDriver', $undeclared->driver());
}

function testForwardedCalls(ContractManager $contract, ImplManager $impl): void
{
    assertType('string', $contract->type());
    assertType('string', $impl->type());
    assertType('int', $impl->notInContract());
}
