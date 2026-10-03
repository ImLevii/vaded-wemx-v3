<?php

namespace App\Console\Commands;

use App\Services\LegacyCustomerDataImporter;
use App\Services\LegacyWemxDumpReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ImportCustomerData extends Command
{
    protected $signature = 'app:import-customer-data {dump : Path to the full legacy WemX SQL export}
        {--commit : Save missing customer data; otherwise validate and roll back}';

    protected $description = 'Restore missing customer contacts and historical orders, payments and balance transactions';

    public function handle(LegacyWemxDumpReader $reader, LegacyCustomerDataImporter $importer): int
    {
        $path = realpath((string) $this->argument('dump'));
        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            $this->error('The dump must be a readable local SQL export.');

            return self::FAILURE;
        }
        $hash = hash_file('sha256', $path);
        $tables = $reader->read($path);
        if (! hash_equals($hash, hash_file('sha256', $path))) {
            throw new RuntimeException('The source export changed while it was being read.');
        }
        $lock = Cache::lock('backup-user-import', 3600);
        if (! $lock->get()) {
            throw new RuntimeException('A customer import is already running.');
        }
        try {
            $commit = (bool) $this->option('commit');
            $prefix = null;
            if ($commit) {
                if (DB::connection()->getDriverName() !== 'sqlite') {
                    throw new RuntimeException('Automatic pre-import backups currently require SQLite.');
                }
                $directory = storage_path('app/private/legacy-imports');
                File::ensureDirectoryExists($directory);
                $prefix = $directory.'/customer-data-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
                $pdo = DB::connection()->getPdo();
                $pdo->exec('VACUUM INTO '.$pdo->quote($prefix.'.sqlite'));
                $this->info('Database backup: '.$prefix.'.sqlite');
            }
            $report = $importer->import($tables, $commit);
            if ($prefix !== null) {
                File::put($prefix.'.json', json_encode(['source_sha256' => $hash,
                    'source' => $path, 'imported_at' => now()->toIso8601String(), 'report' => $report], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                $this->info('Import receipt: '.$prefix.'.json');
            }
            $this->table(['Dataset', 'Created', 'Matched', 'Archived'], collect(array_unique([
                ...array_keys($report['created']), ...array_keys($report['archived']),
            ]))->map(fn (string $dataset): array => [$dataset, $report['created'][$dataset] ?? 0,
                $report['matched'][$dataset] ?? 0, $report['archived'][$dataset] ?? 0])->all());
            $this->info('Missing contact records completed: '.$report['contacts_completed']);
            $this->info('Active or unmapped orders and nonpaid invoices are retained in customer metadata.');
            $this->info($commit ? 'Customer data import committed.' : 'Dry run passed; all changes rolled back.');

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
