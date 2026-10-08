<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Resources\PageResource\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\WikiPlugin;
use Ruvelo\Wiki\Models\Page;
use Ruvelo\Wiki\Support\Navigation;

/**
 * A page as the wiki shows it: rendered Markdown with working [[links]],
 * red links to pages that don't exist yet, an outline and backlinks.
 */
class ReadPage extends ResourcePage
{
    use InteractsWithRecord;

    protected static string $resource = PageResource::class;

    protected static ?string $navigationLabel = 'Read';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        return parent::canAccess($parameters)
            && ($record instanceof Model ? PageResource::canView($record) : WikiPlugin::get()->hasReader());
    }

    public function getTitle(): string
    {
        return $this->page()->title;
    }

    /**
     * The handbook, then the page: no "Read" crumb after it.
     *
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        return [...$this->getResourceBreadcrumbs(), $this->getTitle()];
    }

    public function getSubheading(): string
    {
        $page = $this->page();
        $author = $page->revisions()->with('author')->first()?->authorName();

        return 'Last edited '.$page->updated_at->diffForHumans().($author === null ? '' : ' by '.$author);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openOnWiki')
                ->label('Open on the wiki')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => route('wiki.show', $this->page()->slug), shouldOpenInNewTab: true)
                ->visible(fn (): bool => WikiPlugin::get()->hasPublicLink()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $page = $this->page();
        $prose = PageResource::prose($page->body);
        $outline = Navigation::outline($prose->toHtml());
        $backlinks = $page->backlinks()->orderBy('title')->get(['id', 'title', 'slug']);
        $hasAside = count($outline) > 1 || $backlinks->isNotEmpty();

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 4])->schema([
                Section::make()
                    ->schema([Html::make($prose)])
                    ->columnSpan(['default' => 1, 'lg' => $hasAside ? 3 : 4]),
                Group::make([
                    Section::make('On this page')
                        ->compact()
                        ->schema([Html::make($this->list(array_map(fn (array $heading): array => [
                            'url' => '#'.$heading['id'],
                            'text' => $heading['text'],
                            'sub' => $heading['level'] === 3,
                        ], $outline)))])
                        ->visible(count($outline) > 1),
                    Section::make('What links here')
                        ->compact()
                        ->schema([Html::make($this->list($backlinks->map(fn (Page $backlink): array => [
                            'url' => (string) PageResource::pageUrl($backlink->slug),
                            'text' => $backlink->title,
                            'sub' => false,
                        ])->all()))])
                        ->visible($backlinks->isNotEmpty()),
                ])
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->visible($hasAside),
            ]),
        ]);
    }

    /**
     * @param  array<array{url: string, text: string, sub: bool}>  $items
     */
    private function list(array $items): HtmlString
    {
        $html = '';
        foreach ($items as $item) {
            $html .= '<li'.($item['sub'] ? ' class="fi-wiki-sub"' : '').'><a href="'.e($item['url']).'">'.e($item['text']).'</a></li>';
        }

        return new HtmlString('<ul class="fi-wiki-list">'.$html.'</ul>');
    }

    private function page(): Page
    {
        /** @var Page */
        return $this->getRecord();
    }
}
