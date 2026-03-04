<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--disk=local : Storage disk to use}';

    protected $description = 'Create a full database backup using mysqldump';

    public function handle(): int
    {
        $this->info('Starting database backup...');

        $config = config('database.connections.' . config('database.default'));
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $filename = "backup_{$timestamp}.sql.gz";
        $tempPath = storage_path("app/backups/{$filename}");

        // Ensure backup directory exists
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        // Build mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers %s | gzip > %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['port'] ?? '3306'),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['database']),
            escapeshellarg($tempPath)
        );

        $exitCode = 0;
        $output = [];
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('Backup failed: ' . implode("\n", $output));

            return Command::FAILURE;
        }

        $fileSize = filesize($tempPath);
        $this->info("Backup created: {$filename} (" . $this->formatSize($fileSize) . ')');

        // Store backup record
        DB::table('backups')->insert([
            'filename'   => $filename,
            'disk'       => $this->option('disk'),
            'path'       => "backups/{$filename}",
            'size_bytes' => $fileSize,
            'created_at' => now(),
        ]);

        // Copy to configured disk if not local
        $disk = $this->option('disk');
        if ($disk !== 'local' && Storage::disk($disk)->exists('')) {
            Storage::disk($disk)->put("backups/{$filename}", file_get_contents($tempPath));
            $this->info("Backup copied to {$disk} disk.");
        }

        // Clean old backups (retention policy: keep last 30 days)
        $this->cleanOldBackups();

        $this->info('Database backup completed successfully.');

        return Command::SUCCESS;
    }

    private function cleanOldBackups(): void
    {
        $retentionDays = (int) config('backup.retention_days', 30);
        $cutoff = Carbon::now()->subDays($retentionDays);

        $oldBackups = DB::table('backups')
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($oldBackups as $backup) {
            // Delete file
            $filePath = storage_path("app/{$backup->path}");
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete record
            DB::table('backups')->where('id', $backup->id)->delete();

            $this->info("Cleaned old backup: {$backup->filename}");
        }
    }

    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
