<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class HolidayController extends Controller
{
    private function parseFileToRows(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return $this->parseCsv($path);
        }
        return $this->parseSpreadsheet($path);
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        if (!is_file($path)) {
            return $rows;
        }
        if (($fh = fopen($path, 'r')) === false) {
            return $rows;
        }
        $header = null;
        while (($data = fgetcsv($fh)) !== false) {
            if ($header === null) {
                $header = array_map('trim', $data);
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $row[$key] = $data[$i] ?? null;
            }
            $rows[] = $row;
        }
        fclose($fh);
        return $rows;
    }

    private function parseSpreadsheet(string $path): array
    {
        $rows = [];
        $sheet = IOFactory::load($path)->getActiveSheet();
        $data = $sheet->toArray(null, true, true, true);
        if (count($data) < 2) {
            return $rows;
        }
        $headerRow = array_shift($data);
        $headers = [];
        foreach ($headerRow as $col => $val) {
            $key = trim((string) $val);
            if ($key !== '') {
                $headers[$col] = $key;
            }
        }
        foreach ($data as $row) {
            $item = [];
            foreach ($headers as $col => $key) {
                $item[$key] = $row[$col] ?? null;
            }
            $rows[] = $item;
        }
        return $rows;
    }

    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        try {
            return \Carbon\Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function index(Request $request)
    {
        $user = current_user();
        $companyId = current_company_id();
        $globalCompanyId = 0;
        $messages = [];
        $errors = [];
        $edit = null;

        if ($request->has('edit')) {
            $editId = (int) $request->query('edit');
            if ($editId > 0) {
                $edit = Holiday::where('company_id', $globalCompanyId)->where('id', $editId)->first();
            }
        }

        if ($request->isMethod('post')) {
            $action = $request->input('action', '');
            if ($action === 'create') {
                $data = $request->validate([
                    'holiday_date' => ['required','date'],
                    'name' => ['nullable','string','max:120'],
                ]);
                $exists = Holiday::where('company_id', $globalCompanyId)
                    ->where('holiday_date', $data['holiday_date'])
                    ->exists();
                if ($exists) {
                    $errors[] = 'Tanggal libur sudah ada.';
                } else {
                    Holiday::create([
                        'company_id' => $globalCompanyId,
                        'holiday_date' => $data['holiday_date'],
                        'name' => $data['name'] ?? null,
                    ]);
                    $messages[] = 'Libur nasional berhasil ditambahkan.';
                }
            } elseif ($action === 'update') {
                $data = $request->validate([
                    'id' => ['required','integer'],
                    'holiday_date' => ['required','date'],
                    'name' => ['nullable','string','max:120'],
                ]);
                $id = (int) $data['id'];
                $exists = Holiday::where('company_id', $globalCompanyId)
                    ->where('holiday_date', $data['holiday_date'])
                    ->where('id', '<>', $id)
                    ->exists();
                if ($exists) {
                    $errors[] = 'Tanggal libur sudah ada.';
                } else {
                    Holiday::where('company_id', $globalCompanyId)
                        ->where('id', $id)
                        ->update([
                            'holiday_date' => $data['holiday_date'],
                            'name' => $data['name'] ?? null,
                        ]);
                    $messages[] = 'Libur nasional berhasil diperbarui.';
                }
            } elseif ($action === 'delete') {
                $data = $request->validate([
                    'id' => ['required','integer'],
                ]);
                Holiday::where('company_id', $globalCompanyId)
                    ->where('id', (int) $data['id'])
                    ->delete();
                $messages[] = 'Libur nasional dihapus.';
            } elseif ($action === 'import_file') {
                $data = $request->validate([
                    'holiday_file' => ['required','file','max:5120'],
                ]);
                $file = $request->file('holiday_file');
                if (!$file || !$file->isValid()) {
                    $errors[] = 'File tidak valid.';
                } else {
                    $ext = strtolower($file->getClientOriginalExtension());
                    if (!in_array($ext, ['csv','xlsx','xls'], true)) {
                        $errors[] = 'Format file harus CSV/XLSX/XLS.';
                    } else {
                        $tmpDir = storage_path('app/tmp');
                        if (!File::exists($tmpDir)) {
                            File::makeDirectory($tmpDir, 0755, true);
                        }
                        $tmpFile = $tmpDir . DIRECTORY_SEPARATOR . 'holidays_' . uniqid() . '.' . $ext;
                        $file->move($tmpDir, basename($tmpFile));
                        $rows = $this->parseFileToRows($tmpFile);
                        @unlink($tmpFile);

                        if (empty($rows)) {
                            $errors[] = 'File kosong atau format header tidak dikenali.';
                        } else {
                            $created = 0;
                            $updated = 0;
                            foreach ($rows as $row) {
                                $date = $this->normalizeDate($row['holiday_date'] ?? null);
                                $name = trim((string) ($row['holiday_name'] ?? $row['name'] ?? 'Libur Nasional'));
                                if (!$date) {
                                    continue;
                                }
                                $exists = Holiday::where('company_id', $globalCompanyId)
                                    ->where('holiday_date', $date)
                                    ->first();
                                if ($exists) {
                                    $exists->update(['name' => $name]);
                                    $updated++;
                                } else {
                                    Holiday::create([
                                        'company_id' => $globalCompanyId,
                                        'holiday_date' => $date,
                                        'name' => $name,
                                    ]);
                                    $created++;
                                }
                            }
                            $messages[] = "Import libur nasional berhasil. Baru: {$created}, Update: {$updated}.";
                        }
                    }
                }
            }
        }

        $items = Holiday::where('company_id', $globalCompanyId)
            ->orderBy('holiday_date')
            ->get();

        return view('modules.holidays.index', compact('user', 'companyId', 'items', 'messages', 'errors', 'edit'));
    }

    public function template()
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['holiday_date', 'holiday_name']);
        fputcsv($handle, ['2026-01-01', 'Tahun Baru']);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="holiday_template.csv"');
    }
}
