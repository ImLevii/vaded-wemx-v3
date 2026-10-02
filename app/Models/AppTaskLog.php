<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppTaskLog extends Model
{
    protected $table = 'app_task_logs';

    protected $fillable = [
        'task',
        'status',
        'message',
        'show',
        'data',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public static function isSchedularRunning(): bool
    {
        return AppTaskLog::where('task', 'scheduler:heartbeat')
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMinutes(3))
            ->exists();
    }

    public static function lastSchedularRun(): string
    {
        $lastRun = AppTaskLog::where('task', 'scheduler:heartbeat')
            ->where('status', 'completed')
            ->latest()
            ->first();

        return $lastRun ? $lastRun->created_at->diffForHumans() : 'Never';
    }

    public static function isQueueWorkerRunning(): bool
    {
        if (config('queue.worker.secret')) {
            $lastCompletedAt = Cache::store('database')->get('queue:worker:last_completed_at');

            return is_numeric($lastCompletedAt) && (int) $lastCompletedAt >= now()->subMinutes(15)->timestamp;
        }

        // retrieve latest job from the jobs
        $latestJob = \DB::table('jobs')
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $latestJob) {
            return true; // No jobs found, assume worker is running
        }

        // check if this job is older than 10 minutes
        $jobCreatedAt = Carbon::createFromTimestamp($latestJob->created_at);

        return $jobCreatedAt->greaterThanOrEqualTo(now()->subMinutes(5));
    }

    public static function createHeartbeat()
    {
        return AppTaskLog::updateOrCreate(
            [
                'task' => 'scheduler:heartbeat',
            ],
            [
                'status' => 'completed',
                'message' => 'The scheduler is running as expected.',
                'show' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
