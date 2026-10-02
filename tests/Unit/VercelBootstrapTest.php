<?php

namespace Tests\Unit;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class VercelBootstrapTest extends TestCase
{
    public function test_runtime_directories_and_manifests_are_writable_outside_the_deployment(): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $prepare = require $app->bootstrapPath('vercel.php');

        $prepare($app);
        $prepare($app);

        $this->assertSame(sys_get_temp_dir().'/wemx', $app->storagePath());

        foreach (['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
            $this->assertDirectoryIsWritable($app->storagePath($directory));
        }

        foreach ([$app->getCachedConfigPath(), $app->getCachedEventsPath(), $app->getCachedPackagesPath(), $app->getCachedRoutesPath(), $app->getCachedServicesPath()] as $path) {
            $this->assertSame(realpath($app->storagePath('bootstrap/cache')), realpath(dirname($path)));
            $this->assertDirectoryIsWritable(dirname($path));
        }
    }

    public function test_laravel_boots_and_compiles_views_with_temporary_storage(): void
    {
        foreach (['APP_INSTALLED' => 'false', 'APP_ENV' => 'testing'] as $name => $value) {
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv($name.'='.$value);
        }

        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        (require $app->bootstrapPath('vercel.php'))($app);
        $app->make(Kernel::class)->bootstrap();

        $this->assertSame(realpath($app->storagePath('framework/views')), $app['config']->get('view.compiled'));
        $this->assertFileExists($app->getCachedPackagesPath());
        $this->assertFileExists($app->getCachedServicesPath());

        $view = tempnam(sys_get_temp_dir(), 'vercel-view-');
        rename($view, $view.'.blade.php');
        $view .= '.blade.php';
        file_put_contents($view, 'Hello {{ $name }}');

        try {
            $this->assertSame('Hello Vercel', $app['view']->file($view, ['name' => 'Vercel'])->render());
            $this->assertFileExists($app['blade.compiler']->getCompiledPath($view));
        } finally {
            $compiled = $app['blade.compiler']->getCompiledPath($view);

            if (is_file($compiled)) {
                unlink($compiled);
            }

            unlink($view);
        }
    }
}
