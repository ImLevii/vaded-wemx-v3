<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PackagePrice;
use App\Models\ServerAccount;
use App\Models\ServerConnection;
use App\Models\User;
use App\Support\LicensePlanLimits;
use Carbon\CarbonImmutable;
use Extensions\Servers\Pterodactyl\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PterodactylImporter
{
    /**
     * @param  array<int, int>  $serverPrices
     * @param  list<int>  $serverIds
     * @return array{users_created: int, users_matched: int, orders_created: int, orders_skipped: int, servers: list<array<int, mixed>>}
     */
    public function import(ServerConnection $connection, ?int $defaultPriceId = null, array $serverPrices = [], ?string $dueDate = null, bool $commit = false, bool $matchPackages = false, array $serverIds = [], ?int $userId = null, bool $billingReview = false): array
    {
        if ($connection->extension_identifier !== 'server-pterodactyl') {
            throw new RuntimeException('Select a Pterodactyl server connection.');
        }
        $credentials = $connection->config ?? [];
        if (empty($credentials['hostname']) || ! str_starts_with($credentials['api_key'] ?? '', 'ptla_')) {
            throw new RuntimeException('Configure the connection with a panel hostname and a Pterodactyl Application API key (ptla_).');
        }
        Validator::make(['server_ids' => $serverIds], ['server_ids' => ['array'], 'server_ids.*' => ['integer', 'min:1', 'distinct']])->validate();
        $expectedUser = $userId === null ? null : User::query()->findOrFail($userId);
        if ($billingReview && ($serverIds === [] || $expectedUser === null || $dueDate !== null)) {
            throw new RuntimeException('--billing-review requires explicit --server and --user selections and cannot be combined with --due-date.');
        }
        $renewalDate = null;
        if ($dueDate !== null) {
            Validator::make(['due_date' => $dueDate], ['due_date' => ['required', 'date_format:Y-m-d', 'after:today']])->validate();
            $renewalDate = CarbonImmutable::createFromFormat('!Y-m-d', $dueDate);
        }

        $lock = Cache::lock('pterodactyl-import:'.$connection->id, 3600);
        if (! $lock->get()) {
            throw new RuntimeException('An import for this connection is already running.');
        }
        try {
            $servers = $this->fetchServers($credentials, $serverIds);
            foreach ($servers as $server) {
                $owner = $server['relationships']['user']['attributes'];
                if ($expectedUser !== null && (mb_strtolower(trim($expectedUser->email)) !== mb_strtolower(trim($owner['email']))
                    || (data_get($expectedUser->data, '_legacy_pterodactyl.id') !== null && (int) data_get($expectedUser->data, '_legacy_pterodactyl.id') !== (int) $owner['id']))) {
                    throw new RuntimeException("Server {$server['id']} does not belong to the selected WemX customer.");
                }
            }
            $unknownMappings = array_diff(array_keys($serverPrices), array_column($servers, 'id'));
            if ($unknownMappings !== []) {
                throw new RuntimeException('Price mappings reference servers absent from this panel: '.implode(', ', $unknownMappings));
            }
            $prices = PackagePrice::query()->with('package')
                ->whereHas('package', fn ($query) => $query->where('connection_id', $connection->id))
                ->when(! $matchPackages, fn ($query) => $query->whereIn('id', array_filter([
                    $defaultPriceId, ...array_values($serverPrices),
                ])))->get()->keyBy('id');
            $report = ['users_created' => 0, 'users_matched' => 0, 'orders_created' => 0, 'orders_skipped' => 0, 'servers' => []];
            DB::beginTransaction();
            try {
                ServerConnection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
                if ($expectedUser !== null) {
                    $expectedUser = User::query()->whereKey($expectedUser->id)->lockForUpdate()->firstOrFail();
                }
                Model::withoutEvents(function () use ($connection, $servers, $prices, $defaultPriceId, $serverPrices, $renewalDate, $matchPackages, $billingReview, $expectedUser, &$report): void {
                    $users = [];
                    foreach ($servers as $server) {
                        $owner = $server['relationships']['user']['attributes'];
                        $email = mb_strtolower(trim($owner['email']));
                        if ($expectedUser !== null && (mb_strtolower(trim($expectedUser->email)) !== $email
                            || (data_get($expectedUser->data, '_legacy_pterodactyl.id') !== null && (int) data_get($expectedUser->data, '_legacy_pterodactyl.id') !== (int) $owner['id']))) {
                            throw new RuntimeException("Server {$server['id']} does not belong to the selected WemX customer.");
                        }
                        $orders = Order::query()->where('external_id', (string) $server['id'])
                            ->whereHas('package', fn ($query) => $query->where('connection_id', $connection->id))
                            ->with('user')->get();
                        if ($orders->count() > 1) {
                            throw new RuntimeException("Multiple orders link to Pterodactyl server {$server['id']}.");
                        }
                        if ($order = $orders->first()) {
                            if (mb_strtolower($order->user->email) !== $email || $order->status === 'terminated'
                                || ($expectedUser !== null && (int) $order->user_id !== (int) $expectedUser->id)) {
                                throw new RuntimeException("Existing order for server {$server['id']} has conflicting ownership or is terminated. Resolve it before importing.");
                            }
                            $this->linkAccount($order, $owner);
                            $report['orders_skipped']++;
                            $report['servers'][] = [$server['id'], $server['name'], $email, $order->package_price_id, $order->due_date?->toDateString(), 'Existing'];

                            continue;
                        }
                        $priceId = $serverPrices[$server['id']] ?? $defaultPriceId;
                        $price = $priceId === null && $matchPackages ? $this->matchPrice($server, $prices) : $prices->get($priceId);
                        if (! $price || ! $price->is_active || (int) $price->package->connection_id !== (int) $connection->id) {
                            throw new RuntimeException("Server {$server['id']} needs an active price from this connection. Use --package-price=ID or --server-price={$server['id']}:PRICE_ID; use --list to find prices.");
                        }
                        if ((int) $price->package->data('egg_id') !== (int) $server['egg']) {
                            throw new RuntimeException("The selected package egg does not match server {$server['id']}. Choose a matching --server-price={$server['id']}:PRICE_ID.");
                        }
                        if ($price->isRecurring() && $renewalDate === null && ! $billingReview) {
                            throw new RuntimeException('Recurring orders require --due-date=YYYY-MM-DD. Pterodactyl does not provide billing dates.');
                        }
                        LicensePlanLimits::assertCanCreateOrders(1);
                        if (! isset($users[$owner['id']])) {
                            if ($expectedUser !== null) {
                                $users[$owner['id']] = $expectedUser;
                                $report['users_matched']++;
                            } else {
                                $users[$owner['id']] = $this->importUser($owner, $report);
                            }
                        }
                        $orderData = $server;
                        if ($billingReview) {
                            $orderData['_import'] = [
                                'billing_review_required' => true, 'catalog_price_id' => $price->id,
                                'imported_at' => now()->toIso8601String(),
                                'legacy_wemx_order_id' => preg_match('/^wmx-([1-9][0-9]*)$/D', $server['external_id'] ?? '', $matches) ? (int) $matches[1] : null,
                            ];
                        }
                        $order = Order::query()->create([
                            'user_id' => $users[$owner['id']]->id,
                            'package_id' => $price->package_id,
                            'package_price_id' => $price->id,
                            'external_id' => (string) $server['id'],
                            'status' => ($server['suspended'] ?? false) || ($server['status'] ?? null) === 'suspended' ? 'suspended' : 'active',
                            'cycle_price' => $billingReview ? 0 : $price->getDailyPrice(),
                            'setup_fee' => 0,
                            'upgrade_fee' => $billingReview ? 0 : $price->upgrade_fee,
                            'period_in_days' => $price->period_in_days,
                            'due_date' => $price->isRecurring() ? $renewalDate : null,
                            'last_renewed_at' => now(),
                            'auto_balance_renew' => false,
                            'data' => $orderData,
                        ]);
                        $this->linkAccount($order, $owner);
                        $report['orders_created']++;
                        $report['servers'][] = [$server['id'], $server['name'], $email, $price->id, $order->due_date?->toDateString(), $billingReview ? 'Billing review' : 'Import'];
                    }
                });
                if ($commit) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            } catch (Throwable $exception) {
                DB::rollBack();

                throw $exception;
            }

            return $report;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $server
     * @param  Collection<int, PackagePrice>  $prices
     */
    private function matchPrice(array $server, Collection $prices): PackagePrice
    {
        $matches = $prices->filter(function (PackagePrice $price) use ($server): bool {
            $config = $price->package->data ?? [];
            if (! $price->is_active || (int) ($config['egg_id'] ?? 0) !== (int) $server['egg']) {
                return false;
            }
            foreach (['memory_limit' => 'memory', 'disk_limit' => 'disk', 'cpu_limit' => 'cpu'] as $packageKey => $serverKey) {
                if (! isset($config[$packageKey], $server['limits'][$serverKey]) || ! is_numeric($config[$packageKey])) {
                    return false;
                }
                $expected = $packageKey === 'cpu_limit' ? (float) $config[$packageKey] : ceil((float) $config[$packageKey] * 1024);
                if ($expected !== (float) $server['limits'][$serverKey]) {
                    return false;
                }
            }

            return true;
        });
        if ($matches->count() !== 1) {
            throw new RuntimeException("Server {$server['id']} ({$server['name']}) has {$matches->count()} matching prices by egg, RAM, disk and CPU. Specify --server-price={$server['id']}:PRICE_ID; use --list to find prices.");
        }

        return $matches->first();
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  list<int>  $serverIds
     * @return list<array<string, mixed>>
     */
    private function fetchServers(array $credentials, array $serverIds = []): array
    {
        $servers = [];
        $page = 1;
        do {
            try {
                if ($serverIds === []) {
                    $response = Server::makeRequest($credentials, '/api/application/servers', 'get', [
                        'include' => 'user,allocations', 'per_page' => 100, 'page' => $page,
                    ])->json();
                } else {
                    $records = [];
                    foreach ($serverIds as $serverId) {
                        $record = Server::makeRequest($credentials, '/api/application/servers/'.$serverId, 'get', ['include' => 'user,allocations'])->json();
                        if ((int) ($record['attributes']['id'] ?? 0) !== $serverId) {
                            throw new RuntimeException("The panel returned a different server for requested ID {$serverId}.");
                        }
                        $records[] = $record;
                    }
                    $response = ['data' => $records, 'meta' => ['pagination' => ['total_pages' => 1]]];
                }
            } catch (Throwable $exception) {
                throw new RuntimeException("Unable to read Pterodactyl servers on page {$page}. Check connectivity and Application API read permissions for servers, users and allocations.", previous: $exception);
            }
            Validator::make($response ?? [], [
                'data' => ['present', 'array'],
                'data.*.attributes' => ['required', 'array'],
                'data.*.attributes.id' => ['required', 'integer', 'min:1'],
                'data.*.attributes.uuid' => ['required', 'uuid'],
                'data.*.attributes.name' => ['required', 'string'],
                'data.*.attributes.user' => ['required', 'integer', 'min:1'],
                'data.*.attributes.egg' => ['required', 'integer', 'min:1'],
                'data.*.attributes.relationships.user.attributes.id' => ['required', 'integer', 'min:1'],
                'data.*.attributes.relationships.user.attributes.email' => ['required', 'email', 'max:255'],
                'data.*.attributes.relationships.user.attributes.username' => ['required', 'string', 'max:255'],
                'meta.pagination.total_pages' => ['required', 'integer', 'min:1'],
            ])->validate();
            foreach ($response['data'] as $record) {
                $server = $record['attributes'];
                $id = (int) $server['id'];
                if (isset($servers[$id]) || (int) $server['user'] !== (int) $server['relationships']['user']['attributes']['id']) {
                    throw new RuntimeException("Pterodactyl returned a duplicate server or mismatched owner for server {$id}.");
                }
                if (! in_array($server['status'] ?? null, [null, 'suspended'], true)) {
                    throw new RuntimeException("Server {$id} is installing, restoring or in an unsupported state. Retry after it is ready.");
                }
                $servers[$id] = $server;
            }
            $page++;
        } while ($page <= (int) $response['meta']['pagination']['total_pages']);

        return array_values($servers);
    }

    /**
     * @param  array<string, mixed>  $owner
     * @param  array{users_created: int, users_matched: int, orders_created: int, orders_skipped: int, servers: list<array<int, mixed>>}  $report
     */
    private function importUser(array $owner, array &$report): User
    {
        $email = mb_strtolower(trim($owner['email']));
        $matches = User::query()->whereRaw('LOWER(email) = ?', [$email])->get();
        if ($matches->count() > 1) {
            throw new RuntimeException("Multiple WemX users match Pterodactyl user {$owner['id']} by email.");
        }
        if ($user = $matches->first()) {
            $report['users_matched']++;

            return $user;
        }
        $username = $owner['username'];
        $suffix = 0;
        while (User::query()->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])->exists()) {
            $suffix++;
            $username = mb_substr($owner['username'], 0, 220).'-ptero-'.$owner['id'].'-'.$suffix;
        }
        $user = User::query()->create([
            'username' => $username, 'email' => $email,
            'first_name' => $owner['first_name'] ?? null, 'last_name' => $owner['last_name'] ?? null,
            'status' => 'active', 'password' => Hash::make(Str::random(64)),
        ]);
        $user->createEmptyAddress();
        $report['users_created']++;

        return $user;
    }

    /** @param array<string, mixed> $owner */
    private function linkAccount(Order $order, array $owner): void
    {
        $accounts = ServerAccount::query()->where('order_id', $order->id)->get();
        if ($accounts->count() > 1) {
            throw new RuntimeException("Order {$order->id} has multiple external accounts. Resolve them before importing.");
        }
        if ($account = $accounts->first()) {
            if ($account->server !== 'server-pterodactyl' || (int) $account->external_id !== (int) $owner['id'] || (int) $account->user_id !== (int) $order->user_id) {
                throw new RuntimeException("Order {$order->id} has a conflicting external account. Resolve it before importing.");
            }

            return;
        }
        ServerAccount::query()->create([
            'user_id' => $order->user_id, 'order_id' => $order->id, 'server' => 'server-pterodactyl',
            'external_id' => (string) $owner['id'], 'username' => $owner['username'],
            'password' => 'unknown',
            'data' => Arr::only($owner, ['id', 'uuid', 'username', 'email', 'first_name', 'last_name', 'language', 'created_at', 'updated_at']),
        ]);
    }
}
