<?php

namespace App\Console\Commands;

use App\Services\BackupUserImporter;
use App\Services\LegacyWemxDumpReader;
use App\Services\UserImportBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ImportUserBackup extends Command
{
    protected $signature = 'app:import-user-backup {directory : Root directory of the vaded-dbs backup}
        {--commit : Save users; otherwise validate and roll back}';

    protected $description = 'Import WemX and Pterodactyl users from a local backup while preserving existing accounts';

    public function handle(LegacyWemxDumpReader $reader, BackupUserImporter $importer, UserImportBackup $backup): int
    {
        $root = realpath((string) $this->argument('directory'));
        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('The backup directory must exist.');
        }
        $files = ['wemx' => $root.'/wemx/wemx/users.sql', 'pterodactyl' => $root.'/pterodactyl/users.sql',
            'two_factor' => $root.'/wemx/wemx/user_2fa.sql'];
        if (! is_readable($root.'/SHA256SUMS')) {
            throw new RuntimeException('The backup checksum manifest must be readable.');
        }
        $checksums = [];
        foreach (file($root.'/SHA256SUMS', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/^([a-f0-9]{64})\s+\.\/(.+)$/', $line, $match)) {
                $checksums[$match[2]] = $match[1];
            }
        }
        $hashes = [];
        foreach ($files as $source => $path) {
            if (! is_file($path) || ! is_readable($path)) {
                throw new RuntimeException("Missing readable {$source} user backup.");
            }
            $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
            $hashes[$source] = hash_file('sha256', $path);
            if (! isset($checksums[$relative]) || ! hash_equals($checksums[$relative], $hashes[$source])) {
                throw new RuntimeException("Backup checksum verification failed for {$source}.");
            }
        }
        $wemx = $reader->read($files['wemx']);
        $wemx['user_2fa'] = $reader->read($files['two_factor'])['user_2fa'];
        $panel = $reader->read($files['pterodactyl']);
        $lock = Cache::lock('backup-user-import', 3600);
        if (! $lock->get()) {
            throw new RuntimeException('A user backup import is already running.');
        }
        try {
            $commit = (bool) $this->option('commit');
            $prefix = null;
            if ($commit) {
                $directory = storage_path('app/private/legacy-imports');
                File::ensureDirectoryExists($directory);
                $prefix = $directory.'/users-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
                $this->info('Pre-import backup: '.$backup->create($prefix));
            }
            $report = $importer->import($wemx, $panel, $commit);
            if ($prefix !== null) {
                File::put($prefix.'.json', json_encode(['source_sha256' => $hashes,
                    'imported_at' => now()->toIso8601String(), 'report' => $report], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                $this->info('Import receipt: '.$prefix.'.json');
            }
            $this->table(['Source', 'Created', 'Matched', 'Identity links added'], collect($report['created'])
                ->map(fn (int $count, string $source): array => [$source, $count, $report['matched'][$source], $report['linked'][$source]])->values()->all());
            $this->info('Existing passwords retained despite differing source hashes: '.$report['existing_passwords_retained']);
            $this->info('Usernames adjusted to avoid conflicts: '.$report['usernames_renamed']);
            $this->info($commit ? 'User import committed.' : 'Dry run passed; all changes rolled back.');

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
