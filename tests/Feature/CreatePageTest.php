<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\Resources\PageResource\Pages\CreatePage;
use Ruvelo\FilamentWiki\Tests\TestCase;
use Ruvelo\Wiki\Events\PageSaved;
use Ruvelo\Wiki\Models\Page;
use Ruvelo\Wiki\Wiki;

class CreatePageTest extends TestCase
{
    public function test_it_creates_a_page_through_the_wiki(): void
    {
        $maya = $this->signIn();
        Event::fake([PageSaved::class]);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Deploy guide', 'body' => "Ship it.\n\nSee [[Rollbacks]].", 'summary' => 'First draft'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Page created')
            ->assertRedirect(PageResource::getUrl('view', ['record' => 'deploy-guide']));

        $page = Wiki::find('Deploy guide');
        $this->assertNotNull($page);
        $this->assertSame('deploy-guide', $page->slug);
        $this->assertSame(['rollbacks'], $page->outgoingLinks());

        $revision = $page->revisions()->firstOrFail();
        $this->assertSame('First draft', $revision->summary);
        $this->assertSame((string) $maya->id, (string) $revision->user_id);

        Event::assertDispatched(PageSaved::class, fn (PageSaved $event) => $event->wasCreated() && $event->page->is($page));
    }

    public function test_the_summary_defaults_to_created_page(): void
    {
        $this->signIn();

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Billing', 'body' => 'Monthly.'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Created page', Wiki::find('Billing')?->revisions()->value('summary'));
    }

    public function test_windows_line_endings_are_normalised(): void
    {
        $this->signIn();

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Billing', 'body' => "One\r\nTwo"])
            ->call('create');

        $this->assertSame("One\nTwo", Wiki::find('Billing')?->body);
    }

    public function test_it_redirects_to_the_editor_without_the_reader(): void
    {
        $this->signIn();
        $this->plugin()->reader(false);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Billing', 'body' => 'Monthly.'])
            ->call('create')
            ->assertRedirect(PageResource::getUrl('edit', ['record' => 'billing']));
    }

    public function test_a_red_link_prefills_the_title(): void
    {
        $this->signIn();

        $this->get(PageResource::getUrl('create', ['title' => 'On-call rota']))
            ->assertOk()
            ->assertSee('On-call rota');

        Livewire::withQueryParams(['title' => 'On-call rota'])
            ->test(CreatePage::class)
            ->assertFormSet(['title' => 'On-call rota']);
    }

    public function test_it_validates_the_title(): void
    {
        $this->signIn();
        $this->page('Deploy guide');

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => '', 'body' => 'x'])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => '!!!', 'body' => 'x'])
            ->call('create')
            ->assertHasFormErrors(['title'])
            ->assertSee('A page title needs at least one letter or number.');

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'deploy GUIDE', 'body' => 'x'])
            ->call('create')
            ->assertHasFormErrors(['title'])
            ->assertSee('A page with this title already exists.');

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => str_repeat('a', 256), 'body' => 'x'])
            ->call('create')
            ->assertHasFormErrors(['title' => 'max']);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Fine', 'body' => 'x', 'summary' => str_repeat('a', 256)])
            ->call('create')
            ->assertHasFormErrors(['summary' => 'max']);

        $this->assertSame(1, Page::query()->count());
    }

    public function test_a_page_created_in_the_meantime_is_reported_not_overwritten(): void
    {
        $this->signIn();

        $component = Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Billing', 'body' => 'Mine.']);

        // Someone else's insert lands between validation and this save.
        Page::creating(function (Page $page): void {
            Page::flushEventListeners();
            $this->page('Billing', 'Theirs.');
        });

        $component->call('create')
            ->assertNotified('The page wasn’t created')
            ->assertNoRedirect()
            ->assertFormSet(['title' => 'Billing', 'body' => 'Mine.']);

        $this->assertFalse(Page::query()->where('body', 'Mine.')->exists());
    }

    public function test_the_live_preview_uses_the_wiki_renderer(): void
    {
        $this->signIn();
        $this->page('Deploy guide');

        Livewire::test(CreatePage::class)
            ->assertSee('Nothing to preview yet.')
            ->fillForm(['body' => "## Steps\n\nRead [[Deploy guide]], then [[Rollbacks]]. <script>alert(1)</script>"])
            ->assertSeeHtml('<a class="wiki-link" href="'.e(PageResource::getUrl('view', ['record' => 'deploy-guide'])).'">Deploy guide</a>')
            ->assertSeeHtml('<a class="wiki-link wiki-link--new" href="'.e(PageResource::getUrl('create', ['title' => 'Rollbacks'])).'">Rollbacks</a>')
            ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;')
            ->assertDontSeeHtml('<script>alert(1)</script>');
    }

    public function test_only_editors_can_create(): void
    {
        Gate::define('wiki-edit', fn ($user) => $user->name === 'Maya Okafor');
        $this->signIn('Tom Reyes');

        $this->get(PageResource::getUrl('create'))->assertForbidden();
        Livewire::test(CreatePage::class)->assertForbidden();
    }
}
