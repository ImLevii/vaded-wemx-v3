<?php

namespace App\Console\Commands\Cronjobs;

use App\Services\IntegratedMarketplace;
use Illuminate\Console\Command;

class CheckMarketplaceUpdatesCommand extends Command
{
    protected $signature = 'cronjobs:check-marketplace-updates';

    protected $description = 'Check the integrated marketplace for updates to installed resources';

    public function handle(IntegratedMarketplace $marketplace): int
    {
        $updates = $marketplace->refreshInstalledUpdates();

        if ($updates === 0) {
            $this->info('Installed marketplace resources are up to date.');

            return self::SUCCESS;
        }

        $this->info($updates === 1
            ? '1 installed marketplace resource has an update ready.'
            : $updates.' installed marketplace resources have an update ready.');

        return self::SUCCESS;
    }
}
