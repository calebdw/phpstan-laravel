<?php

declare(strict_types=1);

namespace StubThrows;

use App\User;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

/**
 * @param EloquentCollection<int, User> $models
 * @param Collection<int, int> $collection
 * @param LazyCollection<int, int> $lazy
 */
function undeclared(Validator $validator, ValidatorContract $contract, FormRequest $request, EloquentCollection $models, Collection $collection, LazyCollection $lazy, User $user): void
{
    $validator->validate();
    $validator->validated();
    $validator->validateWithBag('bag');
    $validator->safe();
    $contract->validate();
    $contract->validated();
    $request->safe();
    $models->findOrFail(1);
    $models->toQuery();
    $user->car()->findOrFail(1);
    User::query()->createOrFirst(['name' => 'test']);
    $user->accounts()->createOrFirst(['name' => 'test']);
    $user->roles()->createOrFirst(['name' => 'test']);
    Arr::sole([1]);
    Arr::random([1]);
    $collection->split(2);
    $collection->shift();
    $lazy->split(2);
    File::defaults();
    User::query()->findSole(1);
}

/** @throws \Illuminate\Validation\ValidationException */
function declared(Validator $validator): void
{
    $validator->safe();
}

function caught(Validator $validator): void
{
    try {
        $validator->validated();
    } catch (\Illuminate\Validation\ValidationException) {
    }
}
