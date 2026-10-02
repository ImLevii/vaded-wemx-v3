<?php

namespace Tests\Feature;

use App\Jobs\InstallerQueueProbeJob;
use App\Models\AppTaskLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class QueueWorkerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'database',
            'queue.worker.secret' => 'test-worker-secret',
        ]);
    }

    public function test_missing_and_incorrect_tokens_cannot_process_jobs(): void
    {
        InstallerQueueProbeJob::dispatch('queue-worker-probe');

        $this->postJson('/internal/queue/work')->assertUnauthorized();
        $this->withToken('incorrect')->postJson('/internal/queue/work')->assertUnauthorized();

        $this->assertSame(1, Queue::connection('database')->size());
        $this->assertFalse(Cache::has('queue-worker-probe'));
    }

    public function test_unconfigured_secret_disables_the_endpoint(): void
    {
        config(['queue.worker.secret' => '']);

        $this->postJson('/internal/queue/work')->assertUnauthorized();
    }

    public function test_get_requests_cannot_run_jobs(): void
    {
        $this->withToken('test-worker-secret')->getJson('/internal/queue/work')->assertStatus(405);
    }

    public function test_authenticated_request_processes_real_database_jobs_and_records_heartbeat(): void
    {
        InstallerQueueProbeJob::dispatch('queue-worker-probe');

        $this->withToken('test-worker-secret')->postJson('/internal/queue/work')
            ->assertOk()->assertExactJson(['status' => 'completed']);

        $this->assertSame(0, Queue::connection('database')->size());
        $this->assertTrue(Cache::has('queue-worker-probe'));
        $this->assertTrue(AppTaskLog::isQueueWorkerRunning());
        $this->assertTrue(Cache::store('database')->lock('queue:worker:lock', 90)->get());
    }

    public function test_empty_queue_returns_success(): void
    {
        $this->withToken('test-worker-secret')->postJson('/internal/queue/work')
            ->assertOk()->assertExactJson(['status' => 'completed']);
    }

    public function test_each_invocation_limits_the_number_of_jobs(): void
    {
        for ($index = 0; $index < 11; $index++) {
            InstallerQueueProbeJob::dispatch('queue-worker-probe-'.$index);
        }

        $this->withToken('test-worker-secret')->postJson('/internal/queue/work')->assertOk();

        $this->assertSame(1, Queue::connection('database')->size());
        $this->assertFalse(Cache::has('queue-worker-probe-10'));
    }

    public function test_delayed_jobs_remain_queued_until_they_are_due(): void
    {
        InstallerQueueProbeJob::dispatch('queue-worker-probe')->delay(now()->addMinutes(10));

        $this->withToken('test-worker-secret')->postJson('/internal/queue/work')->assertOk();

        $this->assertSame(1, Queue::connection('database')->size());
        $this->assertFalse(Cache::has('queue-worker-probe'));
    }

    public function test_overlapping_request_skips_processing(): void
    {
        $lock = Cache::store('database')->lock('queue:worker:lock', 90);
        $this->assertTrue($lock->get());
        InstallerQueueProbeJob::dispatch('queue-worker-probe');

        $this->withToken('test-worker-secret')->postJson('/internal/queue/work')
            ->assertOk()->assertExactJson(['status' => 'busy']);

        $this->assertSame(1, Queue::connection('database')->size());
        $this->assertFalse(AppTaskLog::isQueueWorkerRunning());
        $lock->release();
    }

    public function test_worker_error_releases_lock_without_exposing_details(): void
    {
        Artisan::shouldReceive('call')->once()->andThrow(new RuntimeException('private connection details'));

        $this->withToken('test-worker-secret')->postJson('/internal/queue/work')
            ->assertStatus(500)->assertExactJson(['status' => 'error']);

        $this->assertFalse(AppTaskLog::isQueueWorkerRunning());
        $this->assertTrue(Cache::store('database')->lock('queue:worker:lock', 90)->get());
    }

    public function test_nonzero_worker_exit_does_not_record_heartbeat(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(1);

        $this->withToken('test-worker-secret')->postJson('/internal/queue/work')
            ->assertStatus(500)->assertExactJson(['status' => 'error']);

        $this->assertFalse(AppTaskLog::isQueueWorkerRunning());
        $this->assertTrue(Cache::store('database')->lock('queue:worker:lock', 90)->get());
    }

    public function test_failing_jobs_retry_with_backoff_and_are_eventually_recorded(): void
    {
        Queue::push(new QueueWorkerFailureJob);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->withToken('test-worker-secret')->postJson('/internal/queue/work')
                ->assertOk()->assertExactJson(['status' => 'completed']);

            if ($attempt < 3) {
                $job = DB::table('jobs')->first();
                $this->assertSame($attempt, (int) $job->attempts);
                $this->assertGreaterThan(now()->timestamp, (int) $job->available_at);
                $this->travel(11)->seconds();
            }
        }

        $this->assertSame(0, Queue::connection('database')->size());
        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_heartbeat_expires_when_scheduled_processing_stops(): void
    {
        Cache::store('database')->put('queue:worker:last_completed_at', now()->subMinutes(16)->timestamp, 3600);

        $this->assertFalse(AppTaskLog::isQueueWorkerRunning());
    }

    public function test_other_queue_drivers_are_rejected(): void
    {
        config(['queue.default' => 'sync']);

        $this->withToken('test-worker-secret')->postJson('/internal/queue/work')->assertStatus(409);
    }

    public function test_existing_worker_check_handles_unix_job_timestamps(): void
    {
        config(['queue.worker.secret' => null]);
        InstallerQueueProbeJob::dispatch('queue-worker-probe');
        DB::table('jobs')->update(['created_at' => now()->subMinutes(10)->timestamp]);

        $this->assertFalse(AppTaskLog::isQueueWorkerRunning());
    }
}

class QueueWorkerFailureJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        throw new RuntimeException('private job failure details');
    }
}
