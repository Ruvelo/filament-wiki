<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Ruvelo\FilamentWiki\Resources\PageResource;
use Ruvelo\FilamentWiki\Tests\TestCase;
use Ruvelo\FilamentWiki\Widgets\RecentChanges;
use Ruvelo\FilamentWiki\Widgets\WantedPages;
use Ruvelo\FilamentWiki\WikiPlugin;

class PluginTest extends TestCase
{
    public function test_it_registers_the_resource_on_the_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasPlugin('ruvelo-wiki'));
        $this->assertContains(PageResource::class, $panel->getResources());
        $this->assertSame($panel->getPlugin('ruvelo-wiki'), WikiPlugin::get());
    }

    public function test_it_has_sensible_navigation_defaults(): void
    {
        $this->assertSame('Wiki', PageResource::getNavigationLabel());
        $this->assertSame(Heroicon::OutlinedBookOpen, PageResource::getNavigationIcon());
        $this->assertNull(PageResource::getNavigationGroup());
        $this->assertNull(PageResource::getNavigationSort());
        $this->assertSame('wiki', PageResource::getSlug());
        $this->assertStringEndsWith('/admin/wiki', PageResource::getUrl('index'));
        $this->assertStringEndsWith('/admin/wiki/_/new', PageResource::getUrl('create'));
    }

    public function test_navigation_is_configurable(): void
    {
        $this->plugin()
            ->navigationGroup('Content')
            ->navigationLabel(fn () => 'Handbook')
            ->navigationIcon('heroicon-o-academic-cap')
            ->navigationSort(3);

        $this->assertSame('Content', PageResource::getNavigationGroup());
        $this->assertSame('Handbook', PageResource::getNavigationLabel());
        $this->assertSame('heroicon-o-academic-cap', PageResource::getNavigationIcon());
        $this->assertSame(3, PageResource::getNavigationSort());

        $this->signIn();
        $this->get(PageResource::getUrl('index'))->assertOk()->assertSee('Handbook')->assertSee('Content');
    }

    public function test_the_url_segment_is_configurable(): void
    {
        $panel = Panel::make()->id('docs')->path('docs')->plugin(WikiPlugin::make()->slug('/handbook/'));

        $this->assertSame('handbook', PageResource::getSlug($panel));
        $this->assertSame('wiki', PageResource::getSlug(Panel::make()->id('other')));
    }

    public function test_editing_defaults_to_the_wiki_edit_gate(): void
    {
        $maya = $this->user('Maya Okafor');
        $tom = $this->user('Tom Reyes');

        $this->assertTrue($this->plugin()->userCanEdit($maya));
        $this->assertFalse($this->plugin()->userCanEdit());

        Gate::define('wiki-edit', fn (Authenticatable $user) => $user->getAuthIdentifier() === $maya->id);

        $this->assertTrue($this->plugin()->userCanEdit($maya));
        $this->assertFalse($this->plugin()->userCanEdit($tom));

        $this->actingAs($maya);
        $this->assertTrue($this->plugin()->userCanEdit());
        $this->assertTrue(PageResource::canCreate());
    }

    public function test_a_can_edit_closure_can_ask_for_the_user_by_type(): void
    {
        $maya = $this->user('Maya Okafor');
        $this->plugin()->canEdit(fn (Authenticatable $editor): bool => $editor->getAuthIdentifier() === $maya->id);

        $this->assertTrue($this->plugin()->userCanEdit($maya));
        $this->assertFalse($this->plugin()->userCanEdit($this->user('Tom Reyes')));
    }

    public function test_guests_can_never_edit(): void
    {
        $this->plugin()->canEdit(true);

        $this->assertFalse($this->plugin()->userCanEdit());
    }

    public function test_abilities_filament_has_no_use_for_are_denied(): void
    {
        $this->signIn();
        $page = $this->page('Deploy guide');

        $this->assertTrue(PageResource::canViewAny());
        $this->assertTrue(PageResource::canView($page));
        $this->assertTrue(PageResource::canEdit($page));
        $this->assertTrue(PageResource::canDelete($page));
        $this->assertFalse(PageResource::canForceDelete($page));
        $this->assertFalse(PageResource::canReplicate($page));
        $this->assertFalse(PageResource::canReorder());

        $this->plugin()->reader(false);
        $this->assertFalse(PageResource::canView($page));
    }

    public function test_widgets_can_be_left_off(): void
    {
        $panel = Panel::make()->id('docs')->path('docs');
        WikiPlugin::make()->widgets(false)->register($panel);
        $this->assertNotContains(RecentChanges::class, $panel->getWidgets());
        $this->assertNotContains(WantedPages::class, $panel->getWidgets());

        $panel = Panel::make()->id('docs')->path('docs');
        WikiPlugin::make()->wantedPagesWidget(false)->register($panel);
        $this->assertContains(RecentChanges::class, $panel->getWidgets());
        $this->assertNotContains(WantedPages::class, $panel->getWidgets());

        $panel = Panel::make()->id('docs')->path('docs');
        WikiPlugin::make()->recentChangesWidget(false)->register($panel);
        $this->assertNotContains(RecentChanges::class, $panel->getWidgets());
        $this->assertContains(WantedPages::class, $panel->getWidgets());
    }

    public function test_the_public_link_needs_the_wiki_routes(): void
    {
        $this->assertTrue($this->plugin()->hasPublicLink());
        $this->assertFalse(WikiPlugin::make()->publicLink(false)->hasPublicLink());
        $this->assertFalse(WikiPlugin::make()->publicLink(fn () => false)->hasPublicLink());
    }

    public function test_it_adds_its_few_styles_to_the_panel(): void
    {
        $this->signIn();

        $this->get(PageResource::getUrl('index'))->assertSee('.fi-wiki-diff', escape: false);
    }

    public function test_the_boost_guidelines_render(): void
    {
        $guidelines = Blade::render((string) file_get_contents(__DIR__.'/../../resources/boost/guidelines/core.blade.php'));

        $this->assertStringContainsString('## ruvelo/filament-wiki', $guidelines);
        $this->assertStringContainsString('WikiPlugin::make()', $guidelines);
    }

    public function test_get_falls_back_to_defaults_outside_a_panel_with_the_plugin(): void
    {
        Filament::setCurrentPanel(Panel::make()->id('bare')->path('bare'));

        $this->assertNotSame(Filament::getPanel('admin')->getPlugin('ruvelo-wiki'), WikiPlugin::get());
        $this->assertSame('Wiki', WikiPlugin::get()->getNavigationLabel());
    }
}
