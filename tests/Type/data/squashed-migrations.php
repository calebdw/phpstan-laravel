<?php

namespace SquashedMigrations;

use Illuminate\Database\Eloquent\Model;

use function PHPStan\Testing\assertType;

class DumpPost extends Model
{
    protected $table = 'dump_posts';
}

class OtherDumpPost extends DumpPost
{
    protected $connection = 'foo';
}

function properties(DumpPost $post, OtherDumpPost $other): void
{
    assertType("'draft'|'published'", $post->status);
    assertType('string', $post->body);
    assertType('int', $other->body);
}
