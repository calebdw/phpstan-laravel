<?php

declare(strict_types=1);

namespace Tests\Rules\Queue\Data;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\UniqueFor;

#[UniqueFor(3600)]
class UniqueJobWithUniqueForAttribute implements ShouldQueue, ShouldBeUnique
{
}

#[UniqueFor(3600)]
abstract class AbstractUniqueJobWithAttribute implements ShouldQueue, ShouldBeUnique
{
}

class UniqueJobInheritingUniqueForAttribute extends AbstractUniqueJobWithAttribute
{
}

#[UniqueFor(3600)]
trait UniqueForAttributeTrait
{
}

class UniqueJobUsingUniqueForTrait implements ShouldQueue, ShouldBeUnique
{
    use UniqueForAttributeTrait;
}
