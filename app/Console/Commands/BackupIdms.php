<?php

namespace App\Console\Commands;

use App\Services\BackupArchiveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupIdms extends Command
{
    protected $signature = 'idms:backup {--output= : Absolute or project-relative ZIP path}';

    protected $description = 'Create and verify a PHILCST IDMS database and private-file backup';

    public function handle(BackupArchiveService $backups): int
    {
        $driver = DB::connection()->getDriverName();
        $output = $this->option('output')
            ?: storage_path('app/private/backups/idms-'.now()->format('Ymd-His').'.zip');
        $output = $this->absolutePath($output);

        try {
            if ($driver === 'sqlite') {
                $backups->createSqlite(
                    DB::connection()->getDatabaseName(),
                    storage_path('app/private'),
                    $output,
                );
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $this->backupMysql($backups, $output);
            } else {
                throw new RuntimeException("Backups for the {$driver} driver require a documented database-native dump.");
            }
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Verified backup: '.$output);
        $this->warn('The .env file and credentials are intentionally excluded. Store them in the approved secret manager.');

        return self::SUCCESS;
    }

    private function backupMysql(BackupArchiveService $backups, string $output): void
    {
        $config = config('database.connections.'.config('database.default'));
        $binary = env('IDMS_MYSQLDUMP_BINARY');
        if (! $binary) {
            $candidate = dirname(base_path(), 2).DIRECTORY_SEPARATOR.'mysql'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'mysqldump.exe';
            $binary = is_file($candidate) ? $candidate : 'mysqldump';
        }

        $working = storage_path('app/private/backups/.working-'.bin2hex(random_bytes(6)));
        if (! is_dir($working) && ! mkdir($working, 0700, true) && ! is_dir($working)) {
            throw new RuntimeException('Could not create the private backup working directory.');
        }

        $defaults = $working.DIRECTORY_SEPARATOR.'mysql-client.cnf';
        $dump = $working.DIRECTORY_SEPARATOR.'database.sql';
        $escape = fn (mixed $value) => str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);
        file_put_contents($defaults, "[client]\nhost=\"{$escape($config['host'])}\"\nport=\"{$escape($config['port'])}\"\nuser=\"{$escape($config['username'])}\"\npassword=\"{$escape($config['password'])}\"\n");
        @chmod($defaults, 0600);

        try {
            $process = new Process([
                $binary,
                '--defaults-extra-file='.$defaults,
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--result-file='.$dump,
                (string) $config['database'],
            ]);
            $process->setTimeout(300);
            $process->mustRun();

            $backups->createFromDatabaseFile(
                $dump,
                'database/database.sql',
                storage_path('app/private'),
                $output,
            );
        } finally {
            if (is_file($defaults)) {
                unlink($defaults);
            }
            if (is_file($dump)) {
                unlink($dump);
            }
            if (is_dir($working)) {
                rmdir($working);
            }
        }
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }
}
