<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\Widgets\RecentChanges;
use Ruvelo\FilamentWiki\Widgets\WantedPages;
use Ruvelo\Wiki\Wiki;
use UnitEnum;

/**
 * Brings ruvelo/laravel-wiki into a Filament panel.
 *
 *     $panel->plugin(WikiPlugin::make()->navigationGroup('Content'));
 */
class WikiPlugin implements Plugin
{
    use EvaluatesClosures;

    public const ID = 'ruvelo-wiki';

    protected string|UnitEnum|Closure|null $navigationGroup = null;

    protected string|Closure $navigationLabel = 'Wiki';

    protected string|BackedEnum|Htmlable|Closure|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected int|Closure|null $navigationSort = null;

    protected string $slug = 'wiki';

    protected bool|Closure|null $canEdit = null;

    protected bool|Closure $hasReader = true;

    protected bool|Closure $hasPublicLink = true;

    protected bool $hasRecentChangesWidget = true;

    protected bool $hasWantedPagesWidget = true;

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * The plugin registered on the current panel, or one with the defaults.
     */
    public static function get(): static
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($panel !== null && $panel->hasPlugin(self::ID)) {
            /** @var static */
            return $panel->getPlugin(self::ID);
        }

        return static::make();
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel->resources([PageResource::class]);

        $panel->widgets(array_values(array_filter([
            $this->hasRecentChangesWidget ? RecentChanges::class : null,
            $this->hasWantedPagesWidget ? WantedPages::class : null,
        ])));

        // A few rules for what Filament's theme doesn't style: diffs, red
        // links and the outline. Scoped to fi-wiki-*, built on the theme's
        // own colour variables.
        $panel->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => view('filament-wiki::styles')->render());
    }

    public function boot(Panel $panel): void {}

    public function navigationGroup(string|UnitEnum|Closure|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->evaluate($this->navigationGroup);
    }

    public function navigationLabel(string|Closure $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): string
    {
        return (string) $this->evaluate($this->navigationLabel);
    }

    public function navigationIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->evaluate($this->navigationIcon);
    }

    public function navigationSort(int|Closure|null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->evaluate($this->navigationSort);
    }

    /**
     * The URL segment the wiki lives under in the panel: /admin/{slug}.
     */
    public function slug(string $slug): static
    {
        $this->slug = trim($slug, '/');

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Who may create, edit, delete and restore pages. A closure receives the
     * panel user as `$user`. Defaults to the wiki's `wiki-edit` gate.
     */
    public function canEdit(bool|Closure|null $condition): static
    {
        $this->canEdit = $condition;

        return $this;
    }

    public function userCanEdit(?Authenticatable $user = null): bool
    {
        $user ??= Filament::auth()->user();

        if ($user === null) {
            return false;
        }

        if ($this->canEdit === null) {
            return Wiki::canEdit($user);
        }

        return (bool) $this->evaluate($this->canEdit, ['user' => $user], [Authenticatable::class => $user]);
    }

    /**
     * Show pages rendered as the wiki shows them, with links, red links and
     * an outline. Without it, rows open the editor.
     */
    public function reader(bool|Closure $condition = true): static
    {
        $this->hasReader = $condition;

        return $this;
    }

    public function hasReader(): bool
    {
        return (bool) $this->evaluate($this->hasReader);
    }

    /**
     * Link from the panel to the page on the public wiki. Only shown while
     * the wiki's own routes are registered.
     */
    public function publicLink(bool|Closure $condition = true): static
    {
        $this->hasPublicLink = $condition;

        return $this;
    }

    public function hasPublicLink(): bool
    {
        return (bool) $this->evaluate($this->hasPublicLink) && Route::has('wiki.show');
    }

    public function recentChangesWidget(bool $condition = true): static
    {
        $this->hasRecentChangesWidget = $condition;

        return $this;
    }

    public function wantedPagesWidget(bool $condition = true): static
    {
        $this->hasWantedPagesWidget = $condition;

        return $this;
    }

    /**
     * Turn both dashboard widgets on or off.
     */
    public function widgets(bool $condition = true): static
    {
        $this->hasRecentChangesWidget = $condition;
        $this->hasWantedPagesWidget = $condition;

        return $this;
    }
}
