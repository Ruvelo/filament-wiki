<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Resources\PageResource\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\WikiPlugin;
use Ruvelo\Wiki\Models\Page;
use Ruvelo\Wiki\Models\Revision;
use Ruvelo\Wiki\Support\Diff;

/**
 * Every saved version of a page, what changed in each, and a way back.
 */
class PageHistory extends ManageRelatedRecords
{
    protected static string $resource = PageResource::class;

    protected static string $relationship = 'revisions';

    protected static ?string $navigationLabel = 'History';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        return $record instanceof Model && PageResource::canViewAny();
    }

    public function getTitle(): string
    {
        return 'History of “'.$this->page()->title.'”';
    }

    public function getBreadcrumb(): string
    {
        return 'History';
    }

    public function table(Table $table): Table
    {
        $current = $this->page()->currentRevisionId();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('author'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('Revision')
                    ->formatStateUsing(fn (int|string $state): string => '#'.$state)
                    ->badge()
                    ->color(fn (Revision $record): string => $record->id === $current ? 'primary' : 'gray')
                    ->description(fn (Revision $record): ?string => $record->id === $current ? 'Current' : null),
                TextColumn::make('created_at')
                    ->label('Saved')
                    ->since()
                    ->dateTimeTooltip(),
                TextColumn::make('author')
                    ->label('By')
                    ->state(fn (Revision $record): ?string => $record->authorName())
                    ->placeholder('Unknown'),
                TextColumn::make('summary')
                    ->label('Summary')
                    ->placeholder('No summary')
                    ->wrap(),
            ])
            ->recordActions([
                $this->changesAction(),
                $this->restoreAction($current),
            ])
            ->emptyStateHeading('No revisions yet');
    }

    protected function changesAction(): Action
    {
        return Action::make('changes')
            ->label('View changes')
            ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->color('gray')
            ->modalHeading(fn (Revision $record): string => 'Revision #'.$record->id)
            ->modalDescription(fn (Revision $record): string => trim(($record->authorName() ?? 'Someone').', '.$record->created_at->toDayDateTimeString().($record->summary ? ' · '.$record->summary : '')))
            ->modalContent(fn (Revision $record): View => $this->diffView($record))
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    protected function restoreAction(?int $current): Action
    {
        return Action::make('restore')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->requiresConfirmation()
            ->modalHeading(fn (Revision $record): string => 'Restore revision #'.$record->id.'?')
            ->modalDescription('This saves the page as it was in that revision. The history keeps every version, so you can undo it.')
            ->modalSubmitActionLabel('Restore this version')
            ->visible(fn (Revision $record): bool => $record->id !== $current && PageResource::canEdit($this->page()))
            ->action(function (Revision $record): void {
                // Same as the wiki's own restore: a new revision with the old content.
                $this->page()->commit($record->title, $record->body, "Restored revision #{$record->id}", Filament::auth()->user());

                Notification::make()->success()->title("Restored revision #{$record->id}")->send();

                $this->redirect(WikiPlugin::get()->hasReader()
                    ? PageResource::getUrl('view', ['record' => $this->page()])
                    : PageResource::getUrl('history', ['record' => $this->page()]));
            });
    }

    protected function diffView(Revision $revision): View
    {
        $previous = $revision->previous();
        $ops = Diff::lines($previous->body ?? '', $revision->body);

        return view('filament-wiki::diff', [
            'revision' => $revision,
            'previous' => $previous,
            'diff' => Diff::collapse($ops),
            'stats' => Diff::stats($ops),
        ]);
    }

    private function page(): Page
    {
        /** @var Page */
        return $this->getOwnerRecord();
    }
}
