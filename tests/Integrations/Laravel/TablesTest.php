<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Tests\Integrations\Laravel;

use JMac\Testing\PhpUnit\Tia\Integrations\Laravel\Tables;
use JMac\Testing\PhpUnit\Tia\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class TablesTest extends TestCase
{
    #[Test]
    public function it_reads_the_tables_of_a_dml_statement(): void
    {
        $this->assertSame(['orders', 'users'], Tables::fromSql('select * from `orders` inner join `users` on `users`.`id` = `orders`.`user_id`'));
        $this->assertSame(['invoices'], Tables::fromSql('insert into "invoices" ("total") values (?)'));
        $this->assertSame(['posts'], Tables::fromSql('update public.posts set title = ?'));
    }

    #[Test]
    public function it_ignores_schema_metadata_and_statements_that_are_not_dml(): void
    {
        $this->assertSame([], Tables::fromSql('select * from information_schema.tables'));
        $this->assertSame([], Tables::fromSql('select * from `migrations`'));
        $this->assertSame([], Tables::fromSql('alter table users add column age int'));
    }

    #[Test]
    public function it_reads_the_tables_a_migration_touches(): void
    {
        $migration = <<<'PHP'
        return new class extends Migration
        {
            public function up(): void
            {
                Schema::table('invoices', function (Blueprint $table) {
                    $table->foreignId('user_id')->constrained('users');
                });
                Schema::rename('old_posts', 'posts');
                DB::statement('ALTER TABLE orders ADD COLUMN total INT');
                DB::table('settings')->insert(['key' => 'x']);
            }
        };
        PHP;

        $this->assertSame(['invoices', 'old_posts', 'orders', 'posts', 'settings'], Tables::fromMigrationSource($migration));
    }
}
