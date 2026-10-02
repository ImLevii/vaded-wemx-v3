<?php

namespace Extensions\Modules\OneClickDemo;

use App\Extensions\Foundation\ModuleExtension;

class Module extends ModuleExtension
{
    protected string $id = 'one-click-demo';

    protected string $name = 'One Click Demo';

    protected string $description = 'Installed from the marketplace.';

    protected string $version = '1.0.0';

    protected string $marketplace_id = '9';

    protected array $wemxVersions = ['*'];

    public function elements(): array
    {
        return [];
    }

    public function onInstall(): void
    {
    }

    public function onUninstall(): void
    {
    }

    public function onEnable(): void
    {
    }

    public function onDisable(): void
    {
    }
}