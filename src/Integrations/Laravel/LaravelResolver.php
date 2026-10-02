<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Integrations\Laravel;

use JMac\Testing\PhpUnit\Tia\Contracts\EdgeAwareResolver;
use JMac\Testing\PhpUnit\Tia\Contracts\Edges;

/**
 * Resolves the Laravel files a test is not linked to yet, through the files
 * RecordsLaravelEdges did link:
 *
 *  - a new migration runs the tests linked to the earlier migrations of the
 *    tables it touches. A migration of a new table runs none: no test can
 *    use that table yet.
 *  - a view no test rendered runs the tests linked to the views and
 *    classes that use it (ViewReferences).
 *
 * A migration it cannot read the tables from, or a view it finds no tests
 * for, is left to the resolvers after it and to the sibling-directory guess.
 * Register it first, ahead of any resolver that runs the whole suite.
 */
final class LaravelResolver implements EdgeAwareResolver
{
    private const string MIGRATIONS = 'database/migrations';

    private ?ViewReferences $views = null;

    /**
     * @return list<string>|null
     */
    public function resolve(Edges $edges, string $projectRoot, string $changedRelativePath): ?array
    {
        if (! is_file($projectRoot.'/'.$changedRelativePath)) {
            return null;
        }

        if (dirname($changedRelativePath) === self::MIGRATIONS && str_ends_with($changedRelativePath, '.php')) {
            return $this->resolveMigration($edges, $projectRoot, $changedRelativePath);
        }

        if (ViewReferences::isView($changedRelativePath)) {
            return $this->resolveView($edges, $projectRoot, $changedRelativePath);
        }

        return null;
    }

    /**
     * @return list<string>|null
     */
    private function resolveMigration(Edges $edges, string $projectRoot, string $migration): ?array
    {
        $tables = Tables::fromMigrationSource((string) file_get_contents($projectRoot.'/'.$migration));

        if ($tables === []) {
            return null;
        }

        $earlier = (new Migrations([$projectRoot.'/'.self::MIGRATIONS]))->touchingAny($tables);

        return $this->testsLinkedToAny($edges, $earlier);
    }

    /**
     * @return list<string>|null
     */
    private function resolveView(Edges $edges, string $projectRoot, string $view): ?array
    {
        $this->views ??= new ViewReferences($projectRoot);

        $tests = $this->testsLinkedToAny($edges, $this->views->using($view));

        return $tests === [] ? null : $tests;
    }

    /**
     * @param  list<string>  $sourceFiles
     * @return list<string>
     */
    private function testsLinkedToAny(Edges $edges, array $sourceFiles): array
    {
        $tests = [];

        foreach ($sourceFiles as $sourceFile) {
            foreach ($edges->testsLinkedTo($sourceFile) as $test) {
                $tests[$test] = true;
            }
        }

        return array_keys($tests);
    }
}
