<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Tests\Unit;

use Ruvelo\FilamentWiki\Support\WantedPages;
use Ruvelo\FilamentWiki\Tests\TestCase;

class WantedPagesTest extends TestCase
{
    public function test_it_counts_linking_pages_not_links(): void
    {
        $this->page('A', '[[Runbook]] [[Runbook]] [[Glossary]]');
        $this->page('B', '[[runbook|the runbook]]');
        $this->page('Glossary');

        $this->assertSame(['runbook' => ['slug' => 'runbook', 'title' => 'Runbook', 'links' => 2]], WantedPages::top());
    }

    public function test_it_keeps_titles_as_written_and_orders_by_demand(): void
    {
        $this->page('A', '[[On-call rota#Weekends]] [[SLA targets]]');
        $this->page('B', '[[SLA targets]]');

        $this->assertSame([
            'sla-targets' => ['slug' => 'sla-targets', 'title' => 'SLA targets', 'links' => 2],
            'on-call-rota' => ['slug' => 'on-call-rota', 'title' => 'On-call rota', 'links' => 1],
        ], WantedPages::top());
        $this->assertCount(1, WantedPages::top(1));
    }

    public function test_links_in_code_are_not_wanted(): void
    {
        $this->page('A', "`[[Nope]]`\n\n```\n[[Also nope]]\n```");

        $this->assertSame([], WantedPages::top());
    }
}
