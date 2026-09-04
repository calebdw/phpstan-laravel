<?php

declare(strict_types=1);

namespace Type;

use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function Orchestra\Testbench\laravel_version_compare;

class GeneralTypeTest extends TypeInferenceTestCase
{
    /** @return iterable<mixed> */
    public static function dataFileAsserts(): iterable
    {
        yield from self::gatherAssertTypes(__DIR__ . '/data/abort.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/abstract-manager.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/app-make.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/application-make.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/arr-get-pull.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/arr-only.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/arr-pluck.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/arr-select.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/arrayable.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/auth.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/belongs-to-many-generics.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/benchmark.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-aggregates.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-collapse.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-count-by.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-dot.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-flatten.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-filter.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-generic-static-methods.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-helper.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-map-to-groups.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-only-except.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-intersection-types.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-make-static.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-reject.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-select.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-stubs.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-to-array.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-transform.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-value.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-where-not-null.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/collection-where.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/conditionable.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/container-array-access.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/container-make.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/contracts.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/custom-eloquent-builder.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/custom-eloquent-collection.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/custom-model-collection-make.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/custom-model-collection-unique.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/custom-support-collection.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/database-transaction.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/date-extension.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/eloquent-builder-pluck.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/eloquent-builder.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/eloquent-getter-types.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/enumerable-pluck.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/environment-helper.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/facades.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/form-request.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/gate-facade.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/group-by.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/has-events.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/helpers.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/higher-order-collection-proxy-methods.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/key-by.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/managers.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/mixin-infinite-recursion.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/model-attributes.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/model-collections.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/model-factories.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/model-keys.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/model-methods.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/model-properties-relations.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/model-properties.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/model-relations.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/model-scope-attribute.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/model-scopes.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/model.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/optional-helper.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/paginator-extension.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/passthru.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/query-builder.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/request-header.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/request-object.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/request-user.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/route.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/tappable.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/throw.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/translate.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/translator.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/validator.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/view-exists.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/view.php');
        yield from self::gatherAssertTypes(__DIR__ . '/data/where-relation.php');

        if (laravel_version_compare('13.0.0', '>=')) {
            yield from self::gatherAssertTypes(__DIR__ . '/data/l13-eloquent-builder-model-keys.php');
            yield from self::gatherAssertTypes(__DIR__ . '/data/l13-model-counter-methods.php');
        }

        //##############################################################################################################

        // Console Commands
        yield from self::gatherAssertTypes(__DIR__ . '/../application/app/Console/Commands/BarCommand.php');
        yield from self::gatherAssertTypes(__DIR__ . '/../application/app/Console/Commands/BazCommand.php');
        yield from self::gatherAssertTypes(__DIR__ . '/../application/app/Console/Commands/FooCommand.php');
    }

    #[DataProvider('dataFileAsserts')]
    public function testFileAsserts(string $assertType, string $file, mixed ...$args): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/data/config-with-migrations.neon'];
    }
}
