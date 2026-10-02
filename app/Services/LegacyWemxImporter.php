<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Category;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\PackagePrice;
use App\Models\ServerConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LegacyWemxImporter
{
    /**
     * @param  array<string, list<array<string, mixed>>>  $tables
     * @param  array<int, int>  $eggNests
     * @return array{created: array<string, int>, matched: array<string, int>, ids: array<string, array<int, int>>}
     */
    public function import(array $tables, ServerConnection $connection, array $eggNests, bool $commit = false): array
    {
        if ($connection->extension_identifier !== 'server-pterodactyl') {
            throw new RuntimeException('Select a Pterodactyl server connection.');
        }
        if (! empty($tables['package_config_options'])) {
            throw new RuntimeException('Legacy configurable options require an explicit pricing conversion before importing.');
        }
        foreach (['categories', 'packages', 'users'] as $table) {
            if (empty($tables[$table])) {
                throw new RuntimeException("No {$table} records found in the dump.");
            }
        }

        $report = ['created' => array_fill_keys(['categories', 'packages', 'prices', 'features', 'users', 'addresses'], 0),
            'matched' => array_fill_keys(['categories', 'packages', 'users'], 0), 'ids' => ['categories' => [], 'packages' => [], 'users' => []]];
        DB::beginTransaction();
        try {
            Model::withoutEvents(function () use ($tables, $connection, $eggNests, &$report): void {
                foreach ($tables['categories'] as $row) {
                    $slug = $row['link'];
                    $matches = Category::where('slug', $slug)->orWhere('name', $row['name'])->get();
                    if ($matches->count() > 1) {
                        throw new RuntimeException("Ambiguous existing category for legacy ID {$row['id']}.");
                    }
                    $category = $matches->first();
                    if ($category) {
                        $report['matched']['categories']++;
                    } else {
                        $category = new Category;
                        $category->forceFill(Arr::only($row, ['name', 'status', 'icon', 'description', 'created_at', 'updated_at']) + [
                            'slug' => $slug, 'sort_order' => (int) $row['order'],
                        ])->save();
                        $report['created']['categories']++;
                    }
                    $report['ids']['categories'][(int) $row['id']] = $category->id;
                }

                foreach ($tables['packages'] as $row) {
                    $package = Package::where('name', $row['name'])->first();
                    if ($package) {
                        $report['matched']['packages']++;
                        $report['ids']['packages'][(int) $row['id']] = $package->id;

                        continue;
                    }
                    if ($row['service'] !== 'pterodactyl') {
                        throw new RuntimeException("Unsupported service for legacy package {$row['id']}.");
                    }
                    $categoryId = $report['ids']['categories'][(int) $row['category_id']] ?? null;
                    if (! $categoryId) {
                        throw new RuntimeException("Missing category for legacy package {$row['id']}.");
                    }
                    $data = $this->json($row['data']);
                    $eggId = (int) ($data['egg'] ?? 0);
                    $locations = $data['locations'] ?? [];
                    if (! isset($eggNests[$eggId]) || count($locations) !== 1 || ! ctype_digit((string) $locations[0]) || (int) $locations[0] < 1) {
                        throw new RuntimeException("Verify the egg and single numeric location for legacy package {$row['id']}.");
                    }
                    $data['_legacy_wemx'] = [
                        'id' => (int) $row['id'],
                        'setup_on' => $row['setup_on'] ?? null,
                        'allow_coupons' => $row['allow_coupons'] ?? null,
                        'require_domain' => $row['require_domain'] ?? null,
                        'settings' => array_values(array_filter($tables['package_settings'] ?? [], fn (array $setting): bool => $setting['package_id'] == $row['id'])),
                    ];
                    $data['egg_id'] = $eggId;
                    $data['nest_id'] = $eggNests[$eggId];
                    $data['location_id'] = (int) $locations[0];
                    foreach (['memory_limit', 'disk_limit', 'swap_limit'] as $limit) {
                        $value = (float) ($data[$limit] ?? 0);
                        $data[$limit] = $value === -1.0 ? -1 : $value / 1024;
                    }
                    $data['block_io_weight'] ??= 500;
                    $slug = Str::slug($row['name']) ?: 'legacy-package-'.$row['id'];
                    if (Package::where('slug', $slug)->exists()) {
                        $slug .= '-legacy-'.$row['id'];
                    }
                    $package = new Package;
                    $package->forceFill(Arr::only($row, ['name', 'description', 'icon', 'status', 'global_quantity', 'client_quantity', 'allow_notes', 'created_at', 'updated_at']) + [
                        'category_id' => $categoryId, 'connection_id' => $connection->id,
                        'slug' => $slug, 'sort_order' => (int) $row['order'], 'data' => $data,
                    ])->save();
                    $report['ids']['packages'][(int) $row['id']] = $package->id;
                    $report['created']['packages']++;

                    foreach ($tables['package_prices'] ?? [] as $price) {
                        if ($price['package_id'] != $row['id']) {
                            continue;
                        }
                        if ((float) $price['price'] !== (float) $price['renewal_price'] && $price['type'] === 'recurring') {
                            throw new RuntimeException("Different renewal pricing requires review for legacy package {$row['id']}.");
                        }
                        $priceData = $this->json($price['data'] ?? null);
                        $priceData['_legacy_wemx'] = Arr::only($price, ['id', 'type', 'renewal_price', 'cancellation_fee']);
                        (new PackagePrice)->forceFill(Arr::only($price, ['price', 'setup_fee', 'upgrade_fee', 'is_active', 'created_at', 'updated_at']) + [
                            'package_id' => $package->id, 'period_in_days' => $price['type'] === 'single' ? 0 : (int) $price['period'], 'data' => $priceData,
                        ])->save();
                        $report['created']['prices']++;
                    }
                    foreach ($tables['package_features'] ?? [] as $feature) {
                        if ($feature['package_id'] != $row['id']) {
                            continue;
                        }
                        (new PackageFeature)->forceFill(Arr::only($feature, ['description', 'created_at', 'updated_at']) + [
                            'package_id' => $package->id, 'sort_order' => (int) $feature['order'],
                        ])->save();
                        $report['created']['features']++;
                    }
                }

                foreach ($tables['users'] as $row) {
                    $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($row['email'])])->first();
                    if ($user) {
                        $report['matched']['users']++;
                        $report['ids']['users'][(int) $row['id']] = $user->id;

                        continue;
                    }
                    if (! filter_var($row['email'], FILTER_VALIDATE_EMAIL) || password_get_info($row['password'])['algo'] === null) {
                        throw new RuntimeException("Invalid email or unsupported password hash for legacy user {$row['id']}.");
                    }
                    $username = $row['username'];
                    if (User::whereRaw('LOWER(username) = ?', [mb_strtolower($username)])->exists()) {
                        $username .= '-legacy-'.$row['id'];
                    }
                    $addresses = array_values(array_filter($tables['addresses'] ?? [], fn (array $address): bool => $address['user_id'] == $row['id']));
                    usort($addresses, function (array $left, array $right): int {
                        $fields = ['address', 'address_2', 'city', 'region', 'zip_code', 'country', 'company_name', 'phone_number'];

                        return [count(array_filter(Arr::only($right, $fields))), (int) $right['id']]
                            <=> [count(array_filter(Arr::only($left, $fields))), (int) $left['id']];
                    });
                    $address = $addresses[0] ?? [];
                    $data = $this->json($row['data'] ?? null);
                    $data['_legacy_wemx'] = ['id' => (int) $row['id'], 'visibility' => $row['visibility'] ?? null, 'addresses' => $addresses];
                    $user = new User;
                    $user->forceFill(Arr::only($row, ['email', 'first_name', 'last_name', 'status', 'balance', 'avatar', 'is_subscribed', 'language', 'password', 'email_verified_at', 'last_seen_at', 'last_login_at', 'created_at', 'updated_at']) + [
                        'username' => $username, 'phone' => $address['phone_number'] ?? null,
                        'country' => $address['country'] ?? null, 'data' => $data,
                    ])->save();
                    (new Address)->forceFill(Arr::only($address, ['company_name', 'address', 'country', 'region', 'city', 'zip_code', 'created_at', 'updated_at']) + [
                        'user_id' => $user->id, 'address2' => $address['address_2'] ?? null,
                    ])->save();
                    $report['created']['users']++;
                    $report['created']['addresses']++;
                    $report['ids']['users'][(int) $row['id']] = $user->id;
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
    }

    /** @return array<string, mixed> */
    private function json(?string $value): array
    {
        $decoded = json_decode($value ?? '{}', true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Expected JSON object or array in legacy data.');
        }

        return $decoded;
    }
}
