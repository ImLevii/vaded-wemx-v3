<?php

namespace App\Services;

use App\Models\Address;
use App\Models\BalanceTransaction;
use App\Models\GatewayConfig;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LegacyCustomerDataImporter
{
    public function __construct(private BackupUserImporter $users) {}

    /**
     * @param  array<string, list<array<string, mixed>>>  $tables
     * @return array{created: array<string, int>, matched: array<string, int>, archived: array<string, int>, contacts_completed: int}
     */
    public function import(array $tables, bool $commit = false): array
    {
        foreach (['subscriptions', 'payment_taxes'] as $table) {
            if (! empty($tables[$table])) {
                throw new RuntimeException("Legacy {$table} require a separate schema conversion.");
            }
        }
        foreach (['addresses', 'orders', 'payments', 'balance_transactions'] as $table) {
            $ids = [];
            foreach ($tables[$table] ?? [] as $row) {
                $id = (string) ($row['id'] ?? '');
                if ($id === '' || isset($ids[$id])) {
                    throw new RuntimeException("Duplicate or missing {$table} identity.");
                }
                $ids[$id] = true;
            }
        }

        $report = ['created' => ['users' => 0, 'orders' => 0, 'payments' => 0, 'balance_transactions' => 0],
            'matched' => ['users' => 0, 'orders' => 0, 'payments' => 0, 'balance_transactions' => 0],
            'archived' => ['addresses' => 0, 'orders' => 0, 'payments' => 0], 'contacts_completed' => 0];
        DB::beginTransaction();
        try {
            $users = $this->users->import($tables, [], true);
            $report['created']['users'] = $users['created']['wemx'];
            $report['matched']['users'] = $users['matched']['wemx'];
            $customers = User::query()->with('address')->whereIn('id', $users['ids']['wemx'])->lockForUpdate()->get()->keyBy('id');
            Model::withoutEvents(function () use ($tables, $users, $customers, &$report): void {
                $archive = [];
                foreach ($customers as $customer) {
                    $archive[$customer->id] = $customer->data['_legacy_customer_data'] ?? [];
                }
                $customerFor = function (array $row) use ($users, $customers): User {
                    $id = $users['ids']['wemx'][(int) ($row['user_id'] ?? 0)] ?? null;
                    if ($id === null || ! isset($customers[$id])) {
                        throw new RuntimeException('A customer data record references a missing source user.');
                    }

                    return $customers[$id];
                };
                foreach ($tables['addresses'] ?? [] as $row) {
                    $customer = $customerFor($row);
                    if (($archive[$customer->id]['addresses'][$row['id']] ?? null) !== $row) {
                        $archive[$customer->id]['addresses'][$row['id']] = $row;
                        $report['archived']['addresses']++;
                    }
                    $address = $customer->address;
                    if (! $address) {
                        $address = (new Address)->forceFill(['user_id' => $customer->id]);
                        $customer->setRelation('address', $address);
                    }
                    foreach (['company_name' => 'company_name', 'address' => 'address', 'address_2' => 'address2',
                        'country' => 'country', 'city' => 'city', 'region' => 'region', 'zip_code' => 'zip_code'] as $source => $target) {
                        if ($this->missing($address->$target) && ! $this->missing($row[$source] ?? null)) {
                            $address->$target = $row[$source];
                        }
                    }
                    foreach (['phone_number' => 'phone', 'country' => 'country'] as $source => $target) {
                        if ($this->missing($customer->$target) && ! $this->missing($row[$source] ?? null)) {
                            $customer->$target = $row[$source];
                        }
                    }
                    if ($address->isDirty()) {
                        $address->timestamps = false;
                        $address->save();
                        $report['contacts_completed']++;
                    }
                }

                $orders = [];
                foreach ($tables['orders'] ?? [] as $row) {
                    $customer = $customerFor($row);
                    $matches = Order::query()->where(fn ($query) => $query
                        ->where('data->_legacy_wemx->id', (int) $row['id'])
                        ->orWhere('data->_legacy_wemx->id', (string) $row['id']))->get();
                    if ($matches->count() > 1) {
                        throw new RuntimeException('Multiple orders share a legacy identity.');
                    }
                    if ($order = $matches->first()) {
                        if ((int) $order->user_id !== $customer->id) {
                            throw new RuntimeException('Legacy order ownership conflicts with an existing order.');
                        }
                        $orders[$row['id']] = $order;
                        $report['matched']['orders']++;

                        continue;
                    }
                    $packages = Package::query()->where('data->_legacy_wemx->id', (int) $row['package_id'])->get();
                    if ($packages->count() > 1) {
                        throw new RuntimeException('Multiple packages share a legacy identity.');
                    }
                    $package = $packages->first();
                    if (! $package) {
                        $sourcePackage = collect($tables['packages'] ?? [])->firstWhere('id', $row['package_id']);
                        if ($sourcePackage) {
                            $package = Package::query()->where('name', $sourcePackage['name'])->first();
                        }
                    }
                    if ($row['status'] !== 'terminated' || ! $package) {
                        if (($archive[$customer->id]['orders'][$row['id']] ?? null) !== $row) {
                            $archive[$customer->id]['orders'][$row['id']] = $row;
                            $report['archived']['orders']++;
                        }

                        continue;
                    }
                    $price = $this->json($row['price'] ?? null);
                    $period = ($price['type'] ?? null) === 'single' ? 0 : (int) ($price['period'] ?? 0);
                    if ($period < 0 || (! isset($price['price'])) || (($price['type'] ?? null) === 'recurring' && $period === 0)) {
                        throw new RuntimeException('A legacy order has invalid pricing.');
                    }
                    $amount = $this->money($period > 0 ? ($price['renewal_price'] ?? $price['price']) : $price['price']);
                    $data = $this->json($row['data'] ?? null);
                    $data['_legacy_wemx'] = $row;
                    $packagePrice = PackagePrice::query()->where('package_id', $package->id)
                        ->where('data->_legacy_wemx->id', (int) ($price['id'] ?? 0))->first();
                    $order = (new Order)->forceFill(Arr::only($row, ['created_at', 'updated_at', 'due_date', 'last_renewed_at']) + [
                        'user_id' => $customer->id, 'package_id' => $package->id, 'package_price_id' => $packagePrice?->id,
                        'external_id' => null, 'status' => 'terminated', 'auto_balance_renew' => false,
                        'period_in_days' => $period, 'cycle_price' => $period > 0 ? number_format((float) $amount / $period, 8, '.', '') : $amount,
                        'setup_fee' => $this->money($price['setup_fee'] ?? 0), 'upgrade_fee' => $this->money($price['upgrade_fee'] ?? 0), 'data' => $data,
                    ]);
                    $order->save();
                    $orders[$row['id']] = $order;
                    $report['created']['orders']++;
                }

                foreach ($tables['payments'] ?? [] as $row) {
                    $customer = $customerFor($row);
                    if ($row['status'] !== 'paid') {
                        if (($archive[$customer->id]['payments'][$row['id']] ?? null) !== $row) {
                            $archive[$customer->id]['payments'][$row['id']] = $row;
                            $report['archived']['payments']++;
                        }

                        continue;
                    }
                    $amount = $this->money($row['amount']);
                    $matches = Payment::query()->where('data->_legacy_wemx->id', (string) $row['id'])->get();
                    if ($matches->isEmpty() && ! $this->missing($row['transaction_id'] ?? null)) {
                        $matches = Payment::query()->where('transaction_id', $row['transaction_id'])->get();
                    }
                    if ($matches->count() > 1) {
                        throw new RuntimeException('Multiple payments match a legacy payment.');
                    }
                    if ($payment = $matches->first()) {
                        if ((int) $payment->user_id !== $customer->id || $payment->currency !== $row['currency']
                            || $this->decimal((string) $payment->total) !== $this->decimal($amount) || $payment->status !== 'paid') {
                            throw new RuntimeException('Legacy payment conflicts with an existing payment.');
                        }
                        if ($payment->data['_legacy_wemx']['id'] ?? null) {
                            if ((string) $payment->data['_legacy_wemx']['id'] !== (string) $row['id']) {
                                throw new RuntimeException('Legacy payments share an existing transaction identity.');
                            }
                        } else {
                            $archive[$customer->id]['matched_payments'][$row['id']] = $row;
                        }
                        $report['matched']['payments']++;

                        continue;
                    }
                    $gateway = $this->json($row['gateway'] ?? null);
                    $gatewayId = ($gateway['driver'] ?? null) === 'Balance'
                        ? GatewayConfig::query()->where('extension_identifier', 'gateway-balance')->value('id') : null;
                    $data = $this->json($row['data'] ?? null);
                    $data['_legacy_wemx'] = $row;
                    $order = $orders[$row['order_id'] ?? ''] ?? null;
                    (new Payment)->forceFill(Arr::only($row, ['currency', 'transaction_id', 'created_at', 'updated_at']) + [
                        'user_id' => $customer->id, 'token' => Str::random(32), 'invoice_id' => 'LEGACY-'.$row['id'],
                        'description' => $row['description'] ?: 'Legacy payment', 'status' => 'paid', 'gateway_config_id' => $gatewayId,
                        'subtotal' => $amount, 'total' => $amount, 'earnings' => $amount, 'discount' => 0, 'tax' => 0,
                        'paid_at' => $row['updated_at'] ?? $row['created_at'] ?? null,
                        'payable_type' => $order?->getMorphClass(), 'payable_id' => $order?->id,
                        'handler' => null, 'data' => $data,
                    ])->save();
                    $report['created']['payments']++;
                }

                foreach ($tables['balance_transactions'] ?? [] as $row) {
                    $customer = $customerFor($row);
                    if (! in_array($row['result'] ?? null, ['+', '-', '='], true)) {
                        throw new RuntimeException('A legacy balance transaction has an invalid result.');
                    }
                    $transactionId = $archive[$customer->id]['balance_transaction_ids'][$row['id']] ?? null;
                    if ($transactionId !== null) {
                        $transaction = BalanceTransaction::query()->find($transactionId);
                        if (! $transaction || (int) $transaction->user_id !== $customer->id) {
                            throw new RuntimeException('Legacy balance transaction ownership conflicts.');
                        }
                        $report['matched']['balance_transactions']++;

                        continue;
                    }
                    $attributes = Arr::only($row, ['result', 'description', 'created_at', 'updated_at']) + [
                        'user_id' => $customer->id, 'amount' => $this->money($row['amount'], true),
                        'balance_before_transaction' => $this->money($row['balance_before_transaction'], true),
                    ];
                    $transaction = (new BalanceTransaction)->forceFill($attributes);
                    $transaction->save();
                    $archive[$customer->id]['balance_transaction_ids'][$row['id']] = $transaction->id;
                    $archive[$customer->id]['balance_transactions'][$row['id']] = $row;
                    $report['created']['balance_transactions']++;
                }
                foreach ($customers as $customer) {
                    if ($customer->isDirty()) {
                        $report['contacts_completed']++;
                    }
                    $data = $customer->data ?? [];
                    if ($archive[$customer->id] !== []) {
                        $data['_legacy_customer_data'] = $archive[$customer->id];
                        $customer->data = $data;
                    }
                    if ($customer->isDirty()) {
                        $customer->timestamps = false;
                        $customer->save();
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

    private function missing(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /** @return array<string, mixed> */
    private function json(?string $value): array
    {
        $data = json_decode($value ?? '{}', true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('Invalid legacy customer JSON.');
        }

        return $data;
    }

    private function money(mixed $value, bool $allowNegative = false): string
    {
        $amount = (string) $value;
        if (! preg_match('/^-?\d{1,12}(?:\.\d{1,8})?$/D', $amount) || (! $allowNegative && str_starts_with($amount, '-'))) {
            throw new RuntimeException('Invalid legacy monetary value.');
        }

        return $amount;
    }

    private function decimal(string $value): string
    {
        [$whole, $fraction] = explode('.', $value, 2) + [1 => ''];

        return (ltrim($whole, '0') ?: '0').'.'.str_pad($fraction, 8, '0');
    }
}
