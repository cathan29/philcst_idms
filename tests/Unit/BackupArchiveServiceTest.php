<?php

namespace Tests\Unit;

use App\Services\BackupArchiveService;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class BackupArchiveServiceTest extends TestCase
{
    public function test_sqlite_backup_is_verified_and_excludes_environment_files(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'idms-backup-'.bin2hex(random_bytes(5));
        mkdir($root.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'imports', 0777, true);
        file_put_contents($root.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'imports'.DIRECTORY_SEPARATOR.'students.csv', "student_number\n0001\n");
        file_put_contents($root.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR.'.env', 'APP_KEY=must-not-archive');

        $database = $root.DIRECTORY_SEPARATOR.'source.sqlite';
        $pdo = new \PDO('sqlite:'.$database);
        $pdo->exec('CREATE TABLE students (id INTEGER PRIMARY KEY, student_number TEXT)');
        $pdo->exec("INSERT INTO students (student_number) VALUES ('0001')");
        $pdo = null;

        $archive = $root.DIRECTORY_SEPARATOR.'backup.zip';
        $service = new BackupArchiveService();
        $service->createSqlite($database, $root.DIRECTORY_SEPARATOR.'private', $archive);

        $this->assertFileExists($archive);
        $this->assertTrue($service->verify($archive));

        $zip = new ZipArchive();
        $zip->open($archive);
        $this->assertNotFalse($zip->locateName('database/database.sqlite'));
        $this->assertNotFalse($zip->locateName('private/imports/students.csv'));
        $this->assertFalse($zip->locateName('private/.env'));
        $this->assertNotFalse($zip->locateName('manifest.json'));
        $zip->close();

        $this->removeDirectory($root);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
