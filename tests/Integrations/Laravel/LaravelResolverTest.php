<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Tests\Integrations\Laravel;

use JMac\Testing\PhpUnit\Tia\Contracts\Resolver;
use JMac\Testing\PhpUnit\Tia\Graph;
use JMac\Testing\PhpUnit\Tia\Integrations\Laravel\LaravelResolver;
use JMac\Testing\PhpUnit\Tia\TestPaths;
use JMac\Testing\PhpUnit\Tia\Tests\Support\TempGitRepository;
use JMac\Testing\PhpUnit\Tia\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Runs the resolver through Graph::affected(), with the edges
 * RecordsLaravelEdges would have recorded linked by hand.
 */
final class LaravelResolverTest extends TestCase
{
    private TempGitRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = TempGitRepository::create();
        $this->repo->write('database/migrations/2024_01_01_create_invoices_table.php', "<?php Schema::create('invoices', fn () => null);\n");
        $this->repo->write('database/migrations/2024_01_01_create_users_table.php', "<?php Schema::create('users', fn () => null);\n");
        $this->repo->write('resources/views/invoices/show.blade.php', "@include('partials.total')\n");
        $this->repo->write('resources/views/users/index.blade.php', "<ul></ul>\n");
        $this->repo->write('tests/InvoiceTest.php', "<?php\n");
        $this->repo->write('tests/UserTest.php', "<?php\n");
    }

    protected function tearDown(): void
    {
        if ($this->skippedByTia()) {
            return;
        }

        $this->repo->cleanup();
    }

    #[Test]
    public function a_new_migration_runs_the_tests_using_its_tables(): void
    {
        $this->repo->write('database/migrations/2024_02_01_add_total_to_invoices.php', "<?php Schema::table('invoices', fn () => null);\n");

        $this->assertSame(['tests/InvoiceTest.php'], $this->graph()->affected(['database/migrations/2024_02_01_add_total_to_invoices.php']));
    }

    #[Test]
    public function a_migration_of_a_new_table_runs_nothing(): void
    {
        $this->repo->write('database/migrations/2024_02_01_create_payments_table.php', "<?php Schema::create('payments', fn () => null);\n");

        $this->assertSame([], $this->graph()->affected(['database/migrations/2024_02_01_create_payments_table.php']));
    }

    #[Test]
    public function a_migration_without_readable_tables_is_left_to_the_next_resolver(): void
    {
        $this->repo->write('database/migrations/2024_02_01_backfill.php', "<?php Artisan::call('app:backfill');\n");

        $this->assertSame(
            ['tests/InvoiceTest.php', 'tests/UserTest.php'],
            $this->graph(new FullSuite)->affected(['database/migrations/2024_02_01_backfill.php']),
        );
    }

    #[Test]
    public function a_new_partial_runs_the_tests_that_rendered_a_view_including_it(): void
    {
        $this->repo->write('resources/views/partials/total.blade.php', "<p></p>\n");

        $this->assertSame(['tests/InvoiceTest.php'], $this->graph(new FullSuite)->affected(['resources/views/partials/total.blade.php']));
    }

    #[Test]
    public function a_view_nothing_uses_yet_is_left_to_the_next_resolver(): void
    {
        $this->repo->write('resources/views/partials/unused.blade.php', "<p></p>\n");

        $this->assertSame(
            ['tests/InvoiceTest.php', 'tests/UserTest.php'],
            $this->graph(new FullSuite)->affected(['resources/views/partials/unused.blade.php']),
        );
    }

    private function graph(Resolver ...$after): Graph
    {
        $root = $this->repo->path();

        $graph = new Graph($root);
        $graph->setTestPaths(new TestPaths(directories: ['tests'], files: [], suffixes: ['Test.php']));
        $graph->link('tests/InvoiceTest.php', $root.'/database/migrations/2024_01_01_create_invoices_table.php');
        $graph->link('tests/InvoiceTest.php', $root.'/resources/views/invoices/show.blade.php');
        $graph->link('tests/UserTest.php', $root.'/database/migrations/2024_01_01_create_users_table.php');
        $graph->link('tests/UserTest.php', $root.'/resources/views/users/index.blade.php');
        $graph->setResolvers([new LaravelResolver, ...$after]);

        return $graph;
    }
}

/**
 * Stands in for a project's own fallback that runs the whole suite.
 */
final class FullSuite implements Resolver
{
    public function resolve(string $projectRoot, string $changedRelativePath): array
    {
        return ['tests/InvoiceTest.php', 'tests/UserTest.php'];
    }
}
