<?php

namespace App\Services;

use App\Handlers\OrderRenewalHandler;
use App\Handlers\OrderUpgradeHandler;
use App\Models\Order;
use App\Models\OrderSubscription;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderUpgradeService
{
    /** @var array<string, Collection<int, PackagePrice>> */
    private array $candidates = [];

    /** @var Collection<int, int>|null */
    private ?Collection $billingPriceIds = null;

    /** @var Collection<int, int>|null */
    private ?Collection $subscribedOrderIds = null;

    /** @var Collection<int, int>|null */
    private ?Collection $renewalOrderIds = null;

    /** @var Collection<string, int>|null */
    private ?Collection $packageCounts = null;

    /** @param Collection<int, Order> $orders */
    public function upgradeableOrderIds(Collection $orders): array
    {
        $orders->loadMissing(['package.serverConnection.server', 'prices']);
        $this->billingPriceIds = PackagePrice::query()->whereIn('id', $orders->pluck('package_price_id'))->get()->mapWithKeys(fn (PackagePrice $price): array => [$price->id => $price->package_id]);
        $this->subscribedOrderIds = Subscription::query()->where('subscribable_type', (new Order)->getMorphClass())
            ->whereIn('subscribable_id', $orders->pluck('id'))->whereNull('cancelled_at')->whereIn('status', ['active', 'pending'])->pluck('subscribable_id')
            ->merge(OrderSubscription::query()->whereIn('order_id', $orders->pluck('id'))->whereIn('status', ['active', 'pending'])->pluck('order_id'));
        $this->renewalOrderIds = Payment::query()->where('payable_type', (new Order)->getMorphClass())->whereIn('payable_id', $orders->pluck('id'))
            ->where('handler', OrderRenewalHandler::class)->where('status', 'unpaid')->pluck('payable_id');
        $this->packageCounts = Order::query()->whereIn('user_id', $orders->pluck('user_id'))->where('status', '!=', 'terminated')
            ->selectRaw('user_id, package_id, COUNT(*) as service_count')->groupBy('user_id', 'package_id')->get()
            ->mapWithKeys(fn (Order $order): array => [$order->user_id.':'.$order->package_id => (int) $order->service_count]);

        return $orders->filter(fn (Order $order): bool => $this->options($order)->isNotEmpty())->pluck('id')->all();
    }

    public function unavailableReason(Order $order): ?string
    {
        $order->loadMissing(['package.serverConnection.server', 'prices']);
        if (! $order->isActive() || ! ctype_digit((string) $order->external_id) || (int) $order->external_id < 1 || ! $order->isRecurring() || ! $order->due_date?->isFuture()) {
            return 'Only active, provisioned services with time remaining on a recurring billing cycle can be upgraded.';
        }
        if ($order->termination_requested_at || $order->requiresBillingReview()) {
            return 'Upgrades are unavailable while termination or billing review is pending.';
        }
        $connection = $order->package->serverConnection;
        if ($connection?->extension_identifier !== 'server-pterodactyl' || ! $connection->is_active || $connection->prevent_purchasing || $connection->server?->status !== 'enabled') {
            return 'Self-service upgrades are unavailable for this service. Please contact support.';
        }
        $hasOriginalPrice = $this->billingPriceIds !== null
            ? (int) $this->billingPriceIds->get($order->package_price_id) === (int) $order->package_id
            : PackagePrice::query()->whereKey($order->package_price_id)->where('package_id', $order->package_id)->exists();
        if (! $hasOriginalPrice) {
            return 'The original billing plan needs to be restored before this service can be upgraded. Please contact support.';
        }
        $hasSubscription = $this->subscribedOrderIds !== null ? $this->subscribedOrderIds->contains($order->id)
            : Subscription::query()->whereMorphedTo('subscribable', $order)->whereNull('cancelled_at')->whereIn('status', ['active', 'pending'])->exists()
                || $order->orderSubscriptions()->whereIn('status', ['active', 'pending'])->exists();
        if ($hasSubscription) {
            return 'Cancel the recurring subscription before upgrading. You can set it up again at the new price afterwards.';
        }

        $hasRenewalInvoice = $this->renewalOrderIds !== null ? $this->renewalOrderIds->contains($order->id)
            : $order->payments()->where('handler', OrderRenewalHandler::class)->where('status', 'unpaid')->exists();
        if ($hasRenewalInvoice) {
            return 'Complete the outstanding renewal invoice before upgrading so your billing period and upgrade price are correct.';
        }

        return null;
    }

    /** @return Collection<int, array{price: PackagePrice, recurring: float, prorated: float, fee: float, total: float, token: string, resources: array<string, string>}> */
    public function options(Order $order): Collection
    {
        if ($this->unavailableReason($order)) {
            return collect();
        }
        $key = $order->package->connection_id.':'.$order->package->category_id.':'.$order->period_in_days;
        $prices = $this->candidates[$key] ??= PackagePrice::query()->with('package')
            ->where('is_active', true)->where('period_in_days', $order->period_in_days)
            ->whereHas('package', fn ($query) => $query->where('connection_id', $order->package->connection_id)
                ->where('category_id', $order->package->category_id)->where('status', 'active'))
            ->orderBy('price')->get();

        return $prices->filter(fn (PackagePrice $price): bool => $this->compatible($order, $price))
            ->map(fn (PackagePrice $price): array => $this->quote($order, $price))->values();
    }

    public function compatible(Order $order, PackagePrice $price): bool
    {
        $package = $price->package;
        if (! $price->is_active || $package->status !== 'active' || (int) $package->id === (int) $order->package_id
            || (int) $package->connection_id !== (int) $order->package->connection_id
            || (int) $package->category_id !== (int) $order->package->category_id
            || (int) $price->period_in_days !== (int) $order->period_in_days
            || (float) $price->getDailyPrice() <= (float) $order->cycle_price || (float) $price->upgrade_fee < 0
            || ($package->global_quantity !== -1 && $package->global_quantity <= 0)) {
            return false;
        }
        if ($package->client_quantity !== -1) {
            $serviceCount = $this->packageCounts !== null ? $this->packageCounts->get($order->user_id.':'.$package->id, 0)
                : Order::query()->where('user_id', $order->user_id)->where('package_id', $package->id)->where('status', '!=', 'terminated')->count();
            if ($serviceCount >= $package->client_quantity) {
                return false;
            }
        }
        foreach (['nest_id', 'egg_id', 'docker_image', 'startup', 'cpu_pinning', 'block_io_weight'] as $key) {
            if ((string) $order->package->data($key) !== (string) $package->data($key)) {
                return false;
            }
        }
        $increased = false;
        foreach (['memory_limit', 'disk_limit', 'cpu_limit', 'swap_limit', 'database_limit', 'allocation_limit', 'backup_limit'] as $key) {
            $oldValue = (float) $order->option($key, 0);
            $newValue = (float) ($order->prices->firstWhere('key', $key)?->value ?? $package->data($key, 0));
            if (in_array($key, ['memory_limit', 'disk_limit', 'cpu_limit'], true)) {
                $oldValue = $oldValue <= 0 ? INF : $oldValue;
                $newValue = $newValue <= 0 ? INF : $newValue;
            }
            if ($key === 'swap_limit') {
                $oldValue = $oldValue < 0 ? INF : $oldValue;
                $newValue = $newValue < 0 ? INF : $newValue;
            }
            if ($newValue < $oldValue) {
                return false;
            }
            $increased = $increased || $newValue > $oldValue;
        }

        return $increased;
    }

    /** @return array{price: PackagePrice, recurring: float, prorated: float, fee: float, total: float, token: string, resources: array<string, string>} */
    public function quote(Order $order, PackagePrice $price): array
    {
        $remainingDays = max(0, now()->diffInSeconds($order->due_date, false) / 86400);
        $prorated = round(((float) $price->getDailyPrice() - (float) $order->cycle_price) * $remainingDays, 2);
        $fee = round((float) $price->upgrade_fee, 2);
        $total = round($prorated + $fee, 2);
        $resources = [];
        foreach (['memory_limit' => 'Memory', 'disk_limit' => 'Disk', 'cpu_limit' => 'CPU'] as $key => $label) {
            $value = (float) ($order->prices->firstWhere('key', $key)?->value ?? $price->package->data($key, 0));
            $resources[$label] = $value <= 0 ? 'Unlimited' : $value.($key === 'cpu_limit' ? '%' : ' GB');
        }

        return ['price' => $price,
            'recurring' => round((float) $price->price + $order->prices->where('is_active', true)->sum('cycle_price') * $order->period_in_days, 2),
            'prorated' => $prorated, 'fee' => $fee, 'total' => $total,
            'token' => hash('sha256', $this->fingerprint($order).$this->targetFingerprint($price).$total), 'resources' => $resources];
    }

    public function checkout(Order $order, int $priceId, string $quoteToken): Payment
    {
        abort_unless((int) $order->user_id === auth()->id(), 403);

        return DB::transaction(function () use ($order, $priceId, $quoteToken): Payment {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless((int) $order->user_id === auth()->id(), 403);
            $this->assertAvailable($order);
            $price = PackagePrice::query()->with('package')->findOrFail($priceId);
            $this->assertCompatible($order, $price);
            $existing = $this->pendingPayment($order);
            if ($existing) {
                if ((int) $existing->data('package_price_id') === $priceId && $existing->data('source_fingerprint') === $this->fingerprint($order)
                    && $existing->data('target_fingerprint') === $this->targetFingerprint($price)) {
                    return $existing;
                }
                if ($existing->isPaid()) {
                    throw ValidationException::withMessages(['package_price_id' => 'A paid upgrade still needs to be completed. Retry it or contact support before purchasing another upgrade.']);
                }
                $existing->update(['data' => array_merge($existing->data, ['upgrade_superseded_at' => now()->toIso8601String()])]);
            }
            $quote = $this->quote($order, $price);
            if (! hash_equals($quote['token'], $quoteToken)) {
                throw ValidationException::withMessages(['package_price_id' => 'The upgrade quote has changed. Refresh this page and review the latest price.']);
            }

            return $order->payments()->create([
                'user_id' => $order->user_id, 'description' => "Upgrade service #{$order->id} to {$price->package->name}",
                'subtotal' => $quote['total'], 'currency' => baseCurrency(), 'handler' => OrderUpgradeHandler::class,
                'success_url' => route('orders.upgrade', $order), 'cancel_url' => route('orders.upgrade', $order),
                'data' => ['package_price_id' => $price->id, 'source_fingerprint' => $this->fingerprint($order),
                    'target_fingerprint' => $this->targetFingerprint($price), 'prorated' => $quote['prorated'], 'upgrade_fee' => $quote['fee']],
            ]);
        });
    }

    public function pendingPayment(Order $order): ?Payment
    {
        return $order->payments()->where('handler', OrderUpgradeHandler::class)->whereIn('status', ['unpaid', 'paid'])
            ->latest()->get()->first(fn (Payment $payment): bool => ! $payment->data('upgrade_applied_at') && ! $payment->data('upgrade_superseded_at'));
    }

    public function assertPayable(Payment $payment): void
    {
        $order = $payment->payable;
        if (! $order instanceof Order || $payment->data('upgrade_superseded_at') || (int) $payment->user_id !== (int) $order->user_id) {
            throw ValidationException::withMessages(['payment_id' => 'This upgrade invoice is no longer valid. Return to your service to review a new upgrade.']);
        }
        $this->assertAvailable($order);
        $price = PackagePrice::query()->with('package')->findOrFail($payment->data('package_price_id'));
        $this->assertCompatible($order, $price);
        if ($payment->data('source_fingerprint') !== $this->fingerprint($order) || $payment->data('target_fingerprint') !== $this->targetFingerprint($price)) {
            throw ValidationException::withMessages(['payment_id' => 'The service or plan changed after checkout. Return to your service to review a new upgrade.']);
        }
    }

    public function apply(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $order = Order::query()->lockForUpdate()->findOrFail($payment->payable_id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->data('upgrade_applied_at')) {
                return;
            }
            if (! $payment->isPaid() || $payment->handler !== OrderUpgradeHandler::class || $payment->payable_type !== $order->getMorphClass()
                || (int) $payment->user_id !== (int) $order->user_id || $payment->data('upgrade_superseded_at')) {
                throw ValidationException::withMessages(['package_price_id' => 'This payment cannot be used to upgrade this service. Please contact support.']);
            }
            $this->assertAvailable($order);
            $price = PackagePrice::query()->lockForUpdate()->findOrFail($payment->data('package_price_id'));
            $packages = Package::query()->whereIn('id', [$order->package_id, $price->package_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $package = $packages->get($price->package_id);
            $order->setRelation('package', $packages->get($order->package_id));
            $price->setRelation('package', $package);
            $this->assertCompatible($order, $price);
            if ($payment->data('source_fingerprint') !== $this->fingerprint($order) || $payment->data('target_fingerprint') !== $this->targetFingerprint($price)) {
                throw ValidationException::withMessages(['package_price_id' => 'The service or plan changed after checkout. Please contact support to resolve this paid upgrade.']);
            }
            $oldPrice = PackagePrice::query()->findOrFail($order->package_price_id);
            $order->package->serverConnection->server->functions()->upgradeOrDowngrade($order, $oldPrice, $price, $order->package->serverConnection);
            if ($package->global_quantity > 0) {
                $package->decrement('global_quantity');
            }
            if ($order->package->global_quantity !== -1) {
                $order->package->increment('global_quantity');
            }
            $order->update(['package_id' => $price->package_id, 'package_price_id' => $price->id,
                'cycle_price' => $price->getDailyPrice(), 'upgrade_fee' => $price->upgrade_fee]);
            $order->log(['user_id' => $order->user_id, 'action' => 'order_upgraded',
                'description' => "Customer upgraded to {$package->name} using payment #{$payment->id}."]);
            $payment->update(['data' => array_merge($payment->data, ['upgrade_applied_at' => now()->toIso8601String(), 'upgrade_failed' => false])]);
        });
    }

    private function assertAvailable(Order $order): void
    {
        if ($reason = $this->unavailableReason($order)) {
            throw ValidationException::withMessages(['package_price_id' => $reason]);
        }
    }

    private function assertCompatible(Order $order, PackagePrice $price): void
    {
        if (! $this->compatible($order, $price)) {
            throw ValidationException::withMessages(['package_price_id' => 'Choose an available larger plan with the same service type and billing cycle.']);
        }
    }

    private function fingerprint(Order $order): string
    {
        return hash('sha256', json_encode([$order->user_id, $order->package_id, $order->package_price_id, $order->external_id,
            $order->cycle_price, $order->period_in_days, $order->due_date?->toIso8601String(), $order->package->data,
            $order->prices->sortBy('id')->map->only(['id', 'key', 'value', 'cycle_price', 'upgrade_fee', 'is_active'])->values()->all()], JSON_THROW_ON_ERROR));
    }

    private function targetFingerprint(PackagePrice $price): string
    {
        return hash('sha256', json_encode([$price->id, $price->price, $price->upgrade_fee, $price->period_in_days, $price->package_id,
            $price->package->connection_id, $price->package->category_id, $price->package->data], JSON_THROW_ON_ERROR));
    }
}
