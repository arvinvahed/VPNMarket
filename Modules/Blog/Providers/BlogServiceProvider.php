<?php

namespace Modules\Blog\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;

class BlogServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Blog';

    protected string $nameLower = 'blog';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {

        $this->loadMigrationsFrom(module_path($this->name, 'Database/Migrations'));


        $this->registerViews();


        $this->loadRoutesFrom(module_path($this->name, 'Routes/web.php'));


        $this->registerTranslations();
        $this->registerConfig();
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {


        // $this->app->register(EventServiceProvider::class);
    }

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->nameLower);

        $sourcePath = module_path($this->name, 'Resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
        }
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $configPath = module_path($this->name, 'config/config.php');

        if (file_exists($configPath)) {
            $this->publishes([
                $configPath => config_path($this->nameLower . '.php'),
            ], 'config');

            $this->mergeConfigFrom(
                $configPath, $this->nameLower
            );
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->nameLower)) {
                $paths[] = $path . '/modules/' . $this->nameLower;
            }
        }
        return $paths;
    }
}
