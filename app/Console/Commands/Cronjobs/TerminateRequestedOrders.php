<?php

namespace App\Console\Commands\Cronjobs;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TerminateRequestedOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cronjobs:orders:terminate-requested';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Terminate client services at their requested termination date';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Order::query()->whereNotNull('termination_requested_at')->where('terminate_at', '<=', now())
            ->where('status', '!=', 'terminated')
            ->whereDoesntHave('exceptions', fn (Builder $query): Builder => $query->where('action', 'terminate')->whereNull('resolved_at'))
            ->chunkById(100, function (Collection $orders): void {
                foreach ($orders as $order) {
                    $order->terminateServer();
                }
            });

        return self::SUCCESS;
    }
}
