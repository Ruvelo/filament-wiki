<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Resources;

use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Panel;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Ruvelo\FilamentWiki\Resources\PageResource\Pages\CreatePage;
use Ruvelo\FilamentWiki\Resources\PageResource\Pages\EditPage;
use Ruvelo\FilamentWiki\Resources\PageResource\Pages\ListPages;
use Ruvelo\FilamentWiki\Resources\PageResource\Pages\PageHistory;
use Ruvelo\FilamentWiki\Resources\PageResource\Pages\ReadPage;
use Ruvelo\FilamentWiki\Support\PanelLinks;
use Ruvelo\FilamentWiki\WikiPlugin;
use Ruvelo\Wiki\Models\Page;
use Ruvelo\Wiki\Models\Revision;
use Ruvelo\Wiki\Support\Navigation;
use Ruvelo\Wiki\Wiki;
use UnitEnum;

/**
 * Wiki pages in the panel. Every save goes through the wiki's own
 * Page::draft() / commit(), so revisions, links and events stay right.
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'page';

    protected static ?string $pluralModelLabel = 'pages';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getSlug(?Panel $panel = null): string
    {
        $panel ??= filament()->getCurrentOrDefaultPanel();

        return $panel !== null && $panel->hasPlugin(WikiPlugin::ID)
            ? self::pluginFor($panel)->getSlug()
            : WikiPlugin::make()->getSlug();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return WikiPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return WikiPlugin::get()->getNavigationLabel();
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return WikiPlugin::get()->getNavigationIcon();
    }

    public static function getNavigationSort(): ?int
    {
        return WikiPlugin::get()->getNavigationSort();
    }

    /**
     * Anyone who can use the panel can read the wiki; writing needs the
     * plugin's canEdit() check (the `wiki-edit` gate by default). Model
     * policies for wiki pages are not consulted.
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $ability = match (true) {
            $action instanceof BackedEnum => (string) $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        return match ($ability) {
            'viewAny', 'view' => Response::allow(),
            'create', 'update', 'delete', 'deleteAny' => WikiPlugin::get()->userCanEdit()
                ? Response::allow()
                : Response::deny('Only editors can change the wiki.'),
            default => Response::deny(),
        };
    }

    /**
     * @return Builder<Page>
     */
    public static function getEloquentQuery(): Builder
    {
        $page = new Page;
        $links = Page::linksTable();

        $backlinks = $page->getConnection()->table($links)
            ->selectRaw('count(distinct page_id)')
            ->whereColumn("{$links}.target_slug", $page->qualifyColumn('slug'))
            ->when(Navigation::sidebarSlug(), fn ($query, string $sidebar) => $query->whereNotIn(
                'page_id',
                Page::query()->select('id')->where('slug', $sidebar)->toBase(),
            ));

        /** @var Builder<Page> */
        return parent::getEloquentQuery()
            ->select($page->qualifyColumn('*'))
            ->selectSub($backlinks, 'backlinks_count')
            ->withCount('revisions')
            ->with('latestRevision.author');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255)
                    ->autofocus(fn (string $operation): bool => $operation === 'create')
                    ->default(fn (): string => is_string($title = request()->query('title')) ? $title : '')
                    ->rules([fn (string $operation): Closure => self::titleRule($operation)]),
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        MarkdownEditor::make('body')
                            ->label('Text')
                            ->helperText(new HtmlString('Link to other pages with <code>[[Page title]]</code>. Add <code>[TOC]</code> for a table of contents.'))
                            ->toolbarButtons([
                                ['bold', 'italic', 'strike', 'link'],
                                ['heading'],
                                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                                ['table'],
                                ['undo', 'redo'],
                            ])
                            ->minHeight('24rem')
                            ->live(debounce: 600),
                        Section::make('Preview')
                            ->compact()
                            ->extraAttributes(['class' => 'fi-wiki-preview'])
                            ->schema([
                                Html::make(fn (Get $get): HtmlString => self::prose((string) $get('body'), 'Nothing to preview yet.')),
                            ]),
                    ]),
                TextInput::make('summary')
                    ->label('Edit summary')
                    ->maxLength(255)
                    ->placeholder(fn (string $operation): string => $operation === 'create' ? 'Created page' : 'What did you change?')
                    ->helperText('Shown in the page history.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->weight('medium')
                    ->searchable(['title', 'body'])
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('author')
                    ->label('By')
                    ->state(fn (Page $record): ?string => self::latestRevision($record)?->authorName())
                    ->placeholder('Unknown'),
                TextColumn::make('revisions_count')
                    ->label('Revisions')
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('revisions_count', $direction === 'desc' ? 'desc' : 'asc')),
                TextColumn::make('backlinks_count')
                    ->label('Backlinks')
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('backlinks_count', $direction === 'desc' ? 'desc' : 'asc')),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Page $record): ?string => match (true) {
                WikiPlugin::get()->hasReader() => static::getUrl('view', ['record' => $record]),
                static::canEdit($record) => static::getUrl('edit', ['record' => $record]),
                default => null,
            })
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // One by one, so the wiki's PageDeleted event fires for each.
                    DeleteBulkAction::make()->fetchSelectedRecords(),
                ]),
            ])
            ->emptyStateHeading('The wiki is empty')
            ->emptyStateDescription('Write the first page.');
    }

    public static function getPages(): array
    {
        // Slugs never contain "_", so "/_/new" can't collide with a page.
        // The reader's route always exists; reader(false) denies access to it.
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/_/new'),
            'view' => ReadPage::route('/{record}'),
            'edit' => EditPage::route('/{record}/edit'),
            'history' => PageHistory::route('/{record}/history'),
        ];
    }

    public static function getRecordSubNavigation(ResourcePage $page): array
    {
        return $page->generateNavigationItems([
            ReadPage::class,
            EditPage::class,
            PageHistory::class,
        ]);
    }

    /**
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }

    /**
     * Markdown to HTML with the wiki's renderer, links pointing into the panel.
     */
    public static function render(string $markdown): string
    {
        return PanelLinks::rewrite(
            Wiki::render($markdown),
            fn (string $slug): ?string => self::pageUrl($slug),
            fn (string $title): ?string => static::canCreate() ? static::getUrl('create', ['title' => $title]) : null,
        );
    }

    /**
     * Rendered Markdown in Filament's prose styles.
     */
    public static function prose(string $markdown, string $empty = 'This page is empty.'): HtmlString
    {
        $html = trim($markdown) === ''
            ? '<p class="fi-wiki-empty">'.e($empty).'</p>'
            : static::render($markdown);

        return new HtmlString('<div class="fi-prose fi-wiki-prose">'.$html.'</div>');
    }

    /**
     * Where a page opens in the panel: the reader, else the editor.
     */
    public static function pageUrl(string $slug): ?string
    {
        if (WikiPlugin::get()->hasReader()) {
            return static::getUrl('view', ['record' => $slug]);
        }

        return static::canCreate() ? static::getUrl('edit', ['record' => $slug]) : null;
    }

    public static function latestRevision(Page $page): ?Revision
    {
        $revision = $page->getRelationValue('latestRevision');

        return $revision instanceof Revision ? $revision : null;
    }

    private static function titleRule(string $operation): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($operation): void {
            $slug = Page::slugFor(trim((string) $value));

            if ($slug === '') {
                $fail('A page title needs at least one letter or number.');
            } elseif ($operation === 'create' && Page::query()->where('slug', $slug)->exists()) {
                // Renaming keeps a page's slug, so only new pages can clash.
                $fail('A page with this title already exists.');
            }
        };
    }

    private static function pluginFor(Panel $panel): WikiPlugin
    {
        $plugin = $panel->getPlugin(WikiPlugin::ID);

        return $plugin instanceof WikiPlugin ? $plugin : WikiPlugin::make();
    }
}
