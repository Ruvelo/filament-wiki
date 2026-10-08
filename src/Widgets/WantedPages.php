<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Widgets;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\Support\WantedPages as Wanted;

/**
 * Pages people link to that don't exist yet: the red links, most wanted first.
 */
class WantedPages extends TableWidget
{
    protected static ?int $sort = 20;

    public int $limit = 5;

    public static function canView(): bool
    {
        return PageResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Wanted pages')
            ->description('Linked to, but not written yet.')
            ->records(fn (): array => Wanted::top($this->limit))
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->label('Page')
                    ->weight('medium')
                    ->color('danger'),
                TextColumn::make('links')
                    ->label('Linked from')
                    ->formatStateUsing(fn (int $state): string => $state.' '.str('page')->plural($state)),
            ])
            ->recordActions([
                Action::make('write')
                    ->label('Write it')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (array $record): string => PageResource::getUrl('create', ['title' => $record['title']]))
                    ->visible(fn (): bool => PageResource::canCreate()),
            ])
            ->emptyStateHeading('No red links')
            ->emptyStateDescription('Every link goes to a page that exists.');
    }
}
