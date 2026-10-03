<?php

namespace Tests\Feature;

use App\Events\Payments\PaymentCreated;
use App\Events\Users\UserCreated;
use App\Models\BalanceTransaction;
use App\Models\Category;
use App\Models\Extension;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\ServerConnection;
use App\Models\User;
use App\Services\LegacyCustomerDataImporter;
use Extensions\Servers\Pterodactyl\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LegacyCustomerDataImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create();
        $this->customer = User::factory()->create(['email' => 'customer@example.test', 'balance' => 99,
            'phone' => 'current-phone', 'country' => null, 'data' => ['current' => 'retained'],
            'tfa_enabled' => true, 'tfa_secret' => 'current-secret', 'updated_at' => '2025-01-01 00:00:00']);
        Extension::query()->updateOrCreate(['identifier' => 'server-pterodactyl'], [
            'namespace' => Server::class, 'type' => 'server', 'name' => 'Pterodactyl', 'status' => 'enabled', 'version' => '1.0.0',
        ]);
        $connection = ServerConnection::create(['alias' => 'Import test', 'extension_identifier' => 'server-pterodactyl', 'config' => []]);
        $category = Category::create(['name' => 'Games', 'slug' => 'games', 'status' => 'active', 'icon' => 'server']);
        $this->package = Package::create(['name' => 'Legacy game', 'slug' => 'legacy-game', 'category_id' => $category->id,
            'connection_id' => $connection->id, 'status' => 'active', 'icon' => 'server', 'data' => ['_legacy_wemx' => ['id' => 50]]]);
        Queue::fake();
        Event::fake([UserCreated::class, PaymentCreated::class]);
    }

    public function test_dry_run_reports_missing_history_and_rolls_back_every_change(): void
    {
        $before = $this->customer->fresh()->getAttributes();
        $report = app(LegacyCustomerDataImporter::class)->import($this->tables());
        $this->assertSame(['users' => 0, 'orders' => 1, 'payments' => 1, 'balance_transactions' => 1], $report['created']);
        $this->assertSame(['addresses' => 1, 'orders' => 1, 'payments' => 1], $report['archived']);
        $this->assertSame($before, $this->customer->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('balance_transactions', 0);
        $this->assertNull($this->customer->fresh()->address->address);
        Queue::assertNothingPushed();
    }

    public function test_history_import_preserves_credentials_balances_and_existing_contact_fields(): void
    {
        $before = $this->customer->fresh()->getAttributes();
        $tables = $this->tables();
        app(LegacyCustomerDataImporter::class)->import($tables, true);
        $customer = $this->customer->fresh();
        foreach (['username', 'email', 'first_name', 'last_name', 'password', 'status', 'balance', 'tfa_enabled', 'tfa_secret', 'updated_at'] as $field) {
            $this->assertSame($before[$field], $customer->getAttributes()[$field]);
        }
        $this->assertSame('current-phone', $customer->phone);
        $this->assertSame('US', $customer->country);
        $this->assertSame('retained', $customer->data['current']);
        $this->assertSame('123 Example Street', $customer->address->address);
        $this->assertSame('Suite 2', $customer->address->address2);
        $order = Order::query()->sole();
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame($this->package->id, $order->package_id);
        $this->assertSame('terminated', $order->status);
        $this->assertNull($order->external_id);
        $this->assertFalse($order->auto_balance_renew);
        $this->assertEqualsWithDelta(12.34, $order->price, 0.000001);
        $this->assertSame('server-short-id', $order->data['_legacy_wemx']['external_id']);
        $payment = Payment::query()->sole();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('12.34000000', $payment->total);
        $this->assertSame($order->id, $payment->payable_id);
        $this->assertSame($order->getMorphClass(), $payment->payable_type);
        $this->assertNull($payment->gateway_config_id);
        $this->assertNull($payment->handler);
        $this->assertSame('2024-08-02', $payment->paid_at->toDateString());
        $archive = $customer->data['_legacy_customer_data'];
        $this->assertSame('active', $archive['orders'][71]['status']);
        $this->assertSame('unpaid', $archive['payments']['invoice-unpaid']['status']);
        $this->assertSame('source-payment', $archive['balance_transactions'][90]['payment_id']);
        Event::assertNotDispatched(UserCreated::class);
        Event::assertNotDispatched(PaymentCreated::class);
        Queue::assertNothingPushed();
    }

    public function test_rerun_creates_no_duplicates_or_changes(): void
    {
        $importer = app(LegacyCustomerDataImporter::class);
        $tables = $this->tables();
        $importer->import($tables, true);
        $before = $this->customer->fresh()->getAttributes();
        $report = $importer->import($tables, true);
        $this->assertSame(0, array_sum($report['created']));
        $this->assertSame(0, array_sum($report['archived']));
        $this->assertSame(0, $report['contacts_completed']);
        $this->assertSame(1, $report['matched']['orders']);
        $this->assertSame(1, $report['matched']['payments']);
        $this->assertSame(1, $report['matched']['balance_transactions']);
        $this->assertSame($before, $this->customer->fresh()->getAttributes());
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('balance_transactions', 1);
    }

    public function test_new_customer_is_imported_with_source_credentials_and_no_role_assignment(): void
    {
        $tables = $this->tables();
        $tables['users'][0]['email'] = 'new@example.test';
        $report = app(LegacyCustomerDataImporter::class)->import($tables, true);
        $customer = User::query()->where('email', 'new@example.test')->sole();
        $this->assertSame(1, $report['created']['users']);
        $this->assertSame($tables['users'][0]['password'], $customer->password);
        $this->assertSame('12.34000000', $customer->balance);
        $this->assertFalse($customer->roles()->exists());
        $this->assertSame($customer->id, Payment::query()->sole()->user_id);
        $this->assertSame($customer->id, BalanceTransaction::query()->sole()->user_id);
    }

    public function test_existing_package_without_legacy_metadata_matches_by_source_name(): void
    {
        $this->package->forceFill(['data' => []])->saveQuietly();
        $tables = $this->tables();
        $tables['packages'] = [['id' => 50, 'name' => $this->package->name]];
        $report = app(LegacyCustomerDataImporter::class)->import($tables, true);
        $this->assertSame(1, $report['created']['orders']);
        $this->assertSame($this->package->id, Order::query()->sole()->package_id);
    }

    public function test_sql_string_identities_match_existing_records_on_rerun(): void
    {
        $tables = $this->tables();
        foreach ($tables as &$rows) {
            foreach ($rows as &$row) {
                foreach (['id', 'user_id', 'package_id', 'order_id'] as $field) {
                    if (isset($row[$field])) {
                        $row[$field] = (string) $row[$field];
                    }
                }
            }
            unset($row);
        }
        unset($rows);
        $importer = app(LegacyCustomerDataImporter::class);
        $importer->import($tables, true);
        $report = $importer->import($tables, true);
        $this->assertSame(0, array_sum($report['created']));
        $this->assertSame(0, array_sum($report['archived']));
        $this->assertSame(1, $report['matched']['orders']);
        $this->assertDatabaseCount('orders', 1);
    }

    #[DataProvider('invalidRecords')]
    public function test_invalid_or_conflicting_data_rolls_back_the_whole_import(string $case): void
    {
        $tables = $this->tables();
        if ($case === 'missing_user') {
            $tables['balance_transactions'][0]['user_id'] = 999;
        } elseif ($case === 'duplicate') {
            $tables['payments'][] = $tables['payments'][0];
        } elseif ($case === 'amount') {
            $tables['payments'][0]['amount'] = '-12.34';
        } elseif ($case === 'pricing') {
            $tables['orders'][0]['price'] = '{"type":"recurring","period":0,"price":10}';
        } elseif ($case === 'result') {
            $tables['balance_transactions'][0]['result'] = 'invalid';
        } else {
            Payment::withoutEvents(fn () => Payment::create(['user_id' => 1, 'token' => 'existing', 'description' => 'Existing payment',
                'transaction_id' => 'source-transaction', 'status' => 'paid', 'total' => 12.34, 'currency' => 'USD']));
        }
        $before = $this->customer->fresh()->getAttributes();
        try {
            app(LegacyCustomerDataImporter::class)->import($tables, true);
            $this->fail('Unsafe source records must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertNotEmpty($exception->getMessage());
            $this->assertSame($before, $this->customer->fresh()->getAttributes());
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('payments', $case === 'ownership' ? 1 : 0);
            $this->assertDatabaseCount('balance_transactions', 0);
        }
    }

    /** @return list<array{string}> */
    public static function invalidRecords(): array
    {
        return [['missing_user'], ['duplicate'], ['amount'], ['pricing'], ['result'], ['ownership']];
    }

    public function test_command_dry_run_reads_history_without_committing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'customer-data-');
        $hash = Hash::make('fixture-password');
        file_put_contents($path, "CREATE TABLE `users` (\n `id` bigint,\n `username` text,\n `email` text,\n `password` text\n);\nINSERT INTO `users` VALUES (10,'fixture','fixture@example.test','{$hash}');\nCREATE TABLE `balance_transactions` (\n `id` bigint,\n `user_id` bigint,\n `result` text,\n `amount` text,\n `balance_before_transaction` text\n);\nINSERT INTO `balance_transactions` VALUES (90,10,'+','12.34','0.00');");
        try {
            $this->artisan('app:import-customer-data', ['dump' => $path])->expectsOutput('Dry run passed; all changes rolled back.')->assertSuccessful();
            $this->assertDatabaseCount('users', 2);
            $this->assertDatabaseCount('balance_transactions', 0);
        } finally {
            unlink($path);
        }
    }

    /** @return array<string, list<array<string, mixed>>> */
    protected function tables(): array
    {
        $price = json_encode(['id' => 50, 'type' => 'recurring', 'period' => 30, 'price' => '12.34', 'renewal_price' => '12.34']);

        return [
            'users' => [['id' => 10, 'username' => 'legacy-customer', 'email' => 'customer@example.test',
                'password' => Hash::make('legacy-password'), 'balance' => '12.34', 'data' => '{}']],
            'addresses' => [['id' => 20, 'user_id' => 10, 'address' => '123 Example Street', 'address_2' => 'Suite 2', 'country' => 'US', 'phone_number' => 'source-phone']],
            'orders' => [
                ['id' => 70, 'user_id' => 10, 'package_id' => 50, 'status' => 'terminated', 'external_id' => 'server-short-id', 'price' => $price],
                ['id' => 71, 'user_id' => 10, 'package_id' => 50, 'status' => 'active', 'external_id' => 'unverified-server', 'price' => $price],
            ],
            'payments' => [
                ['id' => 'invoice-paid', 'user_id' => 10, 'order_id' => 70, 'status' => 'paid', 'amount' => '12.34', 'currency' => 'USD',
                    'description' => 'Legacy invoice', 'transaction_id' => 'source-transaction', 'created_at' => '2024-08-01 00:00:00',
                    'updated_at' => '2024-08-02 00:00:00', 'gateway' => '{"driver":"PayPalCheckout"}'],
                ['id' => 'invoice-unpaid', 'user_id' => 10, 'order_id' => 71, 'status' => 'unpaid', 'amount' => '12.34', 'currency' => 'USD'],
            ],
            'balance_transactions' => [['id' => 90, 'user_id' => 10, 'payment_id' => 'source-payment', 'result' => '+',
                'amount' => '12.34', 'balance_before_transaction' => '0.00', 'description' => 'Legacy topup']],
        ];
    }
}
