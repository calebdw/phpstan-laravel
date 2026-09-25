# Form requests

`rules()` is parsed when it is a constant array of pipe-strings, arrays of
strings, `Rule::enum()` / `new Enum()`, `Rule::in()` / `in:`, and `min` /
`max` / `between` on integers. Closures, `Rule::when()`, and `$this->…` in
the array are skipped.

Validation does not cast. `validated()` and `$request->title` are the values
as they arrived (forms are strings; JSON may already be `int`/`bool`). Use
`integer()`, `boolean()`, and `enum()` when you want a cast.

```php
class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'body' => 'nullable|string',
            'age' => 'integer',
            'status' => ['required', Rule::enum(PostStatus::class)],
            'tags.*' => 'string',
            'author.name' => 'required|string',
        ];
    }
}

$post->validated();
// array{title: string, body?: string|null, age?: int|numeric-string, status: 'draft'|'published', tags?: array<int|string, string>, author: array{name: string}}

$post->safe();                   // ValidatedInput of that shape
$post->safe(['title', 'body']);  // array{title: string, body?: string|null}

$post->title;   // string
$post->integer('age');           // int
$post->enum('status', PostStatus::class); // PostStatus|null
```

A wildcard keeps whatever keys were submitted, because Laravel does not
reindex them: `tags.*` is `array<int|string, string>` rather than a list.
Laravel's own `list` rule on the parent narrows it back to one. A dotted
segment that is numeric is an integer key, the way PHP casts it, so
`items.0.id` is `array{items: array{0: array{id: …}}}`.

A ternary in the rules array is read on both branches and unioned; the
condition is not evaluated, so it can be anything. The field is required
only when every branch requires it, and a branch that `exclude`s it
contributes no type but leaves the key optional.

```php
'discount' => $condition
    ? ['nullable', 'numeric']
    : ['exclude'],
// discount?: float|int|numeric-string|null
```

`$request->foo` is typed like `validated()['foo']` for keys in `rules()`.
Runtime `__get` reads `all()` (unvalidated input and route params too); the
type is the useful shape, not that bag.

Inline `$request->validate($rules)` on `Illuminate\Http\Request` uses the
same rule parser. The return value is the shape; after the call,
`$request->title` is too.

```php
$data = $request->validate([
    'title' => ['required', 'string'],
    'age' => 'integer',
]);
// array{title: string, age?: int|numeric-string}

$request->title; // string
```
