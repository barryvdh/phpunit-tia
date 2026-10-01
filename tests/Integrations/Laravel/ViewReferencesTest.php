<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Tests\Integrations\Laravel;

use JMac\Testing\PhpUnit\Tia\Integrations\Laravel\ViewReferences;
use JMac\Testing\PhpUnit\Tia\Tests\Support\TempGitRepository;
use JMac\Testing\PhpUnit\Tia\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ViewReferencesTest extends TestCase
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
    public function it_follows_includes_and_components_up_to_the_views_and_classes_that_use_them(): void
    {
        $this->repo->write('resources/views/partials/total.blade.php', "<p>{{ \$total }}</p>\n");
        $this->repo->write('resources/views/components/invoice-card.blade.php', "@include('partials.total')\n");
        $this->repo->write('resources/views/invoices/show.blade.php', "@extends('layouts.app')\n<x-invoice-card />\n");
        $this->repo->write('resources/views/emails/invoice.blade.php', "@include(\"partials.total\", ['total' => 1])\n");
        $this->repo->write('resources/views/unrelated.blade.php', "<x-invoice-cards />\n@include('partials.totals')\n");
        $this->repo->write('app/Http/Controllers/InvoiceController.php', "<?php return view('invoices.show');\n");
        $this->repo->write('app/Mail/InvoiceMail.php', "<?php new Content(markdown: 'emails.invoice');\n");
        $this->repo->write('app/Mail/OtherMail.php', "<?php new Content(markdown: 'emails.invoice-reminder');\n");

        $using = (new ViewReferences($this->repo->path()))->using('resources/views/partials/total.blade.php');

        sort($using);

        $this->assertSame([
            'app/Http/Controllers/InvoiceController.php',
            'app/Mail/InvoiceMail.php',
            'resources/views/components/invoice-card.blade.php',
            'resources/views/emails/invoice.blade.php',
            'resources/views/invoices/show.blade.php',
        ], $using);
    }

    #[Test]
    public function it_matches_an_index_component_by_its_directory_name(): void
    {
        $this->repo->write('resources/views/components/card/index.blade.php', "<div></div>\n");
        $this->repo->write('resources/views/dashboard.blade.php', "<x-card>Hi</x-card>\n");

        $this->assertSame(
            ['resources/views/dashboard.blade.php'],
            (new ViewReferences($this->repo->path()))->using('resources/views/components/card/index.blade.php'),
        );
    }
}
