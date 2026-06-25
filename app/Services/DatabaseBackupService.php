<?php

namespace App\Services;

class DatabaseBackupService
{
    private const FREQUENCIES = ['manual', 'daily', 'weekly', 'monthly'];

    public function __construct(private \PDO $pdo)
    {
    }

    public function dumpDatabase(?string $dbName = null): string
    {
        $dbName = $dbName ?: $this->databaseName();

        $out = "-- BCP-HRIS Backup\n";
        $out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tablesStmt = $this->pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME");
        $tablesStmt->execute([$dbName]);
        $tables = $tablesStmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $createStmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt->fetch(\PDO::FETCH_ASSOC);
            $out .= "-- Table: `{$table}`\n";
            $out .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $out .= $createRow['Create Table'] . ";\n\n";

            $rowsStmt = $this->pdo->query("SELECT * FROM `{$table}`");
            $rows = $rowsStmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows) {
                $cols = array_map(fn ($c) => '`' . $c . '`', array_keys($rows[0]));
                $colList = implode(',', $cols);
                $out .= "INSERT INTO `{$table}` ({$colList}) VALUES\n";
                $values = [];
                foreach ($rows as $row) {
                    $vals = [];
                    foreach ($row as $val) {
                        $vals[] = $val === null ? 'NULL' : $this->pdo->quote($val);
                    }
                    $values[] = '(' . implode(',', $vals) . ')';
                }
                $out .= implode(",\n", $values) . ";\n\n";
            }
        }

        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $out;
    }

    public function databaseName(): string
    {
        $stmt = $this->pdo->query('SELECT DATABASE() as db');
        $row = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
        return (string) ($row['db'] ?? '');
    }

    public function loadSettings(): array
    {
        $defaults = [
            'frequency' => 'manual',
            'last_run_at' => null,
            'last_file' => null,
            'keep_files' => 14,
        ];

        $path = $this->settingsPath();
        if (!is_file($path)) {
            return $defaults;
        }

        $json = file_get_contents($path);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($data)) {
            return $defaults;
        }

        $settings = array_merge($defaults, $data);
        if (!in_array($settings['frequency'], self::FREQUENCIES, true)) {
            $settings['frequency'] = 'manual';
        }

        return $settings;
    }

    public function saveSettings(string $frequency): array
    {
        if (!in_array($frequency, self::FREQUENCIES, true)) {
            $frequency = 'manual';
        }

        $settings = $this->loadSettings();
        $settings['frequency'] = $frequency;
        $this->writeSettings($settings);

        return $settings;
    }

    public function runIfDue(): array
    {
        $settings = $this->loadSettings();
        if (!$this->isDue($settings)) {
            return [
                'created' => false,
                'message' => 'Auto backup belum jatuh tempo.',
                'settings' => $settings,
            ];
        }

        return $this->createBackup();
    }

    public function createBackup(): array
    {
        $dbName = $this->databaseName();
        $filename = 'auto_backup_' . $dbName . '_' . date('Ymd_His') . '.sql';
        $path = $this->backupDirectory() . DIRECTORY_SEPARATOR . $filename;

        file_put_contents($path, $this->dumpDatabase($dbName));

        $settings = $this->loadSettings();
        $settings['last_run_at'] = date('Y-m-d H:i:s');
        $settings['last_file'] = $filename;
        $this->writeSettings($settings);
        $this->pruneOldBackups((int) ($settings['keep_files'] ?? 14));

        return [
            'created' => true,
            'file' => $filename,
            'path' => $path,
            'message' => 'Auto backup berhasil dibuat: ' . $filename,
            'settings' => $settings,
        ];
    }

    public function isDue(array $settings): bool
    {
        $frequency = (string) ($settings['frequency'] ?? 'manual');
        if ($frequency === 'manual') {
            return false;
        }

        $lastRunAt = (string) ($settings['last_run_at'] ?? '');
        if ($lastRunAt === '') {
            return true;
        }

        $last = strtotime($lastRunAt);
        if (!$last) {
            return true;
        }

        $intervals = [
            'daily' => 86400,
            'weekly' => 604800,
            'monthly' => 2592000,
        ];

        return (time() - $last) >= ($intervals[$frequency] ?? PHP_INT_MAX);
    }

    public function nextRunText(array $settings): string
    {
        $frequency = (string) ($settings['frequency'] ?? 'manual');
        if ($frequency === 'manual') {
            return 'Auto backup nonaktif.';
        }

        $lastRunAt = (string) ($settings['last_run_at'] ?? '');
        if ($lastRunAt === '') {
            return 'Akan dibuat saat cron berikutnya berjalan.';
        }

        $last = strtotime($lastRunAt);
        if (!$last) {
            return 'Akan dibuat saat cron berikutnya berjalan.';
        }

        $intervals = [
            'daily' => '+1 day',
            'weekly' => '+1 week',
            'monthly' => '+1 month',
        ];
        $next = strtotime($intervals[$frequency] ?? '+100 years', $last);

        return $next ? date('Y-m-d H:i:s', $next) : 'Akan dibuat saat cron berikutnya berjalan.';
    }

    public function recentBackups(int $limit = 10): array
    {
        $files = glob($this->backupDirectory() . DIRECTORY_SEPARATOR . 'auto_backup_*.sql') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return array_map(function ($path) {
            return [
                'name' => basename($path),
                'size' => filesize($path) ?: 0,
                'modified_at' => date('Y-m-d H:i:s', filemtime($path) ?: time()),
            ];
        }, array_slice($files, 0, $limit));
    }

    private function settingsPath(): string
    {
        $directory = storage_path('app');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory . DIRECTORY_SEPARATOR . 'backup_settings.json';
    }

    private function backupDirectory(): string
    {
        $directory = storage_path('app/backups/database');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory;
    }

    private function writeSettings(array $settings): void
    {
        file_put_contents(
            $this->settingsPath(),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function pruneOldBackups(int $keepFiles): void
    {
        $keepFiles = max(1, $keepFiles);
        $files = glob($this->backupDirectory() . DIRECTORY_SEPARATOR . 'auto_backup_*.sql') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        foreach (array_slice($files, $keepFiles) as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
