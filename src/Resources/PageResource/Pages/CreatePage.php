<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Resources\PageResource\Pages;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\WikiPlugin;
use Ruvelo\Wiki\Exceptions\InvalidTitle;
use Ruvelo\Wiki\Exceptions\PageAlreadyExists;
use Ruvelo\Wiki\Models\Page;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'New page';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $title = trim((string) $data['title']);
        $body = str_replace("\r\n", "\n", (string) ($data['body'] ?? ''));
        $summary = trim((string) ($data['summary'] ?? ''));

        try {
            $page = Page::draft($title);
            $page->commit($title, $body, $summary === '' ? 'Created page' : $summary, Filament::auth()->user());
        } catch (InvalidTitle|PageAlreadyExists|UniqueConstraintViolationException) {
            // Validation catches these first; this is someone else creating
            // the same page while the form was being saved.
            Notification::make()
                ->danger()
                ->title('The page wasn’t created')
                ->body('A page with this title was created a moment ago. Change the title, or edit that page instead.')
                ->persistent()
                ->send();

            throw new Halt;
        }

        return $page;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Page created';
    }

    protected function getRedirectUrl(): string
    {
        /** @var Page $page */
        $page = $this->getRecord();

        return WikiPlugin::get()->hasReader()
            ? PageResource::getUrl('view', ['record' => $page])
            : PageResource::getUrl('edit', ['record' => $page]);
    }
}
