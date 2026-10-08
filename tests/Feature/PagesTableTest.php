<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Tests\Feature;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\Resources\PageResource\Pages\ListPages;
use Ruvelo\FilamentWiki\Tests\TestCase;
use Ruvelo\Wiki\Events\PageDeleted;
use Ruvelo\Wiki\Models\Page;
use Ruvelo\Wiki\Models\Revision;

class PagesTableTest extends TestCase
{
    public function test_it_lists_pages_with_their_last_editor_and_counts(): void
    {
        $maya = $this->signIn();
        $tom = $this->user('Tom Reyes');

        $deploy = $this->page('Deploy guide', 'Ship it.', $maya);
        $deploy->commit('Deploy guide', 'Ship it, carefully.', 'Be careful', $tom);
        $this->page('Home', 'See [[Deploy guide]].', $maya);
        $this->page('Onboarding', 'Read the [[Deploy guide]] twice: [[Deploy guide]].', $maya);

        Livewire::test(ListPages::class)
            ->assertOk()
            ->assertCanSeeTableRecords(Page::all())
            ->assertTableColumnStateSet('author', 'Tom Reyes', (string) $deploy->getKey())
            ->assertTableColumnStateSet('revisions_count', 2, (string) $deploy->getKey())
            ->assertTableColumnStateSet('backlinks_count', 2, (string) $deploy->getKey())
            ->assertSee('Tom Reyes');
    }

    public function test_the_menu_page_does_not_count_as_a_backlink(): void
    {
        $this->signIn();
        $deploy = $this->page('Deploy guide');
        $this->page('Sidebar', '- [[Deploy guide]]');

        Livewire::test(ListPages::class)->assertTableColumnStateSet('backlinks_count', 0, (string) $deploy->getKey());
    }

    public function test_it_searches_titles_and_text(): void
    {
        $this->signIn();
        $deploy = $this->page('Deploy guide', 'Merges ship to production.');
        $billing = $this->page('Billing', 'Invoices go out monthly.');

        Livewire::test(ListPages::class)
            ->searchTable('deploy')
            ->assertCanSeeTableRecords([$deploy])
            ->assertCanNotSeeTableRecords([$billing])
            ->searchTable('invoices')
            ->assertCanSeeTableRecords([$billing])
            ->assertCanNotSeeTableRecords([$deploy]);
    }

    public function test_it_sorts_by_title_edits_revisions_and_backlinks(): void
    {
        $this->signIn();
        Carbon::setTestNow('2026-10-01 09:00');
        $alpha = $this->page('Alpha', 'See [[Gamma]].');
        Carbon::setTestNow('2026-10-02 09:00');
        $beta = $this->page('Beta', 'See [[Gamma]].');
        Carbon::setTestNow('2026-10-03 09:00');
        $gamma = $this->page('Gamma');
        $gamma->commit('Gamma', 'v2');
        $gamma->commit('Gamma', 'v3');
        Carbon::setTestNow();

        Livewire::test(ListPages::class)
            ->assertCanSeeTableRecords([$gamma, $beta, $alpha], inOrder: true)
            ->sortTable('title')
            ->assertCanSeeTableRecords([$alpha, $beta, $gamma], inOrder: true)
            ->sortTable('revisions_count', 'desc')
            ->assertCanSeeTableRecords([$gamma, $alpha], inOrder: true)
            ->sortTable('backlinks_count', 'desc')
            ->assertCanSeeTableRecords([$gamma, $alpha], inOrder: true);
    }

    public function test_rows_open_the_reader_or_the_editor(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide');

        $table = Livewire::test(ListPages::class)->instance()->getTable();
        $this->assertSame(PageResource::getUrl('view', ['record' => $page]), $table->getRecordUrl($page));

        $this->plugin()->reader(false);

        $table = Livewire::test(ListPages::class)->instance()->getTable();
        $this->assertSame(PageResource::getUrl('edit', ['record' => $page]), $table->getRecordUrl($page));
    }

    public function test_rows_open_nothing_for_readers_without_the_reader(): void
    {
        Gate::define('wiki-edit', fn () => false);
        $this->signIn();
        $page = $this->page('Deploy guide');
        $this->plugin()->reader(false);

        $this->assertNull(Livewire::test(ListPages::class)->instance()->getTable()->getRecordUrl($page));
    }

    public function test_it_invites_the_first_page_when_empty(): void
    {
        $this->signIn();

        Livewire::test(ListPages::class)
            ->assertSee('The wiki is empty')
            ->assertSee('Write the first page.');
    }

    public function test_editors_can_delete_a_page_and_its_history(): void
    {
        $this->signIn();
        Event::fake([PageDeleted::class]);
        $page = $this->page('Deploy guide');

        Livewire::test(ListPages::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($page))
            ->assertNotified();

        $this->assertNull(Page::query()->find($page->id));
        $this->assertSame(0, Revision::query()->count());
        Event::assertDispatched(PageDeleted::class, fn (PageDeleted $event) => $event->page->is($page));
    }

    public function test_bulk_delete_goes_through_the_model_so_events_fire(): void
    {
        $this->signIn();
        Event::fake([PageDeleted::class]);
        $pages = [$this->page('One'), $this->page('Two')];

        Livewire::test(ListPages::class)
            ->selectTableRecords($pages)
            ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

        $this->assertSame(0, Page::query()->count());
        $this->assertSame(0, Revision::query()->count());
        Event::assertDispatchedTimes(PageDeleted::class, 2);
    }

    public function test_readers_cannot_create_edit_or_delete(): void
    {
        Gate::define('wiki-edit', fn () => false);
        $this->signIn();
        $page = $this->page('Deploy guide');

        Livewire::test(ListPages::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$page])
            ->assertActionHidden('create')
            ->assertActionHidden(TestAction::make('edit')->table($page))
            ->assertActionHidden(TestAction::make('delete')->table($page));
    }

    public function test_the_public_wiki_link_follows_the_plugin_option(): void
    {
        $this->signIn();

        Livewire::test(ListPages::class)->assertActionVisible('openWiki');

        $this->plugin()->publicLink(false);

        Livewire::test(ListPages::class)->assertActionHidden('openWiki');
    }
}
