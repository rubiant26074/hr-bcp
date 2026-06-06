<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function index()
    {
        return view('modules.settings.index');
    }

    public function theme(Request $request)
    {
        $messages = [];
        $theme = session('theme', 'bcp_form');

        if ($request->isMethod('post')) {
            $selected = $request->input('theme', 'bcp_form');
            if (!in_array($selected, ['light', 'dark', 'mekari', 'heart', 'bcp_form'], true)) {
                $selected = 'bcp_form';
            }
            session(['theme' => $selected]);
            $theme = $selected;
            $messages[] = 'Tema berhasil disimpan.';
        }

        return view('modules.settings.theme', compact('messages', 'theme'));
    }

    private function dumpDatabase(\PDO $pdo, string $dbName): string
    {
        $out = "-- BCP-HRIS Backup\n";
        $out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tablesStmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME");
        $tablesStmt->execute([$dbName]);
        $tables = $tablesStmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt->fetch(\PDO::FETCH_ASSOC);
            $out .= "-- Table: `{$table}`\n";
            $out .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $out .= $createRow['Create Table'] . ";\n\n";

            $rowsStmt = $pdo->query("SELECT * FROM `{$table}`");
            $rows = $rowsStmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows) {
                $cols = array_map(function ($c) { return '`' . $c . '`'; }, array_keys($rows[0]));
                $colList = implode(',', $cols);
                $out .= "INSERT INTO `{$table}` ({$colList}) VALUES\n";
                $values = [];
                foreach ($rows as $row) {
                    $vals = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $pdo->quote($val);
                        }
                    }
                    $values[] = '(' . implode(',', $vals) . ')';
                }
                $out .= implode(",\n", $values) . ";\n\n";
            }
        }

        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $out;
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $stringChar = '';
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($inString) {
                if ($ch === $stringChar) {
                    $escaped = false;
                    $j = $i - 1;
                    while ($j >= 0 && $sql[$j] === '\\') {
                        $escaped = !$escaped;
                        $j--;
                    }
                    if (!$escaped) {
                        $inString = false;
                        $stringChar = '';
                    }
                }
                $buffer .= $ch;
                continue;
            }

            if ($ch === '\'' || $ch === '"') {
                $inString = true;
                $stringChar = $ch;
                $buffer .= $ch;
                continue;
            }

            if ($ch === '-' && $next === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                $buffer .= "\n";
                continue;
            }
            if ($ch === '#') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                $buffer .= "\n";
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $i += 2;
                while ($i < $len && !($sql[$i] === '*' && ($i + 1 < $len && $sql[$i + 1] === '/'))) {
                    $i++;
                }
                $i++;
                continue;
            }

            if ($ch === ';') {
                $stmt = trim($buffer);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $ch;
        }
        $last = trim($buffer);
        if ($last !== '') {
            $statements[] = $last;
        }
        return $statements;
    }

    public function backup(Request $request)
    {
        $messages = [];
        $errors = [];
        $maxUploadBytes = 70 * 1024 * 1024;
        $pdo = legacy_pdo();

        if ($request->isMethod('post')) {
            $action = $request->input('action', 'backup');
            if ($action === 'restore') {
                $confirm = $request->input('confirm_restore') === '1';
                $confirmText = trim((string) $request->input('confirm_text', ''));
                $mode = $request->input('restore_mode', 'drop');
                if (!$confirm || strtoupper($confirmText) !== 'RESTORE') {
                    $errors[] = 'Konfirmasi restore belum valid. Centang checkbox dan ketik RESTORE.';
                }
                if (!$request->hasFile('sql_file')) {
                    $errors[] = 'File SQL belum dipilih.';
                } else {
                    $file = $request->file('sql_file');
                    if (!$file->isValid()) {
                        $errors[] = 'Upload file SQL gagal.';
                    } elseif ($file->getSize() > $maxUploadBytes) {
                        $errors[] = 'Ukuran file melebihi 70 MB.';
                    }
                }

                if (!$errors) {
                    try {
                        $sql = file_get_contents($request->file('sql_file')->getPathname());
                        if ($sql === false) {
                            throw new \RuntimeException('Gagal membaca file SQL.');
                        }
                        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                        $statements = $this->splitSqlStatements($sql);
                        $executed = 0;
                        foreach ($statements as $stmt) {
                            $upper = strtoupper(ltrim($stmt));
                            if ($mode === 'append') {
                                if (str_starts_with($upper, 'DROP TABLE') || str_starts_with($upper, 'CREATE TABLE')) {
                                    continue;
                                }
                            }
                            $pdo->exec($stmt);
                            $executed++;
                        }
                        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                        $messages[] = "Restore berhasil. Query dieksekusi: {$executed}.";
                    } catch (\Throwable $e) {
                        $errors[] = 'Gagal restore database: ' . $e->getMessage();
                    }
                }
            } else {
                try {
                    $dbName = (string) DB::selectOne('SELECT DATABASE() as db')->db;
                    $sql = $this->dumpDatabase($pdo, $dbName);
                    $filename = 'backup_' . $dbName . '_' . date('Ymd_His') . '.sql';
                    return response($sql)
                        ->header('Content-Type', 'application/sql')
                        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                } catch (\Throwable $e) {
                    $errors[] = 'Gagal membuat backup: ' . $e->getMessage();
                }
            }
        }

        return view('modules.settings.backup', compact('messages', 'errors'));
    }

    public function reset(Request $request)
    {
        $messages = [];
        $errors = [];
        $tablesToTruncate = [
            'attendance_logs',
            'attendance_daily',
            'payroll',
            'payroll_period',
        ];

        if ($request->isMethod('post')) {
            $confirm = $request->input('confirm_reset') === '1';
            $confirmText = trim((string) $request->input('confirm_text', ''));
            if (!$confirm || strtoupper($confirmText) !== 'RESET') {
                $errors[] = 'Konfirmasi reset belum valid. Centang checkbox dan ketik RESET.';
            }

            if (!$errors) {
                try {
                    DB::statement('SET FOREIGN_KEY_CHECKS=0');
                    foreach ($tablesToTruncate as $table) {
                        DB::statement("TRUNCATE TABLE {$table}");
                    }
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                    $messages[] = 'Reset database berhasil. Data karyawan tidak dihapus.';
                } catch (\Throwable $e) {
                    $errors[] = 'Gagal reset database: ' . $e->getMessage();
                }
            }
        }

        return view('modules.settings.reset', compact('messages', 'errors'));
    }
}
