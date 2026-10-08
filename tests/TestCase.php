<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Tests;

use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Orchestra\Testbench\TestCase as Orchestra;
use PHPUnit\Framework\Assert;
use Ruvelo\FilamentWiki\FilamentWikiServiceProvider;
use Ruvelo\FilamentWiki\Tests\Fixtures\AdminPanelProvider;
use Ruvelo\FilamentWiki\Tests\Fixtures\User;
use Ruvelo\FilamentWiki\WikiPlugin;
use Ruvelo\Wiki\Models\Page;
use Ruvelo\Wiki\Wiki;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * What package discovery loads in an app, in the same order (by package
     * name): Filament has to register before Livewire. Filament 4.0 has no
     * query builder package, so only what's installed is loaded.
     */
    public const PROVIDERS = [
        'BladeUI\\Heroicons\\BladeHeroiconsServiceProvider',
        'BladeUI\\Icons\\BladeIconsServiceProvider',
        'Filament\\Actions\\ActionsServiceProvider',
        'Filament\\FilamentServiceProvider',
        'Filament\\Forms\\FormsServiceProvider',
        'Filament\\Infolists\\InfolistsServiceProvider',
        'Filament\\Notifications\\NotificationsServiceProvider',
        'Filament\\QueryBuilder\\QueryBuilderServiceProvider',
        'Filament\\Schemas\\SchemasServiceProvider',
        'Filament\\Support\\SupportServiceProvider',
        'Filament\\Tables\\TablesServiceProvider',
        'Filament\\Widgets\\WidgetsServiceProvider',
        'Kirschbaum\\PowerJoins\\PowerJoinsServiceProvider',
        'Livewire\\LivewireServiceProvider',
        'Ruvelo\\Wiki\\WikiServiceProvider',
        'RyanChandler\\BladeCaptureDirective\\BladeCaptureDirectiveServiceProvider',
    ];

    protected function getPackageProviders($app): array
    {
        return [
            ...array_values(array_filter(self::PROVIDERS, class_exists(...))),
            FilamentWikiServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('f', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Livewire 4 sends action modals as partial renders; Livewire 3
        // renders them inline. This sees both.
        Testable::macro('assertModalSee', function (string $html): Testable {
            /** @var Testable $this */
            $partials = data_get($this->lastState->getEffects(), 'partials', []);
            Assert::assertStringContainsString($html, implode('', (array) $partials).$this->html());

            return $this;
        });
        Testable::macro('assertModalDontSee', function (string $html): Testable {
            /** @var Testable $this */
            $partials = data_get($this->lastState->getEffects(), 'partials', []);
            Assert::assertStringNotContainsString($html, implode('', (array) $partials).$this->html());

            return $this;
        });
    }

    protected function tearDown(): void
    {
        Wiki::flush();

        parent::tearDown();
    }

    protected function user(string $name = 'Maya Okafor'): User
    {
        return User::query()->firstOrCreate(
            ['email' => strtolower(strtok($name, ' ') ?: $name).'@halyard.test'],
            ['name' => $name, 'password' => 'secret'],
        );
    }

    protected function signIn(string $name = 'Maya Okafor'): User
    {
        $user = $this->user($name);
        $this->actingAs($user);

        return $user;
    }

    protected function plugin(): WikiPlugin
    {
        return WikiPlugin::get();
    }

    protected function page(string $title, string $body = 'Some text.', ?User $author = null, string $summary = 'Created page'): Page
    {
        $page = Page::draft($title);
        $page->commit($title, $body, $summary, $author);

        return $page;
    }
}
