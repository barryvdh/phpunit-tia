<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Integrations\Laravel;

/**
 * Use next to RunWithTia in a Laravel base TestCase. Laravel calls
 * setUpRecordsLaravelEdges() once the application of each test is created,
 * and the hooks it installs link the test to the views it renders and the
 * migrations of the tables it queries.
 *
 * Only what happens inside the test method counts: PHPUnit reports a test
 * as running after setUp(), so a seeder or `migrate` call in setUp() links
 * nothing.
 */
trait RecordsLaravelEdges
{
    protected function setUpRecordsLaravelEdges(): void
    {
        EdgeRecorder::register($this->app);
    }
}
