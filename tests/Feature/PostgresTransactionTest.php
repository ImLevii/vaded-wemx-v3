<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresTransactionTest extends TestCase
{
    public function test_bound_queries_can_repeat_inside_a_pooled_postgres_transaction(): void
    {
        $databaseUrl = getenv('POSTGRES_TEST_URL');

        if (! $databaseUrl || ! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('Set POSTGRES_TEST_URL to run the read-only PostgreSQL integration test.');
        }

        $connection = DB::build(array_replace(config('database.connections.pgsql'), [
            'url' => $databaseUrl,
            'port' => '5432',
        ]));

        try {
            $connection->beginTransaction();
            $connection->unprepared('SET TRANSACTION READ ONLY');

            foreach (["Community's server", 'Monthly', 'Yearly'] as $value) {
                $this->assertSame($value, $connection->selectOne('SELECT CAST(? AS TEXT) AS value', [$value])->value);
            }

            $this->assertTrue($connection->selectOne('SELECT true AS is_active WHERE true = ?', [true])->is_active);
            $this->assertFalse($connection->selectOne('SELECT false AS is_active WHERE false = ?', [false])->is_active);

            $connection->commit();
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            $connection->disconnect();
        }
    }
}
