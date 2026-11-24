<?php

namespace App\Services;

use PDO;
use RuntimeException;
use ZipArchive;

class BackupArchiveService
{
    public function createSqlite(string $database, string $privateRoot, string $archive): string
    {
        if (! is_file($database)) {
            throw new RuntimeException('The configured SQLite database file does not exist.');
        }

        $temporary = dirname($archive).DIRECTORY_SEPARATOR.'idms-snapshot-'.bin2hex(random_bytes(6)).'.sqlite';
        $this->ensureDirectory(dirname($temporary));

        $pdo = new PDO('sqlite:'.$database);
        $quoted = str_replace("'", "''", $temporary);
        $pdo->exec("VACUUM INTO '{$quoted}'");
        $pdo = null;

        try {
            return $this->createFromDatabaseFile($temporary, 'database/database.sqlite', $privateRoot, $archive);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function createFromDatabaseFile(
        string $databaseFile,
        string $databaseArchiveName,
        string $privateRoot,
        string $archive,
    ): string {
        if (! is_file($databaseFile)) {
            throw new RuntimeException('The database backup was not created.');
        }

        $this->ensureDirectory(dirname($archive));
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The backup archive could not be opened.');
        }

        $entries = [];
        $this->addFile($zip, $databaseFile, $databaseArchiveName, $entries);

        if (is_dir($privateRoot)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($privateRoot, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($privateRoot) + 1));
                if ($this->excluded($relative)) {
                    continue;
                }

                $this->addFile($zip, $file->getPathname(), 'private/'.$relative, $entries);
            }
        }

        $manifest = [
            'format' => 1,
            'application' => 'PHILCST ID Monitoring System',
            'created_at' => now()->toIso8601String(),
            'database' => $databaseArchiveName,
            'files' => $entries,
            'restore_policy' => 'Restore only to an isolated environment after verification.',
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $zip->close();

        if (! $this->verify($archive)) {
            @unlink($archive);
            throw new RuntimeException('Backup verification failed; the incomplete archive was removed.');
        }

        return $archive;
    }

    public function verify(string $archive): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CHECKCONS) !== true) {
            return false;
        }

        try {
            $manifestRaw = $zip->getFromName('manifest.json');
            if (! is_string($manifestRaw)) {
                return false;
            }
            $manifest = json_decode($manifestRaw, true, flags: JSON_THROW_ON_ERROR);
            if (($manifest['format'] ?? null) !== 1 || ! is_array($manifest['files'] ?? null)) {
                return false;
            }

            foreach ($manifest['files'] as $name => $hash) {
                $contents = $zip->getFromName($name);
                if (! is_string($contents) || ! hash_equals($hash, hash('sha256', $contents))) {
                    return false;
                }
            }

            return isset($manifest['database']) && $zip->locateName($manifest['database']) !== false;
        } catch (\JsonException) {
            return false;
        } finally {
            $zip->close();
        }
    }

    private function addFile(ZipArchive $zip, string $source, string $name, array &$entries): void
    {
        if (! $zip->addFile($source, $name)) {
            throw new RuntimeException("Could not add {$name} to the backup.");
        }
        $entries[$name] = hash_file('sha256', $source);
    }

    private function excluded(string $relative): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $relative));

        return basename($normalized) === '.env'
            || str_starts_with($normalized, 'backups/')
            || str_contains($normalized, '/backups/');
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The backup directory could not be created.');
        }
    }
}
