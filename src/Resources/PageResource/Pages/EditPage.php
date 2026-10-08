<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Resources\PageResource\Pages;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\WikiPlugin;
use Ruvelo\Wiki\Exceptions\EditConflict;
use Ruvelo\Wiki\Models\Page;

/**
 * @property-read Page $record
 */
class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected static ?string $navigationLabel = 'Edit';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    /**
     * The revision this editor started from. Saving fails, rather than
     * overwriting, if someone else has saved since.
     */
    #[Locked]
    public ?int $baseRevision = null;

    public function getTitle(): string
    {
        return 'Edit “'.$this->page()->title.'”';
    }

    protected function afterFill(): void
    {
        $this->baseRevision = $this->page()->currentRevisionId();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Page $record */
        $title = trim((string) $data['title']);
        $body = str_replace("\r\n", "\n", (string) ($data['body'] ?? ''));
        $summary = trim((string) ($data['summary'] ?? ''));

        if ($record->isUnchanged($title, $body)) {
            Notification::make()->title('No changes to save')->info()->send();

            throw new Halt;
        }

        try {
            $record->commit($title, $body, $summary === '' ? null : $summary, Filament::auth()->user(), $this->baseRevision);
        } catch (EditConflict) {
            // The form keeps the editor's text, so nothing is lost.
            Notification::make()
                ->danger()
                ->title('Someone else edited this page')
                ->body('They saved while you were working on it, so your changes weren’t saved. Copy your text, reload the page, and apply your changes again.')
                ->persistent()
                ->send();

            throw new Halt;
        }

        $this->baseRevision = $record->currentRevisionId();
        $this->data['summary'] = null;

        return $record;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Page saved';
    }

    protected function getRedirectUrl(): ?string
    {
        return WikiPlugin::get()->hasReader()
            ? PageResource::getUrl('view', ['record' => $this->page()])
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Delete page')
                ->modalHeading(fn (): string => 'Delete “'.$this->page()->title.'”?')
                ->modalDescription('The page and its whole history are deleted. Links to it turn red.')
                ->modalSubmitActionLabel('Delete page')
                ->successNotificationTitle('Page deleted'),
        ];
    }

    private function page(): Page
    {
        /** @var Page */
        return $this->getRecord();
    }
}
