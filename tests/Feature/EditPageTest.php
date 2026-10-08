<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Tests\Feature;

use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\Resources\PageResource\Pages\EditPage;
use Ruvelo\FilamentWiki\Tests\TestCase;
use Ruvelo\Wiki\Events\PageDeleted;
use Ruvelo\Wiki\Events\PageSaved;
use Ruvelo\Wiki\Models\Page;
use Ruvelo\Wiki\Models\Revision;

class EditPageTest extends TestCase
{
    public function test_it_loads_the_page_and_the_revision_it_started_from(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide', 'Ship it.');

        Livewire::test(EditPage::class, ['record' => $page->slug])
            ->assertOk()
            ->assertFormSet(['title' => 'Deploy guide', 'body' => 'Ship it.', 'summary' => null])
            ->assertSet('baseRevision', $page->currentRevisionId());
    }

    public function test_saving_commits_a_new_revision(): void
    {
        $this->signIn('Tom Reyes');
        $page = $this->page('Deploy guide', 'Ship it.');
        Event::fake([PageSaved::class]);

        Livewire::test(EditPage::class, ['record' => $page->slug])
            ->fillForm(['body' => "Ship it.\n\nSee [[Rollbacks]].", 'summary' => 'Link the rollback guide'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Page saved')
            ->assertRedirect(PageResource::getUrl('view', ['record' => $page]));

        $page->refresh();
        $this->assertSame("Ship it.\n\nSee [[Rollbacks]].", $page->body);
        $this->assertSame(['rollbacks'], $page->outgoingLinks());
        $this->assertSame(2, $page->revisions()->count());

        $revision = $page->revisions()->firstOrFail();
        $this->assertSame('Link the rollback guide', $revision->summary);
        $this->assertSame('Tom Reyes', $revision->authorName());

        Event::assertDispatched(PageSaved::class, fn (PageSaved $event) => ! $event->wasCreated() && $event->revision->is($revision));
    }

    public function test_renaming_keeps_the_slug(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide');

        Livewire::test(EditPage::class, ['record' => $page->slug])
            ->fillForm(['title' => 'Shipping guide'])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertSame('Shipping guide', $page->title);
        $this->assertSame('deploy-guide', $page->slug);
    }

    public function test_an_empty_summary_is_stored_as_null(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide');

        Livewire::test(EditPage::class, ['record' => $page->slug])
            ->fillForm(['body' => 'New text', 'summary' => '   '])
            ->call('save');

        $this->assertNull($page->revisions()->value('summary'));
    }

    public function test_saving_without_changes_records_nothing(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide', 'Ship it.');

        Livewire::test(EditPage::class, ['record' => $page->slug])
            ->fillForm(['summary' => 'Nothing really'])
            ->call('save')
            ->assertNotified('No changes to save')
            ->assertNoRedirect();

        $this->assertSame(1, $page->revisions()->count());
    }

    public function test_it_refuses_to_overwrite_someone_elses_save(): void
    {
        $this->signIn('Maya Okafor');
        $tom = $this->user('Tom Reyes');
        $page = $this->page('Deploy guide', 'Ship it.');

        $editor = Livewire::test(EditPage::class, ['record' => $page->slug])
            ->fillForm(['body' => 'Maya’s version', 'summary' => 'Maya’s edit']);

        // Tom saves while Maya is still typing.
        $page->fresh()?->commit('Deploy guide', 'Tom’s version', 'Tom’s edit', $tom);

        $editor->call('save')
            ->assertNotified('Someone else edited this page')
            ->assertNoRedirect()
            ->assertFormSet(['body' => 'Maya’s version', 'summary' => 'Maya’s edit']);

        $page->refresh();
        $this->assertSame('Tom’s version', $page->body);
        $this->assertSame(2, $page->revisions()->count());
        $this->assertSame('Tom’s edit', $page->revisions()->value('summary'));
    }

    public function test_a_second_save_from_the_same_editor_is_not_a_conflict(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide', 'v1');
        $this->plugin()->reader(false);

        Livewire::test(EditPage::class, ['record' => $page->slug])
            ->fillForm(['body' => 'v2', 'summary' => 'Second'])
            ->call('save')
            ->assertNotified('Page saved')
            ->assertNoRedirect()
            ->assertFormSet(['summary' => null])
            ->fillForm(['body' => 'v3'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Page saved');

        $this->assertSame('v3', $page->fresh()?->body);
        $this->assertSame(3, $page->revisions()->count());
    }

    public function test_the_base_revision_cannot_be_changed_from_the_browser(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(EditPage::class, ['record' => $page->slug])->set('baseRevision', 999);
    }

    public function test_it_validates(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide');

        Livewire::test(EditPage::class, ['record' => $page->slug])
            ->fillForm(['title' => ''])
            ->call('save')
            ->assertHasFormErrors(['title' => 'required']);

        Livewire::test(EditPage::class, ['record' => $page->slug])
            ->fillForm(['title' => '???'])
            ->call('save')
            ->assertHasFormErrors(['title']);

        $this->assertSame(1, $page->revisions()->count());
    }

    public function test_deleting_asks_first_then_removes_the_page_and_history(): void
    {
        $this->signIn();
        Event::fake([PageDeleted::class]);
        $page = $this->page('Deploy guide');

        Livewire::test(EditPage::class, ['record' => $page->slug])
            ->mountAction(DeleteAction::class)
            ->assertActionMounted(DeleteAction::class)
            ->callMountedAction()
            ->assertNotified('Page deleted')
            ->assertRedirect(PageResource::getUrl('index'));

        $this->assertSame(0, Page::query()->count());
        $this->assertSame(0, Revision::query()->count());
        Event::assertDispatched(PageDeleted::class);
    }

    public function test_only_editors_can_edit(): void
    {
        Gate::define('wiki-edit', fn ($user) => $user->name === 'Maya Okafor');
        $page = $this->page('Deploy guide');
        $this->signIn('Tom Reyes');

        $this->get(PageResource::getUrl('edit', ['record' => $page]))->assertForbidden();
        Livewire::test(EditPage::class, ['record' => $page->slug])->assertForbidden();
    }

    public function test_a_custom_can_edit_check_receives_the_panel_user(): void
    {
        $page = $this->page('Deploy guide');
        $this->plugin()->canEdit(fn ($user) => $user->email === 'maya@halyard.test');

        $this->signIn('Tom Reyes');
        $this->get(PageResource::getUrl('edit', ['record' => $page]))->assertForbidden();

        $this->signIn('Maya Okafor');
        $this->get(PageResource::getUrl('edit', ['record' => $page]))->assertOk();
    }

    public function test_can_edit_accepts_a_plain_boolean(): void
    {
        $page = $this->page('Deploy guide');
        $this->plugin()->canEdit(false);
        $this->signIn();

        $this->get(PageResource::getUrl('edit', ['record' => $page]))->assertForbidden();
        $this->get(PageResource::getUrl('view', ['record' => $page]))->assertOk();
    }

    public function test_a_missing_page_is_a_404(): void
    {
        $this->signIn();

        $this->get(PageResource::getUrl('edit', ['record' => 'nope']))->assertNotFound();
    }
}
