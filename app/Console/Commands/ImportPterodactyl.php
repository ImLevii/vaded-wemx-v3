<?php

namespace App\Console\Commands;

use App\Models\PackagePrice;
use App\Models\ServerConnection;
use App\Models\User;
use App\Services\PterodactylImporter;
use App\Services\UserImportBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImportPterodactyl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-pterodactyl
        {--connection= : Existing Pterodactyl server connection ID}
        {--server=* : Import only these Pterodactyl server IDs}
        {--user= : Require every selected server to belong to this existing WemX customer ID}
        {--billing-review : Restore selected services with all billing held until verified}
        {--package-price= : Default WemX package price ID for imported servers}
        {--server-price=* : Override a server price with PTERODACTYL_SERVER_ID:WEMX_PRICE_ID}
        {--match-packages : Match unmapped servers to a unique price by egg, RAM, disk and CPU}
        {--due-date= : First renewal date (YYYY-MM-DD), required for recurring prices}
        {--list : List available connections and package prices without importing}
        {--commit : Save the import; otherwise perform a dry run and roll back}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Pterodactyl server owners and link existing servers as WemX orders';

    /**
     * Execute the console command.
     */
    public function handle(PterodactylImporter $importer, UserImportBackup $backup): int
    {
        if ($this->option('list')) {
            $this->table(['Connection ID', 'Alias'], ServerConnection::query()
                ->where('extension_identifier', 'server-pterodactyl')->get()
                ->map(fn (ServerConnection $connection): array => [$connection->id, $connection->alias])->all());
            $this->table(['Price ID', 'Connection ID', 'Package', 'Egg ID', 'Price', 'Days'], PackagePrice::query()
                ->with('package')->where('is_active', true)
                ->whereHas('package.serverConnection', fn ($query) => $query->where('extension_identifier', 'server-pterodactyl'))
                ->get()->map(fn (PackagePrice $price): array => [
                    $price->id, $price->package->connection_id, $price->package->name,
                    $price->package->data('egg_id'), $price->price, $price->period_in_days,
                ])->all());

            return self::SUCCESS;
        }

        try {
            $serverIds = $this->option('server');
            foreach ($serverIds as $serverId) {
                if (! preg_match('/^[1-9][0-9]*$/', $serverId)) {
                    throw new RuntimeException('--server must be a positive Pterodactyl server ID.');
                }
            }
            $userId = $this->option('user');
            if ($userId !== null && (! preg_match('/^[1-9][0-9]*$/', $userId) || ! User::query()->whereKey($userId)->exists())) {
                throw new RuntimeException('--user must be an existing WemX customer ID.');
            }
            $connections = ServerConnection::query()->where('extension_identifier', 'server-pterodactyl')
                ->when($this->option('connection') !== null, fn ($query) => $query->whereKey($this->option('connection')))
                ->get();
            if ($connections->count() !== 1) {
                throw new RuntimeException('Select a Pterodactyl connection with --connection=ID. Use --list to find IDs.');
            }

            $serverPrices = [];
            foreach ($this->option('server-price') as $mapping) {
                if (! preg_match('/^([1-9][0-9]*):([1-9][0-9]*)$/', $mapping, $matches)) {
                    throw new RuntimeException('--server-price must use PTERODACTYL_SERVER_ID:WEMX_PRICE_ID.');
                }
                if (isset($serverPrices[(int) $matches[1]])) {
                    throw new RuntimeException('Each server can have only one --server-price mapping.');
                }
                $serverPrices[(int) $matches[1]] = (int) $matches[2];
            }

            $priceId = $this->option('package-price');
            if ($priceId !== null && ! preg_match('/^[1-9][0-9]*$/', $priceId)) {
                throw new RuntimeException('--package-price must be a positive WemX price ID.');
            }
            if ($this->option('commit')) {
                $directory = storage_path('app/private/legacy-imports');
                File::ensureDirectoryExists($directory);
                $backupPath = $backup->create($directory.'/pterodactyl-before-'.now()->format('Ymd-His').'-'.Str::uuid(), [
                    'users', 'addresses', 'orders', 'server_accounts', 'order_prices', 'payments', 'subscriptions', 'order_subscriptions', 'balance_transactions',
                ]);
                $this->info('Pre-import snapshot saved: '.$backupPath);
            }
            $report = $importer->import(
                $connections->first(), $priceId === null ? null : (int) $priceId,
                $serverPrices, $this->option('due-date'), (bool) $this->option('commit'), (bool) $this->option('match-packages'),
                array_map('intval', $serverIds), $userId === null ? null : (int) $userId,
                (bool) $this->option('billing-review'),
            );
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Panel server ID', 'Server', 'Owner email', 'Price ID', 'Due date', 'Result'], $report['servers']);
        $this->info(sprintf('Users created: %d; existing users matched: %d; orders created: %d; existing orders skipped: %d.',
            $report['users_created'], $report['users_matched'], $report['orders_created'], $report['orders_skipped']));
        $this->info($this->option('commit') ? 'Import committed.' : 'Dry run passed; all changes rolled back. Add --commit to save.');
        if ($report['users_created'] > 0) {
            $this->info('New WemX users must use Forgot Password to set a password. Panel passwords are unchanged.');
        }

        return self::SUCCESS;
    }
}
