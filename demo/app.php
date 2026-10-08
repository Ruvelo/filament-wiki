<?php

declare(strict_types=1);

/*
 * The Halyard admin panel the screenshots are taken of: Filament, the wiki and
 * this plugin in a Testbench app, signed in as Maya, served by `php -S`.
 *
 *   php demo/app.php seed          # build/demo: database, Filament assets
 *   php -S 127.0.0.1:8940 -t build/demo/public demo/server.php
 *
 * demo/screenshots.sh does both. Filament pages are Livewire components, so
 * they need this live server; the published site is a gallery of the shots.
 */

use Filament\Http\Middleware\Authenticate;
use Filament\Models\Contracts\FilamentUser;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Orchestra\Testbench\Foundation\Application;
use Ruvelo\FilamentWiki\FilamentWikiServiceProvider;
use Ruvelo\FilamentWiki\Tests\TestCase;
use Ruvelo\FilamentWiki\WikiPlugin;
use Ruvelo\Wiki\Models\Page;

require __DIR__.'/../vendor/autoload.php';

const DEMO_DIR = __DIR__.'/../build/demo';
const DEMO_URL = 'http://127.0.0.1:8940';

/** Ruvelo ultramarine as a Filament palette, with #3D4EFF at 600 for buttons. */
const ULTRAMARINE = [
    50 => '#EEF0FF', 100 => '#E0E3FF', 200 => '#C7CCFF', 300 => '#A5ADFF', 400 => '#8F9BFF', 500 => '#6573FF',
    600 => '#3D4EFF', 700 => '#2F3DE0', 800 => '#2733B5', 900 => '#232D8C', 950 => '#1E2150',
];

class DemoUser extends User implements FilamentUser
{
    protected $table = 'users';

    protected $guarded = [];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}

/** Every visitor is Maya, who edits the handbook. */
class SignInAsMaya extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        if (Auth::guest() && ($maya = DemoUser::query()->find(1)) !== null) {
            Auth::login($maya);
        }

        parent::authenticate($request, $guards);
    }
}

class HalyardPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Halyard')
            ->colors(['primary' => ULTRAMARINE])
            ->pages([Dashboard::class])
            ->widgets([AccountWidget::class])
            ->navigationItems([
                NavigationItem::make('Customers')->icon(Heroicon::OutlinedUsers)->group('Billing')->url('#')->sort(1),
                NavigationItem::make('Invoices')->icon(Heroicon::OutlinedDocumentText)->group('Billing')->url('#')->sort(2),
            ])
            ->plugin(WikiPlugin::make()->navigationGroup('Content')->navigationLabel('Handbook'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
            ])
            ->authMiddleware([SignInAsMaya::class]);
    }
}

/** Registered first, so everything after it sees the demo's settings. */
class DemoConfig extends ServiceProvider
{
    public function register(): void
    {
        $this->app->usePublicPath((string) realpath(DEMO_DIR.'/public'));

        $this->app['config']->set([
            'app.key' => 'base64:'.base64_encode(str_repeat('h', 32)),
            'app.url' => DEMO_URL,
            'app.env' => 'local',
            'app.debug' => true,
            'auth.providers.users.model' => DemoUser::class,
            'database.default' => 'demo',
            'database.connections.demo' => ['driver' => 'sqlite', 'database' => realpath(DEMO_DIR.'/demo.sqlite'), 'foreign_key_constraints' => true],
            'session.driver' => 'file',
            'session.files' => realpath(DEMO_DIR.'/storage/framework/sessions'),
            'wiki.name' => 'Halyard Handbook',
        ]);
    }
}

function demo_app(): LaravelApplication
{
    is_dir(DEMO_DIR.'/storage/framework/sessions') || mkdir(DEMO_DIR.'/storage/framework/sessions', 0777, true);
    is_dir(DEMO_DIR.'/public') || mkdir(DEMO_DIR.'/public', 0777, true);
    touch(DEMO_DIR.'/demo.sqlite');

    $providers = array_values(array_filter(TestCase::PROVIDERS, class_exists(...)));

    return Application::create(
        basePath: null,
        options: ['extra' => ['providers' => [DemoConfig::class, ...$providers, FilamentWikiServiceProvider::class, HalyardPanelProvider::class]]],
    );
}

function demo_seed(LaravelApplication $app): void
{
    Schema::dropAllTables();
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });
    $app->make(ConsoleKernel::class)->call('migrate', ['--force' => true]);

    $people = [];
    foreach (['Maya Okafor', 'Tom Reyes', 'Inès Laurent', 'Kenji Mori'] as $name) {
        $first = (string) strtok($name, ' ');
        $people[$first] = DemoUser::forceCreate([
            'name' => $name,
            'email' => strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $first)).'@halyard.test',
            'password' => bin2hex(random_bytes(16)),
        ]);
    }

    $now = Carbon::now();
    foreach (require __DIR__.'/content.php' as [$ago, $who, $title, $body, $summary]) {
        Carbon::setTestNow($now->copy()->modify("-{$ago}"));
        $page = Page::query()->firstOrNew(['slug' => Page::slugFor($title)]);
        $page->commit($title, trim($body)."\n", $summary, $people[$who]);
        Carbon::setTestNow();
    }

    $app->make(ConsoleKernel::class)->call('filament:assets');
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (($argv[1] ?? null) !== 'seed') {
        fwrite(STDERR, "Usage: php demo/app.php seed\n");
        exit(1);
    }

    @unlink(DEMO_DIR.'/demo.sqlite');
    $app = demo_app();
    demo_seed($app);

    $deploy = Page::query()->where('slug', 'deploy-guide')->firstOrFail();
    file_put_contents(DEMO_DIR.'/deploy-revision', (string) $deploy->revisions()->value('id'));

    fwrite(STDOUT, sprintf("Seeded %d pages into build/demo\n", Page::query()->count()));
}
