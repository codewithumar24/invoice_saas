<?php

namespace App\Console\Commands;

use App\Models\BackupLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;
use ZipArchive;

class BackupDatabase extends Command
{
    protected $signature = 'saas:backup {--full : Also include uploaded files}';

    protected $description = 'Create a database backup (and optionally uploaded files), then delete backups older than the retention period';

    public function handle(): int
    {
        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);
        $type = $this->option('full') ? 'full' : 'database';
        $name = $type.'-'.now()->format('Y-m-d-His');

        try {
            $dump = $this->dumpDatabase($directory, $name);
            $path = $dump;

            if ($this->option('full')) {
                $path = "{$directory}/{$name}.zip";
                $zip = new ZipArchive();
                $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $zip->addFile($dump, basename($dump));

                foreach (File::allFiles(storage_path('app')) as $file) {
                    if (! str_starts_with($file->getPathname(), $directory)) {
                        $zip->addFile($file->getPathname(), 'files/'.$file->getRelativePathname());
                    }
                }

                $zip->close();
                File::delete($dump);
            }

            BackupLog::create(['type' => $type, 'status' => 'success', 'path' => $path, 'size' => filesize($path)]);
            $this->info("Backup created: {$path}");
        } catch (Throwable $exception) {
            BackupLog::create(['type' => $type, 'status' => 'failed', 'message' => mb_substr($exception->getMessage(), 0, 1000)]);
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->prune($directory);

        return self::SUCCESS;
    }

    private function dumpDatabase(string $directory, string $name): string
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if ($config['driver'] === 'sqlite') {
            $path = "{$directory}/{$name}.sqlite";
            File::copy($config['database'], $path);

            return $path;
        }

        // MySQL / MariaDB: needs the mysqldump binary on the server. The password is passed by environment, not on the command line.
        $path = "{$directory}/{$name}.sql";
        $result = Process::env(['MYSQL_PWD' => (string) $config['password']])->timeout(1800)->run([
            'mysqldump', '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'],
            '--single-transaction', '--routines', '--result-file='.$path, $config['database'],
        ]);

        if ($result->failed()) {
            throw new \RuntimeException(trim($result->errorOutput()) ?: 'mysqldump failed');
        }

        return $path;
    }

    private function prune(string $directory): void
    {
        $cutoff = now()->subDays(config('saas.backup.retention_days'))->getTimestamp();

        foreach (File::files($directory) as $file) {
            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }
    }
}
