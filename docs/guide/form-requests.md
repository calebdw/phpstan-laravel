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
// array{title: string, body?: string|null, age?: int|numeric-string, status: 'draft'|'published', tags?: list<string>, author: array{name: string}}

$post->safe();                   // ValidatedInput of that shape
$post->safe(['title', 'body']);  // array{title: string, body?: string|null}

$post->title;   // string
$post->integer('age');           // int
$post->enum('status', PostStatus::class); // PostStatus|null
```

`$request->foo` is typed like `validated()['foo']` for keys in `rules()`.
Runtime `__get` reads `all()` (unvalidated input and route params too); the
type is the useful shape, not that bag.
