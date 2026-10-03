<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserImportBackup;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery;
use PDO;
use Tests\TestCase;

class UserImportBackupTest extends TestCase
{
    use DatabaseMigrations;

    public function test_sqlite_backup_contains_the_original_customer_credentials(): void
    {
        $user = User::factory()->create();
        $prefix = sys_get_temp_dir().'/wemx-account-snapshot-'.bin2hex(random_bytes(6));
        $path = $prefix.'.sqlite';
        try {
            $this->assertSame($path, (new UserImportBackup)->create($prefix));
            $snapshot = new PDO('sqlite:'.$path);
            $row = $snapshot->query('SELECT id, password FROM users')->fetch(PDO::FETCH_ASSOC);
            $this->assertSame($user->id, $row['id']);
            $this->assertSame($user->password, $row['password']);
            $snapshot = null;
        } finally {
            File::delete($path);
        }
    }

    public function test_postgres_snapshot_preserves_profiles_credentials_and_permission_rows(): void
    {
        $user = User::factory()->create(['data' => ['_legacy_wemx' => ['id' => 10]]]);
        $realConnection = DB::connection();
        $manager = DB::getFacadeRoot();
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn('pgsql');
        $connection->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback): array => $callback());
        $connection->shouldReceive('statement')->once()->with('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY')->andReturnTrue();
        $tableNames = ['users', 'addresses', 'roles', 'role_user', 'user_bans', 'orders', 'server_accounts', 'order_prices'];
        foreach ($tableNames as $table) {
            $connection->shouldReceive('table')->once()->with($table)->andReturn($realConnection->table($table));
        }
        DB::shouldReceive('connection')->once()->andReturn($connection);
        $prefix = sys_get_temp_dir().'/wemx-account-snapshot-'.bin2hex(random_bytes(6));
        $path = $prefix.'.accounts.json';
        try {
            $this->assertSame($path, (new UserImportBackup)->create($prefix, $tableNames));
            $snapshot = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('wemx-customer-accounts-v1', $snapshot['format']);
            $this->assertSame($tableNames, array_keys($snapshot['tables']));
            $this->assertCount(1, $snapshot['tables']['users']);
            $this->assertCount(1, $snapshot['tables']['addresses']);
            $this->assertSame($user->password, $snapshot['tables']['users'][0]['password']);
            $this->assertSame($user->id, $snapshot['tables']['addresses'][0]['user_id']);
            $this->assertSame(10, json_decode($snapshot['tables']['users'][0]['data'], true)['_legacy_wemx']['id']);
        } finally {
            DB::swap($manager);
            File::delete($path);
        }
    }
}
