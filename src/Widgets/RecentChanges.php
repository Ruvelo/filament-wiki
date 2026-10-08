<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\Wiki\Models\Revision;

/**
 * The latest saves across the wiki, newest first.
 */
class RecentChanges extends TableWidget
{
    protected static ?int $sort = 21;

    protected int|string|array $columnSpan = 'full';

    public int $limit = 8;

    public static function canView(): bool
    {
        return PageResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent changes')
            ->query(fn (): Builder => Revision::query()
                ->with(['page:id,title,slug', 'author'])
                ->select(['id', 'page_id', 'title', 'summary', 'user_id', 'created_at'])
                ->orderByDesc('id')
                ->limit($this->limit))
            ->paginated(false)
            ->columns([
                TextColumn::make('page.title')
                    ->label('Page')
                    ->weight('medium')
                    ->description(fn (Revision $record): ?string => $record->summary),
                TextColumn::make('author')
                    ->label('By')
                    ->state(fn (Revision $record): ?string => $record->authorName())
                    ->placeholder('Unknown'),
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->dateTimeTooltip(),
            ])
            ->recordUrl(fn (Revision $record): ?string => $record->page === null ? null : PageResource::pageUrl($record->page->slug))
            ->emptyStateHeading('No changes yet')
            ->emptyStateDescription('Edits to the wiki show up here.');
    }
}
