<?php

namespace Extensions\Servers\Pterodactyl;

use App\Extensions\Foundation\ServerExtension;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\ServerAccount;
use App\Models\ServerConnection;
use App\Models\User;
use App\Services\CustomerServerCredentials;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class Server extends ServerExtension
{
    /**
     * Define the extension identifier. This identifier should be unique.
     * For example, if the extension name is "Example Module", the extension identifier should be "module-example".
     */
    protected string $id = 'server-pterodactyl';

    /**
     * Define the extension display name
     */
    protected string $name = 'Pterodactyl Server';

    /**
     * Define the extension description.
     */
    protected string $description = 'Pterodactyl server extension';

    /**
     * Define the extension type. For example, if the extension is a module, the extension type should be "Module".
     */
    protected string $type = 'Server';

    /**
     * Define the extension version.
     */
    protected string $version = '1.0.0';

    /**
     * Define the WemX versions that the extension is compatible with.
     * Use * to define that the extension is compatible with all versions.
     */
    protected array $wemxVersions = ['v3-alpha'];

    /**
     * Define the authors of the extension.
     */
    protected array $authors = [
        [
            'name' => 'GIGABAIT',
            'email' => 'xgigabaitx@gmail.com',
        ],
    ];

    /**
     * Relative path to the extension config file.
     */
    protected string $config = 'Config/pterodactyl.php';

    /**
     * Relative path to the language files.
     */
    protected string $translations = 'Lang';

    /**
     * List of providers to be registered.
     */
    public function providers(): array
    {
        return [];
    }

    public function elements(): array
    {
        return [];
    }

    public function setConfig(): array
    {
        // Check if the URL ends with a slash
        $doesNotEndWithSlash = function ($attribute, $value, $fail) {
            if (preg_match('/\/$/', $value)) {
                return $fail('Hostname URL must not end with a slash "/". It should be like https://panel.example.com');
            }
        };

        return [
            [
                'key' => 'hostname',
                'name' => 'Hostname',
                'description' => 'Hostname of your Pterodactyl panel i.e https://panel.example.com',
                'type' => 'url',
                'default_value' => 'https://panel.example.com',
                'rules' => ['required', 'active_url', $doesNotEndWithSlash], // laravel validation rules
            ],
            [
                'key' => 'api_key',
                'name' => 'Application API Key',
                'description' => 'API Key of your Pterodactyl panel',
                'type' => 'password',
                'rules' => ['required', 'starts_with:ptla_'], // laravel validation rules
            ],
            [
                'key' => 'debug_mode',
                'name' => 'Debug Mode',
                'description' => 'When enabled, API errors will be dumped on the screen. Useful for debugging. Do not enable on production.',
                'type' => 'select',
                'options' => [
                    '0' => 'Disabled',
                    '1' => 'Enabled',
                ],
                'default_value' => '0',
                'rules' => ['required', 'in:0,1'], // laravel validation rules
            ],
        ];
    }

    public function setPackageConfig(Package $package, ServerConnection $connection): array
    {
        $config = [
            [
                'key' => 'location_id',
                'name' => 'Location ID',
                'col' => 'col-12',
                'description' => 'The numeric location ID from Pterodactyl Admin > Locations. Make this option configurable to allow users to select the location.',
                'type' => 'text',
                'rules' => ['required', 'integer', 'min:1'],
                'is_configurable' => true,
            ],
            [
                'key' => 'nest_id',
                'name' => 'Nest ID',
                'col' => 'col-12',
                'description' => 'Nest ID of the server you want to use for this package. You can find the nest ID by going to the nest page and looking at the URL. It will be the number at the end of the URL.',
                'type' => 'text',
                'rules' => ['required', 'numeric'],
                'is_configurable' => false,
            ],
            [
                'key' => 'egg_id',
                'name' => 'Egg ID',
                'col' => 'col-12',
                'description' => 'Egg ID of the server you want to use for this package. You can find the egg ID by going to the egg page and looking at the URL. It will be the number at the end of the URL.',
                'type' => 'text',
                'rules' => ['required', 'numeric'],
                'is_configurable' => false,
            ],
        ];

        try {
            // if egg id is not set return the default config
            if (! $package->data('egg_id')) {
                return $config;
            }

            $nestId = (int) $package->data('nest_id', 1);
            $eggId = (int) $package->data('egg_id', 4);

            // Unique per connection + nest + egg
            $connectionId = $connection->id ?? ($connection->getKey() ?? 'default');
            $cacheKey = "pterodactyl:egg:{$connectionId}:nest:{$nestId}:egg:{$eggId}";

            // Cache the *attributes* for 1 hour
            $eggAttributes = Cache::remember(
                $cacheKey,
                now()->addHour(),
                function () use ($connection, $nestId, $eggId) {
                    $egg = Server::makeRequest(
                        $connection->config,
                        "/api/application/nests/{$nestId}/eggs/{$eggId}",
                        'get',
                        ['include' => 'variables']
                    );

                    if (! isset($egg['attributes'])) {
                        throw new RuntimeException('Invalid egg response from panel.');
                    }

                    return $egg['attributes'];
                }
            );

            $config = array_merge($config, [
                [
                    'col' => 'col-4',
                    'key' => 'database_limit',
                    'name' => 'Database Limit',
                    'description' => 'The total number of databases a user is allowed to create for this server on Pterodactyl Panel.',
                    'type' => 'number',
                    'min' => 0,
                    'rules' => ['required', 'numeric', 'min:0', 'max:50'],
                    'is_configurable' => true,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'allocation_limit',
                    'name' => 'Allocation Limit',
                    'description' => 'The total number of allocations a user is allowed to create for this server on Pterodactyl Panel.',
                    'type' => 'number',
                    'min' => 0,
                    'rules' => ['required', 'numeric', 'min:0', 'max:50'],
                    'is_configurable' => true,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'backup_limit',
                    'name' => 'Backup Limit',
                    'description' => 'The total number of backups a user is allowed to create for this server on Pterodactyl Panel.',
                    'type' => 'number',
                    'min' => 0,
                    'rules' => ['required', 'numeric', 'min:0', 'max:100'],
                    'is_configurable' => true,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'cpu_limit',
                    'name' => 'CPU Limit in %',
                    'description' => 'If you do not want to limit CPU usage, set the value to 0. To use a single thread set it to 100%, for 4 threads set to 400% etc',
                    'type' => 'number',
                    'default_value' => 100,
                    'min' => 0,
                    'rules' => ['required', 'numeric', 'min:0', 'max:10000'],
                    'is_configurable' => true,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'memory_limit',
                    'name' => 'Memory Limit in GB',
                    'description' => 'The maximum amount of memory allowed for this container. Setting this to 0 will allow unlimited memory in a container.',
                    'type' => 'number',
                    'min' => 0,
                    'rules' => ['required', 'numeric', 'min:0', 'max:64'],
                    'is_configurable' => true,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'disk_limit',
                    'name' => 'Disk Limit in GB',
                    'description' => 'The maximum amount of memory allowed for this container. Setting this to 0 will allow unlimited memory in a container.',
                    'type' => 'number',
                    'min' => 0,
                    'rules' => ['required', 'numeric', 'min:0', 'max:1024'],
                    'is_configurable' => true,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'cpu_pinning',
                    'name' => 'CPU Pinning (optional)',
                    'description' => 'Advanced: Enter the specific CPU threads that this process can run on, or leave blank to allow all threads. This can be a single number, or a comma separated list. Example: 0, 0-1,3, or 0,1,3,4.',
                    'type' => 'text',
                    'rules' => ['nullable'],
                    'is_configurable' => false,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'swap_limit',
                    'name' => 'Swap Limit in GB',
                    'description' => 'The maximum amount of swap allowed for this container. Setting this to 0 will disable swap. Setting this to -1 will allow unlimited swap.',
                    'type' => 'number',
                    'default_value' => 0,
                    'rules' => ['required', 'numeric', 'min:-1', 'max:128'],
                    'is_configurable' => false,
                ],
                [
                    'col' => 'col-4',
                    'key' => 'block_io_weight',
                    'name' => 'Block IO Weight',
                    'description' => 'The relative weight of IO for this container. This accepts a value between 10 and 1000. The default value is 500.',
                    'type' => 'number',
                    'default_value' => 500,
                    'rules' => ['required', 'numeric', 'min:10', 'max:1000'],
                    'is_configurable' => false,
                ],
            ]);

            $config[] = [
                'col' => 'col-12',
                'key' => 'docker_image',
                'name' => 'Docker Image',
                'description' => 'Docker image to use for this server',
                'type' => 'text',
                'default_value' => data_get($eggAttributes, 'docker_image'),
                'rules' => ['required'],
            ];

            $config[] = [
                'col' => 'col-12',
                'key' => 'startup',
                'name' => 'Startup Command',
                'description' => 'Startup command for this server',
                'type' => 'textarea',
                'default_value' => data_get($eggAttributes, 'startup'),
                'rules' => ['required'],
                'is_configurable' => false,
            ];

            foreach (data_get($eggAttributes, 'relationships.variables.data', []) as $variable) {
                $variable = $variable['attributes'];

                // check if rules is a string, if so convert it to array
                if (is_string($variable['rules'])) {
                    $variable['rules'] = explode('|', $variable['rules']);
                }

                $config[] = [
                    'col' => 'col-4',
                    'key' => "environment.{$variable['env_variable']}",
                    'name' => $variable['name'],
                    'description' => $variable['description'],
                    'type' => 'text',
                    'default_value' => $variable['default_value'] ?? '',
                    'rules' => $variable['rules'],
                    'is_configurable' => true,
                ];
            }
        } catch (\Throwable $e) {
            // if we reach here, the egg id is invalid or the egg does not exist
            // return the default config
            return $config;
        }

        return $config;
    }

    /**
     * This function is called right before the user makes the payment
     * We can use it to check if there are allocations available
     *
     * @throw Exception
     */
    public static function eventAddToCart(Package $package, $configOptions = [])
    {
        // get location id from the package data
        $locationId = $configOptions['location_id'] ?? $package->data('location_id', null);

        if (! $locationId) {
            throw new Exception('Location ID has not been configured for this package');
        }

        Server::findViableNode(
            connection: $package->serverConnection,
            allowedLocations: [$locationId],
            diskLimit: (int) ceil((float) ($configOptions['disk_limit'] ?? $package->data('disk_limit', 0)) * 1024),
            memoryLimit: (int) ceil((float) ($configOptions['memory_limit'] ?? $package->data('memory_limit', 0)) * 1024),
            cpuLimit: $configOptions['cpu_limit'] ?? $package->data('cpu_limit', 0),
        );
    }

    /**
     * Test API connection
     */
    public static function testConnection(array $credentials): string
    {
        $response = Server::makeRequest($credentials, '/api/application/users', 'get', ['per_page' => 1]);
        if (! is_array($response->json('data'))) {
            throw new RuntimeException('Check the panel URL: the Application API did not return a user list.');
        }

        return 'Connected to the Pterodactyl Application API.';
    }

    public static function eventCheckout(Package $package, User $user): void
    {
        if (app(CustomerServerCredentials::class)->password($user) === null) {
            throw ValidationException::withMessages([
                'cart_id' => 'Log out and log in to your customer account before ordering a game server so you can use the same login on the game panel.',
            ]);
        }
    }

    /**
     * Make API request to Pterodactyl API
     *
     * @param  list<int>  $allowedFailureStatuses
     */
    public static function makeRequest(array $credentials, string $endpoint, string $method = 'get', array $data = [], array $allowedFailureStatuses = []): Response
    {
        $method = strtolower($method);

        $apiKey = $credentials['api_key'] ?? '';
        $hostname = rtrim($credentials['hostname'] ?? '', '/');

        if (! str_starts_with($apiKey, 'ptla_')) {
            throw ValidationException::withMessages(['api_key' => 'Use a Pterodactyl Application API key starting with ptla_.']);
        }

        if (! in_array($method, ['get', 'post', 'put', 'delete', 'patch'])) {
            throw new Exception('Invalid method');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Accept' => 'Application/vnd.Pterodactyl.v1+json',
            'Content-Type' => 'application/json',
        ])->connectTimeout(5)->timeout(20)->$method($hostname.'/'.ltrim($endpoint, '/'), $data);

        if ($response->failed() && ! in_array($response->status(), $allowedFailureStatuses, true)) {
            $response->throw();
        }

        return $response;
    }

    /**
     * Changes the password of the Pterodactyl user associated with the order.
     */
    public function changePassword(Order $order, string $newPassword): void
    {
        $connection = $order->package->serverConnection;
        $server = $order->data ?: Server::makeRequest($connection->config, '/api/application/servers/'.$this->provisionedServerId($order))->json('attributes');
        $pterodactylUser = $this->ownedPanelUser($order, $connection, (int) ($server['user'] ?? 0));
        Server::makeRequest($connection->config, "/api/application/users/{$pterodactylUser['id']}", 'patch', [
            'email' => $pterodactylUser['email'],
            'username' => $pterodactylUser['username'],
            'first_name' => $pterodactylUser['first_name'],
            'last_name' => $pterodactylUser['last_name'],
            'password' => $newPassword,
        ]);

        $this->storePteroUserLocally($order, array_merge($pterodactylUser, ['password' => $newPassword]));
    }

    /**
     * This function is responsible for creating an instance of the
     * service. This can be anything such as a server, vps or any other instance.
     */
    public function create(Order $order, ServerConnection $connection): void
    {
        if ($order->external_id !== null) {
            $this->provisionedServerId($order);

            return;
        }
        $existing = Server::makeRequest($connection->config, '/api/application/servers/external/wemx_'.$order->id, 'get', [], [404]);
        if ($existing->successful()) {
            $server = $existing->json('attributes');
            if (! is_array($server) || empty($server['id']) || empty($server['user'])) {
                throw new RuntimeException('The panel returned an invalid server during provisioning recovery.');
            }
            $owner = $this->ownedPanelUser($order, $connection, (int) $server['user']);
            $this->storePteroUserLocally($order, $owner);
            $order->update(['external_id' => $server['id'], 'data' => $server]);

            return;
        }
        $package = $order->package;

        $locationId = $order->option('location_id');

        // specify limits and convert them to MB
        $diskLimit = $this->megabytes($order->option('disk_limit', 0));
        $memoryLimit = $this->megabytes($order->option('memory_limit', 0));
        $swapLimit = $this->megabytes($order->option('swap_limit', 0));
        $cpuLimit = $order->option('cpu_limit', 0);

        $node = Server::findViableNode(
            $connection,
            allowedLocations: [$locationId],
            diskLimit: $diskLimit,
            memoryLimit: $memoryLimit,
            cpuLimit: $cpuLimit
        );
        $pteroUserId = $this->getOrCreatePteroUser($order, $connection);

        // merge environment variables from package and order
        $environment = array_merge(
            $order->package->data('environment', []),
            $order->option('environment', [])
        );

        // prepare the server data
        $serverData = [
            'external_id' => "wemx_{$order->id}",
            'name' => $package->name,
            'user' => $pteroUserId,
            'egg' => $package->data('egg_id'),
            'startup' => $package->data('startup'),
            'docker_image' => $package->data('docker_image'),
            'environment' => $environment,
            'limits' => [
                'memory' => $memoryLimit,
                'swap' => $swapLimit,
                'disk' => $diskLimit,
                'io' => $order->option('block_io_weight', 500),
                'cpu' => $cpuLimit,
                'threads' => $order->option('cpu_pinning'),
            ],
            'feature_limits' => [
                'databases' => $order->option('database_limit', 0),
                'allocations' => $order->option('allocation_limit', 0),
                'backups' => $order->option('backup_limit', 0),
            ],
            'allocation' => [
                'default' => $node['allocation_id'],
            ],
            'start_on_completion' => true,
            'skip_scripts' => false,
            'oom_disabled' => false,
            'swap_disabled' => false,
        ];

        // Create the server on Pterodactyl panel
        $createServerResponse = Server::makeRequest($connection->config, '/api/application/servers', 'post', $serverData);

        // check if the server was created successfully
        if (! isset($createServerResponse['attributes'])) {
            throw new Exception('Failed to create server on Pterodactyl panel');
        }

        $server = $createServerResponse['attributes'];

        // store the server data locally
        $order->update([
            'external_id' => $server['id'],
            'data' => $server,
        ]);
    }

    /**
     * Create the user on Pterodactyl panel and store the data locally
     * If the user already exists, return the user id on Pterodactyl panel
     */
    private function getOrCreatePteroUser(Order $order, ServerConnection $connection): int
    {
        return Cache::lock('pterodactyl:user:'.$connection->id.':'.$order->user_id, 90)
            ->block(5, fn (): int => $this->provisionPteroUser($order, $connection));
    }

    private function provisionPteroUser(Order $order, ServerConnection $connection): int
    {
        $user = $order->user;
        $password = app(CustomerServerCredentials::class)->password($user);

        $userEmailResponse = Server::makeRequest($connection->config, '/api/application/users', 'get', [
            'filter[email]' => $user->email,
        ]);

        $users = $userEmailResponse->json('data');
        if (! is_array($users)) {
            throw new RuntimeException('The panel returned an invalid user list.');
        }

        $matches = collect($users)->pluck('attributes')->filter(fn (mixed $attributes): bool => is_array($attributes)
            && mb_strtolower($attributes['email'] ?? '') === mb_strtolower($user->email))->values();

        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple panel accounts match this customer email.');
        }

        if ($matches->isNotEmpty()) {
            $panelUser = $matches->first();
            $this->validatePteroUser($panelUser, $order);

            if ($password !== null) {
                $response = Server::makeRequest($connection->config, '/api/application/users/'.$panelUser['id'], 'patch', [
                    'email' => $user->email,
                    'username' => $user->username,
                    'first_name' => $user->first_name ?: $user->username,
                    'last_name' => $user->last_name ?: 'Customer',
                    'password' => $password,
                ]);
                $panelUser = $response->json('attributes');
                $this->validatePteroUser($panelUser, $order, $user->username);
                $panelUser['password'] = $password;
            }

            $this->storePteroUserLocally($order, $panelUser);

            return (int) $panelUser['id'];
        }

        if ($password === null) {
            throw new RuntimeException('Log out and log in to your customer account before creating a game panel account, then retry the order.');
        }

        $createUserResponse = Server::makeRequest($connection->config, '/api/application/users', 'post', [
            'first_name' => $user->first_name ?: $user->username,
            'last_name' => $user->last_name ?: 'Customer',
            'email' => $user->email,
            'username' => $user->username,
            'password' => $password,
        ]);

        $panelUser = $createUserResponse->json('attributes');
        $this->validatePteroUser($panelUser, $order, $user->username);

        $this->storePteroUserLocally($order, array_merge($panelUser, ['password' => $password]));
        $this->emailPteroCredentials($order, $user->email);

        return (int) $panelUser['id'];
    }

    private function validatePteroUser(mixed $panelUser, Order $order, ?string $username = null): void
    {
        if (! is_array($panelUser)
            || filter_var($panelUser['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
            || empty($panelUser['username'])
            || mb_strtolower($panelUser['email'] ?? '') !== mb_strtolower($order->user->email)
            || ($username !== null && mb_strtolower($panelUser['username']) !== mb_strtolower($username))) {
            throw new RuntimeException('The panel returned an invalid customer account.');
        }
    }

    /**
     * Store the Pterodactyl user data locally for future reference
     */
    private function storePteroUserLocally(Order $order, array $pteroUserData): void
    {
        $password = $pteroUserData['password'] ?? null;
        unset($pteroUserData['password']);
        $account = ServerAccount::query()->firstOrNew(['order_id' => $order->id, 'server' => 'server-pterodactyl']);
        $account->fill([
            'user_id' => $order->user_id,
            'external_id' => $pteroUserData['id'],
            'username' => $pteroUserData['username'],
            'password' => $password ?? ($account->exists ? $account->password : 'unknown'),
            'data' => array_merge($pteroUserData, ['connection_id' => $order->package->connection_id]),
        ])->save();
    }

    /**
     * Email the user their Pterodactyl panel credentials
     */
    private function emailPteroCredentials(Order $order, string $email): void
    {
        $order->user->email([
            'identifier' => 'server.pterodactyl.account_created',
            'mailable_type' => Order::class,
            'mailable_id' => $order->id,
            'subject' => 'Game Panel Account Created',
            'variables' => ['panel_email' => $email],
            'lines' => [
                'Your account has been created on the game panel.',
                'Use the same email and password as your customer account to log in.',
                "Email: {$email}",
            ],
            'button' => [
                'text' => 'Login to Game Panel',
                'url' => rtrim($order->package->serverConnection->config['hostname'], '/'),
            ],
        ]);
    }

    /**
     * Find a viable node based on the order requirements
     *
     * Returns the node id and allocation id
     *
     * @param  array<int, int|string>  $allowedLocations
     * @return array{node_id: int, allocation_id: int}
     */
    private static function findViableNode(ServerConnection $connection, array $allowedLocations = [], string|int $diskLimit = 0, string|int $memoryLimit = 0, string|int $cpuLimit = 0): array
    {
        foreach ($allowedLocations as $locationId) {
            if (filter_var($locationId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new Exception('Location ID must be a positive numeric ID from Pterodactyl Admin > Locations.');
            }
        }

        $allowedLocations = array_map('intval', $allowedLocations);
        $page = 1;
        $hasEligibleNode = false;

        do {
            $findDeployableNodes = Server::makeRequest($connection->config, '/api/application/nodes/deployable', 'get', [
                'disk' => $diskLimit,
                'memory' => $memoryLimit,
                'cpu' => $cpuLimit,
                'location_ids' => $allowedLocations,
                'include' => 'allocations',
                'page' => $page,
            ]);

            foreach ($findDeployableNodes['data'] ?? [] as $node) {
                $node = $node['attributes'];

                if ($allowedLocations !== [] && ! in_array((int) ($node['location_id'] ?? 0), $allowedLocations, true)) {
                    continue;
                }

                $hasEligibleNode = true;

                foreach ($node['relationships']['allocations']['data'] ?? [] as $allocation) {
                    $allocation = $allocation['attributes'];

                    if ($allocation['assigned']) {
                        continue;
                    }

                    return [
                        'node_id' => $node['id'],
                        'allocation_id' => $allocation['id'],
                    ];
                }
            }

            $totalPages = (int) ($findDeployableNodes['meta']['pagination']['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        if ($hasEligibleNode) {
            throw new RuntimeException('Could not find a free allocation on any eligible node, please contact support');
        }

        throw new RuntimeException('Could not find a node satisfying the requirements in the selected location. Check the Location ID and available memory and disk on Pterodactyl.');
    }

    /**
     * This function is responsible for suspending an instance of the
     * service. This method is called when a order is expired or
     * suspended by an admin
     */
    public function suspend(Order $order, ServerConnection $connection): void
    {
        $serverId = $this->provisionedServerId($order);

        Server::makeRequest($connection->config, "/api/application/servers/{$serverId}/suspend", 'post');
    }

    /**
     * This function is responsible for unsuspending an instance of the
     * service. This method is called when a order is activated or
     * unsuspended by an admin
     */
    public function unsuspend(Order $order, ServerConnection $connection): void
    {
        $serverId = $this->provisionedServerId($order);

        Server::makeRequest($connection->config, "/api/application/servers/{$serverId}/unsuspend", 'post');
    }

    /**
     * This function is responsible for deleting an instance of the
     * service. This can be anything such as a server, vps or any other instance.
     */
    public function terminate(Order $order, ServerConnection $connection): void
    {
        $serverId = $this->provisionedServerId($order);

        $endpoint = "/api/application/servers/{$serverId}";
        $response = Server::makeRequest($connection->config, $endpoint, 'delete', allowedFailureStatuses: [404]);

        if (! $response->notFound()) {
            return;
        }

        if (! $this->isMissingServerResponse($response)) {
            throw new RuntimeException('Pterodactyl returned an unexpected 404 response. Termination could not be confirmed.');
        }

        $serverLookup = Server::makeRequest($connection->config, $endpoint, allowedFailureStatuses: [404]);

        if (! $this->isMissingServerResponse($serverLookup)) {
            throw new RuntimeException('Pterodactyl did not confirm that the server was deleted.');
        }

        $serverList = Server::makeRequest($connection->config, '/api/application/servers', data: ['per_page' => 1]);

        if (! $serverList->ok() || ! is_array($serverList->json('data')) || ! is_array($serverList->json('meta.pagination'))) {
            throw new RuntimeException('Pterodactyl Application API access could not be verified. Termination could not be confirmed.');
        }
    }

    private function isMissingServerResponse(Response $response): bool
    {
        return $response->notFound()
            && $response->json('errors.0.code') === 'NotFoundHttpException'
            && $response->json('errors.0.detail') === 'The requested resource could not be found on the server.';
    }

    private function provisionedServerId(Order $order): string
    {
        $serverId = (string) $order->external_id;

        if (! ctype_digit($serverId) || (int) $serverId < 1) {
            throw new RuntimeException("Order #{$order->id} does not have a provisioned Pterodactyl server ID. Provision the server or link its numeric panel server ID before performing this action.");
        }

        return $serverId;
    }

    /** @return array<string, mixed> */
    private function ownedPanelUser(Order $order, ServerConnection $connection, int $userId): array
    {
        if ($userId < 1) {
            throw new RuntimeException('The panel server does not have a valid owner.');
        }
        $owner = Server::makeRequest($connection->config, '/api/application/users/'.$userId)->json('attributes');
        if (! is_array($owner) || mb_strtolower($owner['email'] ?? '') !== mb_strtolower($order->user->email)) {
            throw new RuntimeException('The panel server does not belong to this customer.');
        }

        return $owner;
    }

    private function megabytes(mixed $value): int
    {
        return (float) $value === -1.0 ? -1 : (int) ceil((float) $value * 1024);
    }

    public function upgradeOrDowngrade(Order $order, PackagePrice $oldPrice, PackagePrice $newPrice, ServerConnection $connection): void
    {
        if ((int) $newPrice->package->connection_id !== (int) $connection->id) {
            throw new RuntimeException('An upgrade must use the same Pterodactyl connection.');
        }
        $serverId = $this->provisionedServerId($order);
        $server = Server::makeRequest($connection->config, '/api/application/servers/'.$serverId)->json('attributes');
        $option = fn (string $key, mixed $default = null): mixed => $order->prices->firstWhere('key', $key)?->value ?? $newPrice->package->data($key, $default);
        Server::makeRequest($connection->config, '/api/application/servers/'.$serverId.'/build', 'patch', [
            'allocation' => $server['allocation'],
            'limits' => [
                'memory' => $this->megabytes($option('memory_limit', 0)),
                'disk' => $this->megabytes($option('disk_limit', 0)),
                'swap' => $this->megabytes($option('swap_limit', 0)),
                'cpu' => $option('cpu_limit', 0), 'io' => $option('block_io_weight', 500),
                'threads' => $option('cpu_pinning'),
            ],
            'feature_limits' => [
                'databases' => (int) $option('database_limit', 0),
                'allocations' => (int) $option('allocation_limit', 0),
                'backups' => (int) $option('backup_limit', 0),
            ],
        ]);
    }

    public function upgrade(Order $order)
    {
        // TODO: Implement upgrade() method.
    }
}
