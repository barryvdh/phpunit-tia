<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Integrations\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Events\QueryExecuted;
use JMac\Testing\PhpUnit\Tia\Tia;

/**
 * Links the running test to the Laravel files its coverage cannot see:
 *
 *  - every view it renders: pages, layouts, includes, Blade and Livewire
 *    component views, mail templates. Blade runs a compiled copy in
 *    storage/, outside <source>.
 *  - the migrations of every table it queries. No line of a migration runs
 *    inside a test, yet a migration that changes a table can break any test
 *    using that table.
 *
 * Ported from Pest's BladeEdges and TableTracker, which record the same two
 * things for Pest's own TIA.
 */
final class EdgeRecorder
{
    private static ?Migrations $migrations = null;

    /** @var list<string> */
    private static array $migrationDirectories = [];

    public static function register(Application $app): void
    {
        if (! Tia::isRecording()) {
            return;
        }

        $app->make('view')->composer('*', static function (View $view): void {
            if (method_exists($view, 'getPath')) {
                Tia::link((string) $view->getPath());
            }
        });

        $migrations = self::migrations($app);

        $app->make('events')->listen(QueryExecuted::class, static function (QueryExecuted $query) use ($migrations): void {
            Tia::link(...$migrations->touchingAny(Tables::fromSql($query->sql)));
        });
    }

    /**
     * Each test creates a new application, but the migrations do not change
     * during a run, so they are read once.
     */
    private static function migrations(Application $app): Migrations
    {
        $directories = [$app->databasePath('migrations')];

        if ($app->bound('migrator')) {
            $directories = [...$directories, ...$app->make('migrator')->paths()];
        }

        $directories = array_values(array_unique($directories));

        if (self::$migrations === null || self::$migrationDirectories !== $directories) {
            self::$migrations = new Migrations($directories);
            self::$migrationDirectories = $directories;
        }

        return self::$migrations;
    }
}
