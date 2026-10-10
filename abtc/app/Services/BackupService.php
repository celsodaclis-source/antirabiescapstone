<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupService
{
    protected string $disk = 'local';
    protected string $directory = 'backups';

    public function create(?string $notes = null): Backup
    {
        $user = auth()->user();
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $timestamp = now()->format('Y-m-d_H-i-s');

        $backupDir = storage_path($this->directory);
        if (!File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        if ($connection === 'sqlite') {
            $dbPath = $config['database'];
            $filename = "abtc_backup_{$timestamp}.sqlite";
            $destination = $backupDir . DIRECTORY_SEPARATOR . $filename;
            File::copy($dbPath, $destination);
        } else {
            $filename = "abtc_backup_{$timestamp}.sql";
            $destination = $backupDir . DIRECTORY_SEPARATOR . $filename;
            $this->dumpMysql($config, $destination);
        }

        $size = File::size($destination);

        return Backup::create([
            'user_id' => $user?->id,
            'filename' => $filename,
            'original_name' => $config['database'] ?? 'database',
            'size' => $size,
            'disk' => $this->disk,
            'status' => 'completed',
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    protected function dumpMysql(array $config, string $destination): void
    {
        $mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        $host = escapeshellarg($config['host'] ?? '127.0.0.1');
        $port = escapeshellarg($config['port'] ?? '3306');
        $database = escapeshellarg($config['database']);
        $username = escapeshellarg($config['username']);
        $password = $config['password'] ?? '';

        $cmd = "\"{$mysqldump}\" -h {$host} -P {$port} -u {$username}";
        if ($password !== '') {
            $cmd .= " -p" . escapeshellarg($password);
        }
        $cmd .= " {$database} > " . escapeshellarg($destination) . " 2>&1";

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException('mysqldump failed: ' . implode("\n", $output));
        }
    }

    public function getPath(Backup $backup): string
    {
        return storage_path($this->directory . DIRECTORY_SEPARATOR . $backup->filename);
    }

    public function delete(Backup $backup): bool
    {
        $path = $this->getPath($backup);
        if (File::exists($path)) {
            File::delete($path);
        }
        return $backup->delete();
    }

    public function getBackups()
    {
        return Backup::with('user')->latest('created_at')->paginate(15);
    }

    public function getStats(): array
    {
        $total = Backup::count();
        $totalSize = Backup::sum('size');
        $lastBackup = Backup::latest('created_at')->first();

        return [
            'total' => $total,
            'total_size' => $totalSize,
            'last_backup' => $lastBackup,
        ];
    }
}
