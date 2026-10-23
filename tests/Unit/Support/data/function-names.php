<?php

namespace FunctionNamesFallback;

use function view as render;

function calls(): void
{
    view('foo');
    render('bar');
}

namespace FunctionNamesShadow;

function view(string $name): string
{
    return $name;
}

function calls(): void
{
    view('foo');
    \view('bar');
}
