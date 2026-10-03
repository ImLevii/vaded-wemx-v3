<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class BackupUserImporter
{
    /**
     * @param  array<string, list<array<string, mixed>>>  $wemxTables
     * @param  array<string, list<array<string, mixed>>>  $panelTables
     * @return array{created: array<string, int>, matched: array<string, int>, linked: array<string, int>, usernames_renamed: int, existing_passwords_retained: int, ids: array<string, array<int, int>>}
     */
    public function import(array $wemxTables, array $panelTables, bool $commit = false): array
    {
        if (! User::query()->whereKey(1)->exists()) {
            throw new RuntimeException('Create the primary administrator before importing customer accounts.');
        }
        if (! empty($wemxTables['user_2fa'])) {
            throw new RuntimeException('Legacy two-factor accounts require a separate authenticator migration.');
        }
        $sources = ['wemx' => $wemxTables['users'] ?? [], 'pterodactyl' => $panelTables['users'] ?? []];
        foreach ($sources as $source => &$rows) {
            $emails = [];
            $ids = [];
            foreach ($rows as &$row) {
                $row['email'] = mb_strtolower(trim((string) ($row['email'] ?? '')));
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1 || isset($ids[$id]) || isset($emails[$row['email']])) {
                    throw new RuntimeException("Duplicate or invalid identity in {$source} user records.");
                }
                $algorithm = password_get_info((string) ($row['password'] ?? ''))['algoName'];
                $compatible = match (config('hashing.driver')) {
                    'bcrypt' => $algorithm === 'bcrypt',
                    'argon' => $algorithm === 'argon2i',
                    'argon2id' => $algorithm === 'argon2id',
                    default => false,
                };
                if (! filter_var($row['email'], FILTER_VALIDATE_EMAIL) || ! $compatible) {
                    throw new RuntimeException("Invalid email or incompatible password hash for {$source} user {$id}.");
                }
                if (($row['use_totp'] ?? false) || ($row['tfa_enabled'] ?? false)) {
                    throw new RuntimeException("Two-factor migration is required for {$source} user {$id}.");
                }
                $ids[$id] = true;
                $emails[$row['email']] = true;
            }
            unset($row);
        }
        unset($rows);
        if (array_sum(array_map('count', $sources)) === 0) {
            throw new RuntimeException('No user records found in the backup.');
        }
        $report = ['created' => ['wemx' => 0, 'pterodactyl' => 0], 'matched' => ['wemx' => 0, 'pterodactyl' => 0],
            'linked' => ['wemx' => 0, 'pterodactyl' => 0], 'usernames_renamed' => 0, 'existing_passwords_retained' => 0,
            'ids' => ['wemx' => [], 'pterodactyl' => []]];
        DB::beginTransaction();
        try {
            Model::withoutEvents(function () use ($sources, &$report): void {
                $byEmail = [];
                $usernames = [];
                $bySource = ['wemx' => [], 'pterodactyl' => []];
                foreach (User::query()->lockForUpdate()->get() as $user) {
                    $email = mb_strtolower(trim($user->email));
                    if (isset($byEmail[$email])) {
                        throw new RuntimeException('Existing accounts have duplicate normalized emails.');
                    }
                    $byEmail[$email] = $user;
                    $usernames[mb_strtolower($user->username)] = true;
                    foreach (array_keys($sources) as $source) {
                        $id = $user->data['_legacy_'.$source]['id'] ?? null;
                        if ($id !== null) {
                            if (isset($bySource[$source][$id])) {
                                throw new RuntimeException("Existing accounts have duplicate {$source} identities.");
                            }
                            $bySource[$source][$id] = $user;
                        }
                    }
                }
                foreach ($sources as $source => $rows) {
                    foreach ($rows as $row) {
                        $id = (int) $row['id'];
                        $user = $byEmail[$row['email']] ?? null;
                        $identified = $bySource[$source][$id] ?? null;
                        if ($identified && (! $user || $identified->id !== $user->id)) {
                            throw new RuntimeException("Email conflicts with the previously imported {$source} user {$id}.");
                        }
                        $key = '_legacy_'.$source;
                        if ($user) {
                            $data = $user->data ?? [];
                            if (isset($data[$key]['id']) && (int) $data[$key]['id'] !== $id) {
                                throw new RuntimeException("Source identity conflicts for {$source} user {$id}.");
                            }
                            $report['matched'][$source]++;
                            if (! hash_equals($user->password, $row['password'])) {
                                $report['existing_passwords_retained']++;
                            }
                        } else {
                            $username = mb_substr(trim((string) ($row['username'] ?? '')) ?: $source.'-'.$id, 0, 255);
                            $base = mb_substr($username, 0, 210);
                            $suffix = 0;
                            while (isset($usernames[mb_strtolower($username)])) {
                                $suffix++;
                                $username = $base.'-'.$source.'-'.$id.'-'.$suffix;
                            }
                            $report['usernames_renamed'] += $suffix > 0 ? 1 : 0;
                            $attributes = $source === 'wemx'
                                ? Arr::only($row, ['first_name', 'last_name', 'status', 'balance', 'avatar', 'is_subscribed', 'language', 'email_verified_at', 'last_seen_at', 'last_login_at', 'created_at', 'updated_at'])
                                : ['first_name' => $row['name_first'] ?? null, 'last_name' => $row['name_last'] ?? null,
                                    'language' => $row['language'] ?? 'en', 'created_at' => $row['created_at'] ?? now(), 'updated_at' => $row['updated_at'] ?? now()];
                            $data = $source === 'wemx' ? json_decode($row['data'] ?? '{}', true, flags: JSON_THROW_ON_ERROR) : [];
                            if (! is_array($data)) {
                                throw new RuntimeException("Invalid profile data for {$source} user {$id}.");
                            }
                            $user = new User;
                            $user->forceFill($attributes + ['username' => $username, 'email' => $row['email'],
                                'password' => $row['password'], 'status' => 'active', 'data' => $data])->save();
                            $user->createEmptyAddress();
                            $byEmail[$row['email']] = $user;
                            $usernames[mb_strtolower($username)] = true;
                            $report['created'][$source]++;
                        }
                        $metadata = $data[$key] ?? [];
                        $metadata['id'] = $id;
                        if ($source === 'pterodactyl') {
                            $metadata['uuid'] = $row['uuid'] ?? null;
                            $metadata['username'] = $row['username'] ?? null;
                        } else {
                            $metadata['visibility'] ??= $row['visibility'] ?? null;
                        }
                        if (($data[$key] ?? null) !== $metadata) {
                            $data[$key] = $metadata;
                            $user->timestamps = false;
                            $user->forceFill(['data' => $data])->save();
                            $report['linked'][$source]++;
                        }
                        $bySource[$source][$id] = $user;
                        $report['ids'][$source][$id] = $user->id;
                    }
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
}
