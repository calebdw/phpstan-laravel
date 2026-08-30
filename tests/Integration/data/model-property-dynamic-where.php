<?php

namespace ModelPropertyDynamicWhere;

use App\Team;
use App\User;

function test(): void
{
    // App\User resolves to a generic Builder<User>.
    User::whereEmail('foo');
    User::whereBogusColumn('foo');

    // App\Team resolves to the non-generic ChildTeamBuilder. The column check
    // must still run: parameterizing a non-generic builder used to leave the
    // active template type map empty, which silently skipped validation.
    Team::whereName('foo');
    Team::whereBogusColumn('foo');
}
