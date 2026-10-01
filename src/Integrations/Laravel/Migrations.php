<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Integrations\Laravel;

/**
 * The migrations in a set of directories, by the tables they touch. Read
 * once and kept for the rest of the run.
 *
 * Laravel only runs the `*.php` files directly in a migration directory, so
 * subdirectories are left out here too.
 */
final class Migrations
{
    /** @var array<string, list<string>>|null table => migration files */
    private ?array $byTable = null;

    /**
     * @param  list<string>  $directories  Absolute paths.
     */
    public function __construct(private readonly array $directories) {}

    /**
     * @return list<string> absolute paths of the migrations that touch $table
     */
    public function touching(string $table): array
    {
        return $this->byTable()[strtolower($table)] ?? [];
    }

    /**
     * @param  list<string>  $tables
     * @return list<string> absolute paths of the migrations that touch any of $tables
     */
    public function touchingAny(array $tables): array
    {
        $files = [];

        foreach ($tables as $table) {
            foreach ($this->touching($table) as $file) {
                $files[$file] = true;
            }
        }

        return array_keys($files);
    }

    /**
     * @return array<string, list<string>>
     */
    private function byTable(): array
    {
        if ($this->byTable !== null) {
            return $this->byTable;
        }

        $byTable = [];

        foreach ($this->directories as $directory) {
            foreach (glob(rtrim($directory, '/').'/*.php') ?: [] as $file) {
                $source = @file_get_contents($file);

                if ($source === false) {
                    continue;
                }

                foreach (Tables::fromMigrationSource($source) as $table) {
                    $byTable[$table][] = $file;
                }
            }
        }

        return $this->byTable = $byTable;
    }
}
