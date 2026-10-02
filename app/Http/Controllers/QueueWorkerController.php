<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

class QueueWorkerController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('queue.worker.secret');

        abort_unless(is_string($secret) && $secret !== '' && hash_equals($secret, $request->bearerToken() ?? ''), 401);
        abort_unless(config('queue.default') === 'database', 409, 'The database queue must be enabled.');

        $cache = Cache::store('database');
        $lock = $cache->lock('queue:worker:lock', 90);

        if (! $lock->get()) {
            return response()->json(['status' => 'busy']);
        }

        try {
            $exitCode = Artisan::call('queue:work', [
                'connection' => 'database',
                '--stop-when-empty' => true,
                '--max-jobs' => 10,
                '--max-time' => 20,
                '--timeout' => 25,
                '--sleep' => 0,
                '--tries' => 3,
                '--backoff' => 10,
                '--no-interaction' => true,
            ]);

            if ($exitCode !== 0) {
                return response()->json(['status' => 'error'], 500);
            }

            $cache->put('queue:worker:last_completed_at', now()->timestamp, now()->addMinutes(15));

            return response()->json(['status' => 'completed']);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['status' => 'error'], 500);
        } finally {
            $lock->release();
        }
    }
}
