<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Tests\Integrations\Laravel;

use JMac\Testing\PhpUnit\Tia\Integrations\Laravel\Migrations;
use JMac\Testing\PhpUnit\Tia\Tests\Support\TempGitRepository;
use JMac\Testing\PhpUnit\Tia\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class MigrationsTest extends TestCase
{
    private TempGitRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = TempGitRepository::create();
    }

    protected function tearDown(): void
    {
        if ($this->skippedByTia()) {
            return;
        }

        $this->repo->cleanup();
    }

    #[Test]
    public function it_finds_the_migrations_of_a_table_in_the_top_level_of_each_directory(): void
    {
        $this->repo->write('database/migrations/2024_01_01_create_invoices_table.php', "<?php Schema::create('invoices', fn () => null);\n");
        $this->repo->write('database/migrations/2024_02_01_add_total_to_invoices.php', "<?php Schema::table('invoices', fn () => null);\n");
        $this->repo->write('database/migrations/2024_01_01_create_users_table.php', "<?php Schema::create('users', fn () => null);\n");
        $this->repo->write('database/migrations/archive/2020_01_01_old_invoices.php', "<?php Schema::create('invoices', fn () => null);\n");
        $this->repo->write('modules/billing/2024_03_01_add_vat_to_invoices.php', "<?php Schema::table('invoices', fn () => null);\n");

        $root = $this->repo->path();
        $migrations = new Migrations([$root.'/database/migrations', $root.'/modules/billing']);

        $this->assertSame([
            $root.'/database/migrations/2024_01_01_create_invoices_table.php',
            $root.'/database/migrations/2024_02_01_add_total_to_invoices.php',
            $root.'/modules/billing/2024_03_01_add_vat_to_invoices.php',
        ], $migrations->touching('INVOICES'));
        $this->assertSame([$root.'/database/migrations/2024_01_01_create_users_table.php'], $migrations->touchingAny(['users', 'sessions']));
    }
}
