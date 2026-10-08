<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Resources\PageResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\WikiPlugin;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        return WikiPlugin::get()->getNavigationLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openWiki')
                ->label('Open the wiki')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => route('wiki.home'), shouldOpenInNewTab: true)
                ->visible(fn (): bool => WikiPlugin::get()->hasPublicLink()),
            CreateAction::make()->label('New page'),
        ];
    }
}
