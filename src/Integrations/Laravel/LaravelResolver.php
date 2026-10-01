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

    private ?Edges $edges = null;

    private ?ViewReferences $views = null;

    /** @var array<string, true> */
    private array $handled = [];

    public function useEdges(Edges $edges): void
    {
        $this->edges = $edges;
    }

    /**
     * @return list<string>
     */
    public function resolve(string $projectRoot, string $changedRelativePath): array
    {
        if ($this->edges === null || ! is_file($projectRoot.'/'.$changedRelativePath)) {
            return [];
        }

        if (dirname($changedRelativePath) === self::MIGRATIONS && str_ends_with($changedRelativePath, '.php')) {
            return $this->resolveMigration($this->edges, $projectRoot, $changedRelativePath);
        }

        if (ViewReferences::isView($changedRelativePath)) {
            return $this->resolveView($this->edges, $projectRoot, $changedRelativePath);
        }

        return [];
    }

    public function handles(string $changedRelativePath): bool
    {
        return isset($this->handled[$changedRelativePath]);
    }

    /**
     * @return list<string>
     */
    private function resolveMigration(Edges $edges, string $projectRoot, string $migration): array
    {
        $tables = Tables::fromMigrationSource((string) file_get_contents($projectRoot.'/'.$migration));

        if ($tables === []) {
            return [];
        }

        $this->handled[$migration] = true;

        $earlier = (new Migrations([$projectRoot.'/'.self::MIGRATIONS]))->touchingAny($tables);

        return $this->testsLinkedToAny($edges, $earlier);
    }

    /**
     * @return list<string>
     */
    private function resolveView(Edges $edges, string $projectRoot, string $view): array
    {
        $this->views ??= new ViewReferences($projectRoot);

        $tests = $this->testsLinkedToAny($edges, $this->views->using($view));

        if ($tests !== []) {
            $this->handled[$view] = true;
        }

        return $tests;
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
