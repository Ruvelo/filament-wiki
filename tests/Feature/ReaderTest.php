<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\Resources\PageResource\Pages\ReadPage;
use Ruvelo\FilamentWiki\Tests\TestCase;

class ReaderTest extends TestCase
{
    public function test_it_renders_the_page_with_the_wiki_renderer(): void
    {
        $maya = $this->signIn();
        $this->page('Rollbacks');
        $page = $this->page('Deploy guide', <<<'MD'
            Every merge ships. See [[Rollbacks#Reverting|how to roll back]] and [[On-call rota]].

            ## Shipping a change

            **Two** reviews for payments. <script>alert(1)</script> [bad](javascript:alert(1))

            ### Small fixes

            Fine.
            MD, $maya);

        $this->get(PageResource::getUrl('view', ['record' => $page]))
            ->assertOk()
            ->assertSee('Deploy guide')
            ->assertSee('Last edited')
            ->assertSee('by Maya Okafor')
            ->assertSee('<strong>Two</strong>', escape: false)
            ->assertSee('<a class="wiki-link" href="'.e(PageResource::getUrl('view', ['record' => 'rollbacks'])).'#content-reverting">how to roll back</a>', escape: false)
            ->assertSee('<a class="wiki-link wiki-link--new" href="'.e(PageResource::getUrl('create', ['title' => 'On-call rota'])).'">On-call rota</a>', escape: false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false)
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertDontSee('javascript:alert', escape: false);
    }

    public function test_it_shows_an_outline_and_what_links_here(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide', "## Shipping a change\n\nx\n\n### Small fixes\n\ny\n\n## Rolling back\n\nz");
        $this->page('Home', 'Start with the [[Deploy guide]].');
        $this->page('Sidebar', '- [[Deploy guide]]');

        $this->get(PageResource::getUrl('view', ['record' => $page]))
            ->assertSee('On this page')
            ->assertSee('<a href="#content-shipping-a-change">Shipping a change</a>', escape: false)
            ->assertSee('<li class="fi-wiki-sub"><a href="#content-small-fixes">Small fixes</a></li>', escape: false)
            ->assertSee('What links here')
            ->assertSee('<a href="'.e(PageResource::getUrl('view', ['record' => 'home'])).'">Home</a>', escape: false)
            ->assertDontSee('>Sidebar</a>', escape: false);
    }

    public function test_short_pages_have_no_aside(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide', 'Just a line.');

        $this->get(PageResource::getUrl('view', ['record' => $page]))
            ->assertDontSee('On this page')
            ->assertDontSee('What links here');
    }

    public function test_an_empty_page_says_so(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide', '');

        $this->get(PageResource::getUrl('view', ['record' => $page]))->assertSee('This page is empty.');
    }

    public function test_red_links_stay_on_the_public_wiki_for_readers(): void
    {
        Gate::define('wiki-edit', fn () => false);
        $this->signIn();
        $page = $this->page('Deploy guide', 'See [[On-call rota]].');

        $this->get(PageResource::getUrl('view', ['record' => $page]))
            ->assertOk()
            ->assertSee('<a class="wiki-link wiki-link--new" href="'.e(route('wiki.show', 'on-call-rota')).'">', escape: false)
            ->assertDontSee(PageResource::getUrl('create'), escape: false);
    }

    public function test_it_links_to_the_page_on_the_public_wiki(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide');

        Livewire::test(ReadPage::class, ['record' => $page->slug])
            ->assertActionVisible('openOnWiki')
            ->assertActionHasUrl('openOnWiki', route('wiki.show', 'deploy-guide'))
            ->assertActionShouldOpenUrlInNewTab('openOnWiki');

        $this->plugin()->publicLink(false);

        Livewire::test(ReadPage::class, ['record' => $page->slug])->assertActionHidden('openOnWiki');
    }

    public function test_the_sub_navigation_offers_edit_and_history_to_editors_only(): void
    {
        $page = $this->page('Deploy guide');
        Gate::define('wiki-edit', fn ($user) => $user->name === 'Maya Okafor');

        $this->signIn('Maya Okafor');
        $this->get(PageResource::getUrl('view', ['record' => $page]))
            ->assertSee(PageResource::getUrl('edit', ['record' => $page]), escape: false)
            ->assertSee(PageResource::getUrl('history', ['record' => $page]), escape: false);

        $this->signIn('Tom Reyes');
        $this->get(PageResource::getUrl('view', ['record' => $page]))
            ->assertDontSee(PageResource::getUrl('edit', ['record' => $page]), escape: false)
            ->assertSee(PageResource::getUrl('history', ['record' => $page]), escape: false);
    }

    public function test_the_reader_can_be_turned_off(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide');
        $this->plugin()->reader(false);

        $this->get(PageResource::getUrl('view', ['record' => $page]))->assertForbidden();
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $page = $this->page('Deploy guide');

        $this->get(PageResource::getUrl('view', ['record' => $page]))->assertRedirect('/admin/login');
    }
}
