<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\ServiceProvider;
use Ruvelo\Wiki\Models\Page;
use Ruvelo\Wiki\Models\Revision;

class FilamentWikiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-wiki');

        // The pages table shows who edited each page last, in one query.
        if (! method_exists(Page::class, 'latestRevision')) {
            Page::resolveRelationUsing('latestRevision', fn (Page $page): HasOne => $page->hasOne(Revision::class)->latestOfMany());
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/filament-wiki'),
            ], 'filament-wiki-views');
        }
    }
}
