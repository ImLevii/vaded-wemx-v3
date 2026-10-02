<?php

namespace App\Extensions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class ExtensionServiceProvider extends ServiceProvider
{
    protected array $extensionNamespaces = [];

    public function __construct($app)
    {
        parent::__construct($app);
        $this->extensionNamespaces = $this->getExtensionNamespaces();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        foreach ($this->extensionNamespaces as $extensionNamespace) {
            if (class_exists($extensionNamespace)) {
                $extensionClass = new $extensionNamespace;

                if (method_exists($extensionClass, 'providers')) {
                    foreach ($extensionClass->providers() as $provider) {
                        if ($this->app) {
                            $this->app->register($provider);
                        }
                    }
                }
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ($this->extensionNamespaces as $extensionNamespace) {
            if (class_exists($extensionNamespace)) {
                $extensionClass = new $extensionNamespace;

                if ($extensionClass->hasViews()) {
                    $this->loadViewsFrom($extensionClass->getViewsPath(), $extensionClass->getId());
                }

                if ($extensionClass->hasTranslations()) {
                    $this->loadTranslationsFrom($extensionClass->getTranslationsPath(), $extensionClass->getId());
                }

                if ($extensionClass->hasMigrations()) {
                    $this->loadMigrationsFrom($extensionClass->getMigrationsPath());
                }

                if ($extensionClass->hasRoutes()) {
                    $this->loadRoutesFrom($extensionClass->getRoutesPath());
                }

                if ($extensionClass->hasConfig()) {
                    $this->mergeConfigFrom($extensionClass->getConfigPath(), $extensionClass->getId());
                }
            }
        }
    }

    private function getExtensionNamespaces(): array
    {
        if (! Schema::hasTable('extensions')) {
            return [];
        }

        return DB::table('extensions')
            ->where('status', 'enabled')
            ->pluck('namespace')
            ->toArray() ?? [];
    }
}
