<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class UserImportBackup
{
    /** @param list<string> $tableNames */
    public function create(string $prefix, array $tableNames = ['users', 'addresses', 'roles', 'role_user', 'user_bans']): string
    {
        $connection = DB::connection();
        if ($connection->getDriverName() === 'sqlite') {
            $path = $prefix.'.sqlite';
            $pdo = $connection->getPdo();
            $pdo->exec('VACUUM INTO '.$pdo->quote($path));

            return $path;
        }
        if ($connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('User import backups support SQLite and PostgreSQL.');
        }

        $snapshot = $connection->transaction(function () use ($connection, $tableNames): array {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            $tables = [];
            foreach ($tableNames as $table) {
                $tables[$table] = $connection->table($table)->orderBy('id')->get()->all();
            }

            return ['format' => 'wemx-customer-accounts-v1', 'driver' => 'pgsql',
                'created_at' => now()->toIso8601String(), 'tables' => $tables];
        });
        $path = $prefix.'.accounts.json';
        if (File::put($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Cannot save the pre-import account snapshot.');
        }

        return $path;
    }
}
