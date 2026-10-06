<?php

declare(strict_types=1);

namespace CalebDW\PhpstanLaravel\Schema;

use PHPStan\Analyser\DeclarationDependencyTracker;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;

use function array_merge;
use function array_unique;
use function array_values;

/**
 * Declares that something read a column, so that the result cache runs it
 * again once a migration or a schema dump changes.
 *
 * The directories rather than the parsed tables, which would be the finer
 * dependency: the result cache reads its values in the main process, which
 * never loads the bootstrap file, and parsing the schema there would both
 * answer from an application that was never booted and leave that answer in
 * the schema cache for the analysis to pick up.
 *
 * Which side it is declared on depends on who is reading. A class reflection
 * extension describes a model and is asked once for the whole process, so it
 * is the model that depends on the schema, and with it every file touching
 * the model. A rule or a dynamic return type extension analyses one file, and
 * only that file has to run again - including when the file is the model's
 * own, which does not depend on the model as a class.
 */
final class SchemaDependencyTracker
{
    /** @var list<string>|null */
    private array|null $directories = null;

    public function __construct(
        private DeclarationDependencyTracker $tracker,
        private MigrationFileParser $migrationFileParser,
        private SchemaDumpParser $schemaDumpParser,
    ) {
    }

    /** What the model declares - its columns, as properties - comes from the schema. */
    public function trackModel(ClassReflection $classReflection): void
    {
        foreach ($this->directories() as $directory) {
            $this->tracker->trackDirectoryDependency($classReflection, $directory);
        }
    }

    /** @param Scope&DependencyTracker $scope */
    public function trackScope(Scope $scope): void
    {
        foreach ($this->directories() as $directory) {
            $scope->trackDirectoryDependency($directory);
        }
    }

    /**
     * Every file in them, not just the ones the parsers match: the patterns
     * are case insensitive and these directories hold nothing else, so the
     * cheap superset is worth more than an exact one that could miss a file.
     *
     * @return list<string>
     */
    private function directories(): array
    {
        return $this->directories ??= array_values(array_unique(array_merge(
            $this->migrationFileParser->directories(),
            $this->schemaDumpParser->directories(),
        )));
    }
}
