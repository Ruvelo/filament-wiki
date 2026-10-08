<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\Tests\TestCase;
use Ruvelo\FilamentWiki\Widgets\RecentChanges;
use Ruvelo\FilamentWiki\Widgets\WantedPages;
use Ruvelo\Wiki\Models\Revision;

class WidgetsTest extends TestCase
{
    public function test_recent_changes_lists_the_latest_saves(): void
    {
        $maya = $this->signIn();
        $tom = $this->user('Tom Reyes');
        $deploy = $this->page('Deploy guide', 'v1', $maya);
        $deploy->commit('Deploy guide', 'v2', 'Add timing', $tom);
        $this->page('Billing', 'Monthly.', $maya);

        $revisions = Revision::query()->orderByDesc('id')->get();

        Livewire::test(RecentChanges::class)
            ->assertOk()
            ->assertSee('Recent changes')
            ->assertCanSeeTableRecords($revisions, inOrder: true)
            ->assertTableColumnStateSet('author', 'Tom Reyes', $revisions[1])
            ->assertSee('Add timing');

        $table = Livewire::test(RecentChanges::class)->instance()->getTable();
        $this->assertSame(PageResource::getUrl('view', ['record' => 'billing']), $table->getRecordUrl($revisions[0]));
    }

    public function test_recent_changes_shows_a_limited_number(): void
    {
        $this->signIn();
        foreach (range(1, 10) as $i) {
            $this->page("Page {$i}");
        }

        $revisions = Revision::query()->orderByDesc('id')->get();

        Livewire::test(RecentChanges::class)
            ->assertCanSeeTableRecords($revisions->take(8))
            ->assertCanNotSeeTableRecords($revisions->skip(8))
            ->set('limit', 3)
            ->assertCanSeeTableRecords($revisions->take(3))
            ->assertCanNotSeeTableRecords($revisions->skip(3));
    }

    public function test_recent_changes_has_an_empty_state(): void
    {
        $this->signIn();

        Livewire::test(RecentChanges::class)->assertSee('No changes yet');
    }

    public function test_wanted_pages_lists_red_links_most_linked_first(): void
    {
        $this->signIn();
        $this->page('Deploy guide', 'See [[On-call rota]] and [[Service map]].');
        $this->page('Incident reviews', 'Page the [[On-call rota]]. Also [[On-call rota]].');
        $this->page('Service map');

        Livewire::test(WantedPages::class)
            ->assertOk()
            ->assertSee('Wanted pages')
            ->assertSee('On-call rota')
            ->assertSee('2 pages')
            ->assertDontSee('Service map')
            ->assertActionVisible(TestAction::make('write')->table('on-call-rota'))
            ->assertActionHasUrl(TestAction::make('write')->table('on-call-rota'), PageResource::getUrl('create', ['title' => 'On-call rota']));
    }

    public function test_wanted_pages_hides_the_write_action_from_readers(): void
    {
        Gate::define('wiki-edit', fn () => false);
        $this->signIn();
        $this->page('Deploy guide', 'See [[On-call rota]].');

        Livewire::test(WantedPages::class)
            ->assertSee('On-call rota')
            ->assertActionHidden(TestAction::make('write')->table('on-call-rota'));
    }

    public function test_wanted_pages_has_an_empty_state(): void
    {
        $this->signIn();
        $this->page('Deploy guide', 'No links.');

        Livewire::test(WantedPages::class)->assertSee('No red links');
    }

    public function test_both_widgets_are_on_the_dashboard_by_default(): void
    {
        $this->signIn();

        $widgets = Filament::getPanel('admin')->getWidgets();
        $this->assertContains(RecentChanges::class, $widgets);
        $this->assertContains(WantedPages::class, $widgets);

        $this->get('/admin')->assertOk()->assertSeeLivewire(RecentChanges::class)->assertSeeLivewire(WantedPages::class);
    }
}
