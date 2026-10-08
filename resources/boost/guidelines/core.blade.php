## ruvelo/filament-wiki

A Filament panel plugin for `ruvelo/laravel-wiki`: a Pages resource (table, create and edit with a live preview, delete), a History page with line diffs and restore, a Reader page that renders pages as the wiki does, and two dashboard widgets (Recent changes, Wanted pages). It works with Filament 4 and 5. The data and the write path are the wiki's own: this plugin adds no tables and no models.

### Registering it

Add the plugin to a panel provider. Every option is optional.

@verbatim
<code-snippet name="Register the plugin on a panel" lang="php">
use Ruvelo\FilamentWiki\WikiPlugin;

return $panel
    ->plugin(
        WikiPlugin::make()
            ->navigationGroup('Content')      // string, enum or closure
            ->navigationLabel('Handbook')     // default "Wiki"
            ->navigationIcon('heroicon-o-book-open')
            ->navigationSort(3)
            ->slug('handbook')                // URL: /admin/handbook (default "wiki")
            ->canEdit(fn ($user) => $user->is_admin) // default: the wiki's `wiki-edit` gate
            ->reader(true)                    // false: rows open the editor, no reader page
            ->publicLink(true)                // "Open on the wiki" buttons, only while wiki routes exist
            ->widgets(true)                   // or recentChangesWidget(false) / wantedPagesWidget(false)
    );
</code-snippet>
@endverbatim

- Read the panel's configuration with `WikiPlugin::get()` (the instance registered on the current panel), e.g. `WikiPlugin::get()->userCanEdit($user)`.
- Who can edit: by default `Ruvelo\Wiki\Wiki::canEdit($user)`, i.e. the `wiki-edit` gate (any signed-in user when no gate is defined). Change it with `canEdit()` or by defining the gate, not with a policy: the resource ignores model policies for wiki pages. Reading needs only panel access.
- URLs, with the default panel path and slug: `/admin/wiki` (pages), `/admin/wiki/_/new?title=` (create, title prefilled), `/admin/wiki/{page}` (reader), `/admin/wiki/{page}/edit`, `/admin/wiki/{page}/history`, where `{page}` is the page's slug. Build URLs with `Ruvelo\FilamentWiki\Resources\PageResource::getUrl('view', ['record' => $page])`.

### Saving

The create and edit pages save only through the wiki: `Page::draft($title)` then `$page->commit($title, $body, $summary, $user, basedOn: $revision)`. The editor remembers the revision it loaded and shows a "Someone else edited this page" notification instead of overwriting a newer save. When you write code next to the plugin, keep to the same path.

@verbatim
<code-snippet name="Change pages from your own code" lang="php">
use Ruvelo\Wiki\Wiki;

Wiki::write('Release notes', $markdown, 'v2.0 notes', $user); // create or update, with history
$page->commit($title, $body, 'Fix typo', $user, basedOn: $revisionId); // throws EditConflict if stale
</code-snippet>
@endverbatim

### Do not

- Do not edit wiki pages with a generic Filament resource, `Page::create()`, `$page->update()` or the query builder: the revision, the link index and the `PageSaved` event would be skipped.
- Do not render page Markdown with Filament's `->markdown()` or `MarkdownEditor` preview: it doesn't know `[[links]]`. Use `PageResource::prose($markdown)` (rendered with the wiki's renderer, links pointing into the panel) or `$page->html()`.
- Do not add Tailwind classes to customise the plugin's pages: they won't exist in Filament's compiled CSS. Use a custom Filament theme, or publish the views (`php artisan vendor:publish --tag=filament-wiki-views`).

### Colours

The plugin uses the panel's colours. The suggested primary is Ruvelo ultramarine; pass a palette with `#3D4EFF` at 600, since `Color::hex('#3D4EFF')` makes buttons lighter.

### Tests

Test the pages as Livewire components with Filament's helpers, and create pages through the wiki so they have history:

@verbatim
<code-snippet name="Test the plugin's pages" lang="php">
use Ruvelo\FilamentWiki\Resources\PageResource\Pages\EditPage;
use Ruvelo\Wiki\Models\Page;

$page = Page::factory()->titled('Deploy guide')->create();

Livewire::actingAs($editor)
    ->test(EditPage::class, ['record' => $page->slug])
    ->fillForm(['body' => 'New text', 'summary' => 'Rewrite'])
    ->call('save')
    ->assertNotified('Page saved');
</code-snippet>
@endverbatim
