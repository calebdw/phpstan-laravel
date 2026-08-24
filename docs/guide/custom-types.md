# Custom PHPDoc types

All custom types that are specific to this extension are listed here. Types that are defined by PHPStan
can be found on [their website](https://phpstan.org/writing-php-code/phpdoc-types).


## view-string

The `view-string` type is a subset of the `string` type. Any `string` that passes the `view()->exists($string)` test
is also a valid `view-string`.

**Example:**

```php
/**
 * @phpstan-param view-string $view
 * @param string $view
 * @return \Illuminate\View\View
 */
public function renderView(string $view): View
{
    return view($view);
}
```
Now, whenever you call `renderView`, this extension will try to check whether 
the given string is a valid blade view.


If the string is not an existing blade view, the following error will be displayed by this extension.
```
Parameter #1 $view of method TestClass::renderView() expects view-string, string given.  
```

When working with packages, all vendor-prefixed paths like `acme::example` may fail. As packages don't contain a Laravel app, the default skeleton from `orchestra/testbench` is used. This instance doesn't know about the package so views are not registered. Create a `testbench.yaml` file to [register](https://packages.tools/testbench#package-service-providers) your service provider to solve this issue.

```yaml
providers:
    - Acme\AcmeServiceProvider
```

## model-property
`model-property` extends the built-in `string` type and acts like a string in the type level. But during the analysis if this extension finds that an argument of the method or a function has a `model-property<ModelName>`, it'll try to check that the given argument value is actually a property of the model.

All of the Laravel core methods have this type thanks to the stubs. So whenever you use a Eloquent builder, relation or a model method that expects a column, it'll be checked by this extension if the column actually exists. But you can also typehint any argument with `model-property` in your code.

The type is only active when [`modelPropertyType`](../reference/configuration.md#modelpropertytype) is enabled. With it off, `model-property<Model>` behaves as a plain `string` and nothing is checked. There is no rule behind it: once the type is active, the mismatches are reported by PHPStan's ordinary argument checks, so they carry core identifiers such as `argument.type`. See [rules](../guide/model-properties.md#checking-property-names) for a worked example.

