<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\Resources\PageResource\Pages\PageHistory;
use Ruvelo\FilamentWiki\Tests\TestCase;
use Ruvelo\Wiki\Events\PageSaved;
use Ruvelo\Wiki\Models\Page;
use Ruvelo\Wiki\Models\Revision;

class HistoryTest extends TestCase
{
    /**
     * @return array{0: Page, 1: Revision, 2: Revision, 3: Revision}
     */
    private function history(): array
    {
        $maya = $this->user('Maya Okafor');
        $tom = $this->user('Tom Reyes');

        $page = $this->page('Deploy guide', "Ship it.\n\n## Rolling back\n\nRevert.", $maya, 'First version');
        $first = $page->revisions()->firstOrFail();
        $second = $page->commit('Deploy guide', "Ship it, within four minutes.\n\n## Rolling back\n\nRevert.", 'Add timing', $tom);
        $third = $page->commit('Deploy guide', "Ship it, within four minutes.\n\n## Rolling back\n\nRevert, then write it up.", null, $maya);

        return [$page, $first, $second, $third];
    }

    public function test_it_lists_every_revision_with_author_and_summary(): void
    {
        $this->signIn();
        [$page, $first, $second, $third] = $this->history();

        Livewire::test(PageHistory::class, ['record' => $page->slug])
            ->assertOk()
            ->assertCanSeeTableRecords([$third, $second, $first], inOrder: true)
            ->assertTableColumnStateSet('author', 'Tom Reyes', $second)
            ->assertTableColumnStateSet('summary', 'Add timing', $second)
            ->assertSee('No summary')
            ->assertSee('Current');

        $this->get(PageResource::getUrl('history', ['record' => $page]))->assertOk()->assertSee('History of “Deploy guide”', escape: false);
    }

    public function test_view_changes_shows_the_line_diff(): void
    {
        $this->signIn();
        [$page, $first, $second] = $this->history();

        Livewire::test(PageHistory::class, ['record' => $page->slug])
            ->mountAction(TestAction::make('changes')->table($second))
            ->assertModalSee('Revision #'.$second->id)
            ->assertModalSee('Changes since #'.$first->id)
            ->assertModalSee('<div role="row" class="fi-wiki-diff-add"><span aria-label="Added">+</span> Ship it, within four minutes.</div>')
            ->assertModalSee('<div role="row" class="fi-wiki-diff-del"><span aria-label="Removed">-</span> Ship it.</div>')
            ->assertModalSee('<span class="fi-wiki-add">+1</span>');
    }

    public function test_the_first_revision_is_all_additions(): void
    {
        $this->signIn();
        [$page, $first] = $this->history();

        Livewire::test(PageHistory::class, ['record' => $page->slug])
            ->mountAction(TestAction::make('changes')->table($first))
            ->assertModalSee('First version')
            ->assertModalDontSee('fi-wiki-diff-del');
    }

    public function test_the_diff_escapes_the_text(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide', 'Safe.');
        $revision = $page->commit('Deploy guide', '<img src=x onerror=alert(1)>');

        Livewire::test(PageHistory::class, ['record' => $page->slug])
            ->mountAction(TestAction::make('changes')->table($revision))
            ->assertModalSee('&lt;img src=x onerror=alert(1)&gt;')
            ->assertModalDontSee('<img src=x onerror=alert(1)>');
    }

    public function test_a_rename_is_called_out(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide', 'Same.');
        $revision = $page->commit('Shipping guide', 'Same.', 'Rename');

        Livewire::test(PageHistory::class, ['record' => $page->slug])
            ->mountAction(TestAction::make('changes')->table($revision))
            ->assertModalSee('Renamed from “Deploy guide” to “Shipping guide”.')
            ->assertModalSee('The text didn’t change.');
    }

    public function test_restoring_saves_the_old_version_as_a_new_revision(): void
    {
        $kenji = $this->signIn('Kenji Mori');
        [$page, $first] = $this->history();
        Event::fake([PageSaved::class]);

        Livewire::test(PageHistory::class, ['record' => $page->slug])
            ->callAction(TestAction::make('restore')->table($first))
            ->assertNotified('Restored revision #'.$first->id)
            ->assertRedirect(PageResource::getUrl('view', ['record' => $page]));

        $page->refresh();
        $this->assertSame($first->body, $page->body);
        $this->assertSame(4, $page->revisions()->count());

        $latest = $page->revisions()->firstOrFail();
        $this->assertSame('Restored revision #'.$first->id, $latest->summary);
        $this->assertSame((string) $kenji->id, (string) $latest->user_id);
        Event::assertDispatched(PageSaved::class);
    }

    public function test_the_current_revision_cannot_be_restored(): void
    {
        $this->signIn();
        [$page, $first, , $third] = $this->history();

        Livewire::test(PageHistory::class, ['record' => $page->slug])
            ->assertActionHidden(TestAction::make('restore')->table($third))
            ->assertActionVisible(TestAction::make('restore')->table($first));
    }

    public function test_readers_can_see_history_but_not_restore(): void
    {
        Gate::define('wiki-edit', fn () => false);
        $this->signIn();
        [$page, $first] = $this->history();

        Livewire::test(PageHistory::class, ['record' => $page->slug])
            ->assertOk()
            ->assertActionVisible(TestAction::make('changes')->table($first))
            ->assertActionHidden(TestAction::make('restore')->table($first));

        $this->assertSame(3, $page->revisions()->count());
    }
}
