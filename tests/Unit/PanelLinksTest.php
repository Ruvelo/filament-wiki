<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Tests\Unit;

use Ruvelo\FilamentWiki\Support\PanelLinks;
use Ruvelo\FilamentWiki\Tests\TestCase;

class PanelLinksTest extends TestCase
{
    private function rewrite(string $html, bool $canCreate = true): string
    {
        return PanelLinks::rewrite(
            $html,
            fn (string $slug): string => "/admin/wiki/{$slug}",
            fn (string $title): ?string => $canCreate ? '/admin/wiki/_/new?title='.rawurlencode($title) : null,
        );
    }

    public function test_links_to_pages_point_into_the_panel(): void
    {
        $html = '<a class="wiki-link" href="http://localhost/wiki/deploy-guide">Deploy guide</a>';

        $this->assertSame('<a class="wiki-link" href="/admin/wiki/deploy-guide">Deploy guide</a>', $this->rewrite($html));
    }

    public function test_sections_are_kept(): void
    {
        $html = '<a class="wiki-link" href="http://localhost/wiki/deploy-guide#content-rolling-back">x</a>';

        $this->assertSame('<a class="wiki-link" href="/admin/wiki/deploy-guide#content-rolling-back">x</a>', $this->rewrite($html));
    }

    public function test_unicode_slugs_are_decoded(): void
    {
        $html = '<a class="wiki-link" href="http://localhost/wiki/'.rawurlencode('café').'">x</a>';

        $this->assertSame('<a class="wiki-link" href="/admin/wiki/café">x</a>', $this->rewrite($html));
    }

    public function test_red_links_open_the_create_form(): void
    {
        $fromEditor = '<a class="wiki-link wiki-link--new" href="http://localhost/wiki/_/new?title=On-call%20rota">On-call rota</a>';
        $fromReader = '<a class="wiki-link wiki-link--new" href="http://localhost/wiki/on-call-rota">On-call rota</a>';

        $this->assertSame('<a class="wiki-link wiki-link--new" href="/admin/wiki/_/new?title=On-call%20rota">On-call rota</a>', $this->rewrite($fromEditor));
        $this->assertSame('<a class="wiki-link wiki-link--new" href="/admin/wiki/_/new?title=On%20call%20rota">On-call rota</a>', $this->rewrite($fromReader));
    }

    public function test_red_links_stay_public_when_nobody_may_create(): void
    {
        $html = '<a class="wiki-link wiki-link--new" href="http://localhost/wiki/on-call-rota">x</a>';

        $this->assertSame($html, $this->rewrite($html, canCreate: false));
    }

    public function test_other_links_are_left_alone(): void
    {
        $html = '<a class="wiki-link" href="#content-steps">Steps</a> <a href="https://example.com/wiki/x">x</a> <a class="wiki-link" href="https://elsewhere.test/wiki/x">x</a>';

        $this->assertSame($html, $this->rewrite($html));
    }

    public function test_urls_are_escaped(): void
    {
        $html = '<a class="wiki-link" href="http://localhost/wiki/a&quot;b">x</a>';

        $out = PanelLinks::rewrite($html, fn (string $slug): string => '/p/'.$slug, fn (string $title): string => '/new');

        $this->assertSame('<a class="wiki-link" href="/p/a&quot;b">x</a>', $out);
    }

    public function test_titles_for_missing_pages_read_like_the_wiki(): void
    {
        $this->assertSame('On call rota', PanelLinks::titleFor('on-call-rota'));
    }
}
