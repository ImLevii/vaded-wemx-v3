<?php

namespace App\Console\Commands\Cronjobs;

use App\Support\LocalLicense;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ReportLicenseActiveCheckCommand extends Command
{
    protected $signature = 'cronjobs:report-active-check';

    protected $description = '';

    private function licenseReportEndpoint(): string
    {
        return 'https://api-v3.wemx.org/api/v1/licenses/report-check';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(): array
    {
        $appUrlHost = parse_url((string) config('app.url', ''), PHP_URL_HOST);
        $machineHashSource = implode('|', [
            (string) config('app.key', ''),
            (string) base_path(),
            (string) gethostname(),
        ]);

        return [
            'license_key' => trim((string) config('app.license_key', '')),
            'event_type' => 'active_check',
            'domain' => is_string($appUrlHost) ? $appUrlHost : null,
            'install_path' => base_path(),
            'php_version' => PHP_VERSION,
            'app_version' => (string) config('app.version', ''),
            'os_name' => PHP_OS_FAMILY,
            'os_version' => php_uname('r') ?: php_uname('v'),
            'server_software' => PHP_SAPI,
            'environment' => app()->environment(),
            'timezone' => (string) config('app.timezone', 'UTC'),
            'machine_ip_address' => gethostbyname(gethostname()),
        ];
    }

    public function handle(): int
    {
        if (LocalLicense::isBypassed()) {
            $this->info('Skipping active license check in local development mode.');

            return self::SUCCESS;
        }

        if (Cache::has('last_license_check_reported_at')) {
            $this->info('Already reported active check');

            return self::SUCCESS;
        }

        $payload = $this->buildPayload();
        if ($payload['license_key'] === '') {
            $this->warn('Skipping active check report: LICENSE_KEY is empty.');

            return self::SUCCESS;
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(2, 250, throw: false)
                ->post($this->licenseReportEndpoint(), $payload);
        } catch (\Throwable $exception) {
            $this->error('Failed to report active license check: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('Upstream active check report failed with status '.$response->status().'.');

            return self::FAILURE;
        }

        $success = $response->json('success');
        if ($success === false) {
            $message = (string) ($response->json('message') ?: 'Unknown upstream error.');
            $this->error('Upstream rejected active check report: '.$message);

            return self::FAILURE;
        }

        Cache::put('last_license_check_reported_at', now()->toISOString(), now()->addHours(12));
        $this->info('Active license check reported successfully.');

        return self::SUCCESS;
    }
}
