<?php

namespace App\Console\Commands;

use App\Models\ServerConnection;
use App\Services\LegacyWemxDumpReader;
use App\Services\LegacyWemxImporter;
use Extensions\Servers\Pterodactyl\Server;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ImportLegacyWemx extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-legacy-wemx {dump : Path to a legacy MariaDB dump}
        {--connection= : Existing Pterodactyl server connection ID}
        {--commit : Commit the import; otherwise validate and roll back}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import legacy WemX categories, packages and users without replacing existing records';

    /**
     * Execute the console command.
     */
    public function handle(LegacyWemxDumpReader $reader, LegacyWemxImporter $importer): int
    {
        $path = (string) $this->argument('dump');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('The dump must be a readable local file.');

            return self::FAILURE;
        }
        $connection = ServerConnection::findOrFail($this->option('connection'));
        if ($connection->extension_identifier !== 'server-pterodactyl') {
            throw new RuntimeException('The connection must use Pterodactyl.');
        }
        $tables = $reader->read($path);
        $eggNests = [];
        $page = 1;
        do {
            $response = Server::makeRequest($connection->config, '/api/application/nests', 'get', [
                'include' => 'eggs', 'per_page' => 100, 'page' => $page,
            ]);
            foreach ($response['data'] ?? [] as $nest) {
                foreach ($nest['attributes']['relationships']['eggs']['data'] ?? [] as $egg) {
                    $eggNests[(int) $egg['attributes']['id']] = (int) $nest['attributes']['id'];
                }
            }
            $page++;
        } while ($page <= (int) ($response['meta']['pagination']['total_pages'] ?? 1));

        $commit = (bool) $this->option('commit');
        $receiptPath = null;
        if ($commit) {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                throw new RuntimeException('This command requires SQLite for its automatic pre-import backup.');
            }
            $directory = storage_path('app/private/legacy-imports');
            File::ensureDirectoryExists($directory);
            $prefix = $directory.'/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
            $backup = $prefix.'.sqlite';
            DB::connection()->getPdo()->exec('VACUUM INTO '.DB::connection()->getPdo()->quote($backup));
            $receiptPath = $prefix.'.json';
            $this->info('Database backup: '.$backup);
        }

        $report = $importer->import($tables, $connection, $eggNests, $commit);
        if ($receiptPath !== null) {
            File::put($receiptPath, json_encode([
                'source_sha256' => hash_file('sha256', $path), 'connection_id' => $connection->id,
                'imported_at' => now()->toIso8601String(), 'report' => $report,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $this->info('Import receipt: '.$receiptPath);
        }
        $this->info($commit ? 'Import committed.' : 'Dry run passed; all changes rolled back.');
        $this->table(['Records', 'Created', 'Existing retained'], collect($report['created'])
            ->map(fn (int $count, string $table): array => [$table, $count, $report['matched'][$table] ?? 0])->values()->all());

        return self::SUCCESS;
    }
}
