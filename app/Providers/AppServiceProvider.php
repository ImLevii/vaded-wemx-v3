<?php

namespace App\Providers;

use App\Extensions\ExtensionServiceProvider;
use App\Install\InstallServiceProvider;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // If app is not installed, add the install provider
        if (! config('app.installed', false)) {
            $this->app->register(InstallServiceProvider::class);
        } else {
            // If app is installed, add the extension provider
            $this->app->register(ExtensionServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prohibits: db:wipe, migrate:fresh, migrate:refresh, and migrate:reset
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Enable automatic eager loading of relationships
        Model::automaticallyEagerLoadRelationships();

        // force https
        if (config('app.force_https', false)) {
            \URL::forceScheme('https');
        }

        // set the default locale and currency
        Number::useLocale('en');
        Number::useCurrency('USD');

        // load invoices views
        $this->loadViewsFrom(resource_path('invoices'), 'invoices');

        // register custom client theme
        $this->registerClientTheme();

        // register custom admin theme
        $this->registerAdminTheme();

        // register custom email theme
        $this->registerEmailTheme();

        // define @settings('key') directive
        Blade::directive('settings', function ($key, $default = null) {
            return "<?php echo settings($key, $default); ?>";
        });

        // define @has('permission') directive
        $this->registerPermissionsDirective();
    }

    /**
     * Register the client area theme
     */
    private function registerClientTheme(): void
    {
        // check if the theme directory exists
        if (! is_dir(resource_path('client_area/'.config('app.theme', 'default')))) {
            throw new \RuntimeException('Client theme "'.config('app.theme', 'default').'" not found');
        }

        $this->loadViewsFrom(resource_path('client_area/'.config('app.theme', 'default')), 'theme');

        View::composer('theme::layouts.header', function (ViewInstance $view): void {
            $isAdmin = auth()->user()?->isAdmin() ?? false;

            $navigationCategories = Category::query()
                ->when($isAdmin, fn (Builder $query): Builder => $query->whereNotIn('status', ['disabled', 'unlisted']))
                ->when(! $isAdmin, fn (Builder $query): Builder => $query->where('status', 'active'))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'slug', 'icon', 'description']);

            $navigationGroups = collect();
            $remainingCategories = $navigationCategories;

            foreach (config('client-navigation.groups', []) as $key => $group) {
                $categories = $remainingCategories->whereIn('slug', $group['slugs']);

                if ($categories->isNotEmpty()) {
                    $navigationGroups->put($key, [
                        'label' => $group['label'],
                        'icon' => $group['icon'],
                        'categories' => $categories,
                    ]);
                }

                $remainingCategories = $remainingCategories->whereNotIn('slug', $group['slugs']);
            }

            if ($remainingCategories->isNotEmpty() || $navigationGroups->isEmpty()) {
                $navigationGroups->put('hosting', [
                    'label' => $navigationGroups->isEmpty() ? 'Hosting' : 'More hosting',
                    'icon' => 'M4 4h16v6H4z M4 14h16v6H4z M7 7h2 M7 17h2 M12 7h5 M12 17h5',
                    'categories' => $remainingCategories,
                ]);
            }

            $view->with('navigationGroups', $navigationGroups);
        });

        // php artisan vendor:publish --tag=client - Publishes the client theme assets
        $this->publishes([
            resource_path('client_area/'.config('app.theme', 'default').'/assets') => public_path('assets/clientarea/'.config('app.theme', 'default')),
        ], 'client');
    }

    /**
     * Register the admin area theme
     */
    private function registerAdminTheme(): void
    {
        // check if the theme directory exists
        if (! is_dir(resource_path('admin_area/'.config('app.admin_theme', 'default')))) {
            throw new \RuntimeException('Admin theme "'.config('app.admin_theme', 'default').'" not found');
        }

        $this->loadViewsFrom(resource_path('admin_area/'.config('app.admin_theme', 'default')), 'admin');

        // php artisan vendor:publish --tag=admin - Publishes the admin theme assets
        $this->publishes([
            resource_path('admin_area/'.config('app.admin_theme', 'default').'/assets') => public_path('assets/adminarea/'.config('app.admin_theme', 'default')),
        ], 'admin');
    }

    /**
     * Register the email theme
     */
    private function registerEmailTheme(): void
    {
        // check if the theme directory exists
        if (! is_dir(resource_path('email_templates/'.config('app.email_theme', 'default')))) {
            throw new \RuntimeException('Email theme "'.config('app.email_theme', 'default').'" not found');
        }

        $this->loadViewsFrom(resource_path('email_templates/'.config('app.email_theme', 'default')), 'email');
    }

    /**
     * Register the permissions directive
     */
    private function registerPermissionsDirective(): void
    {
        Blade::directive('perm', function ($permission) {
            return "<?php if(auth()->user()->hasPermission($permission)): ?>";
        });

        Blade::directive('endperm', function () {
            return '<?php endif; ?>';
        });
    }
}
