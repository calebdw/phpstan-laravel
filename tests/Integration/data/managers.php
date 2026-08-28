<?php

namespace IntegrationManagers;

use Illuminate\Support\Manager;

interface DriverContract
{
    public function type(): string;
}

class Driver implements DriverContract
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

class ContractManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'dummy';
    }

    protected function createDummyDriver(): DriverContract
    {
        return new Driver();
    }
}

class ImplManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'dummy';
    }

    protected function createDummyDriver(): Driver
    {
        return new Driver();
    }
}

function test(ContractManager $contract, ImplManager $impl): void
{
    $contract->type();
    $contract->notInContract();

    $impl->type();
    $impl->notInContract();
}
