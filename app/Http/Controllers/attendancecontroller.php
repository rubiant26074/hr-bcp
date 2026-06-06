<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Services\OvertimeCalculator;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FaceProfile;
use App\Models\AttendanceLocation;
use App\Models\Holiday;
use App\Models\AbsenceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AttendanceController extends Controller
{
    const ATTENDANCE_MODE_IN = 'IN';
    const ATTENDANCE_MODE_OUT = 'OUT';

    private function applyExcludeInactiveEmployeeStatuses($query, string $alias = 'e')
    {
        $column = $alias !== '' ? ($alias . '.active_status') : 'active_status';
        return $query->where(function ($q) use ($column) {
            $q->whereNull($column)
                ->orWhereRaw("TRIM(COALESCE($column, '')) = ''")
                ->orWhereRaw(
                    "LOWER(TRIM(COALESCE($column, ''))) NOT IN (?, ?, ?)",
                    [
                        strtolower(Employee::ACTIVE_STATUS_RESIGN),
                        strtolower(Employee::ACTIVE_STATUS_PHK),
                        strtolower(Employee::ACTIVE_STATUS_HABIS_KONTRAK),
                    ]
                );
        });
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth * $c;
    }

    private function faceDistance(array $a, array $b): float
    {
        $sum = 0.0;
        $len = min(count($a), count($b));
        if ($len === 0) {
            return 999.0;
        }
        for ($i = 0; $i < $len; $i++) {
            $d = ((float) $a[$i]) - ((float) $b[$i]);
            $sum += $d * $d;
        }
        return sqrt($sum);
    }

    private function parseCoordinate($value): ?float
    {
        if ($value === null) {
            return null;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            return (float) $raw;
        }

        // Support DMS formats like 6°21'48.4"S or 107°10'42.9"E
        $pattern = '/(\d{1,3})\D+(\d{1,3})\D+(\d{1,3}(?:\.\d+)?)\D*([NSEW])?/i';
        if (!preg_match($pattern, $raw, $m)) {
            return null;
        }
        $deg = (float) $m[1];
        $min = (float) $m[2];
        $sec = (float) $m[3];
        $dir = strtoupper((string) ($m[4] ?? ''));
        $decimal = $deg + ($min / 60) + ($sec / 3600);
        if (in_array($dir, ['S', 'W'], true)) {
            $decimal *= -1;
        }
        return $decimal;
    }

    public function mobile()
    {
        $user = current_user();
        $companyId = current_company_id();
        $company = Company::find($companyId);
        return view('modules.attendance.mobile', compact('user', 'company'));
    }

    public function faceProfile()
    {
        $user = current_user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $profile = FaceProfile::where('user_id', (int) $user['id'])->first();
        return response()->json([
            'ok' => true,
            'descriptor' => $profile?->descriptor,
        ]);
    }

    public function faceEnroll(Request $request)
    {
        $user = current_user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $data = $request->validate([
            'descriptor' => ['required','array','min:10'],
        ]);
        FaceProfile::updateOrCreate(
            ['user_id' => (int) $user['id']],
            ['descriptor' => array_values($data['descriptor'])]
        );
        return response()->json(['ok' => true, 'message' => 'Wajah tersimpan.']);
    }

    public function faceCheckin(Request $request)
    {
        $user = current_user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $data = $request->validate([
            'descriptor' => ['required','array','min:10'],
            'lat' => ['required','numeric'],
            'lng' => ['required','numeric'],
        ]);
        $companyId = current_company_id();
        $employeeId = (int) ($user['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return response()->json(['ok' => false, 'message' => 'Akun belum terhubung ke karyawan.'], 422);
        }

        // Allow check-in against all configured locations across companies.
        $locations = AttendanceLocation::orderBy('id')->get();
        if ($locations->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Lokasi absen belum diatur.'], 422);
        }
        $inRadius = false;
        foreach ($locations as $location) {
            $distance = $this->distanceMeters((float) $data['lat'], (float) $data['lng'], (float) $location->latitude, (float) $location->longitude);
            if ($distance <= (int) $location->radius_m) {
                $inRadius = true;
                break;
            }
        }
        if (!$inRadius) {
            return response()->json(['ok' => false, 'message' => 'Di luar radius lokasi absen.'], 422);
        }

        $profile = FaceProfile::where('user_id', (int) $user['id'])->first();
        if (!$profile || empty($profile->descriptor)) {
            return response()->json(['ok' => false, 'message' => 'Wajah belum didaftarkan.'], 422);
        }

        $distanceFace = $this->faceDistance($profile->descriptor, $data['descriptor']);
        if ($distanceFace > 0.55) {
            return response()->json(['ok' => false, 'message' => 'Wajah tidak cocok.'], 422);
        }

        $today = date('Y-m-d');
        $last = DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('scan_time', $today)
            ->orderByDesc('scan_time')
            ->first();
        $mode = $last ? self::ATTENDANCE_MODE_OUT : self::ATTENDANCE_MODE_IN;

        AttendanceService::insertLog([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'device_user_id' => (string) ($user['email'] ?? ''),
            'scan_time' => date('Y-m-d H:i:s'),
            'verify_type' => 'mobile_face_' . strtolower($mode),
            'device_id' => 'web',
        ]);

        return response()->json(['ok' => true, 'message' => 'Absensi ' . ($mode === self::ATTENDANCE_MODE_IN ? 'masuk' : 'pulang') . ' berhasil.']);
    }

    public function locationSettings(Request $request)
    {
        $user = current_user();
        $companyId = current_company_id();
        $location = null;
        if ($request->has('edit')) {
            $editId = (int) $request->query('edit');
            if ($editId > 0) {
                $location = AttendanceLocation::where('company_id', $companyId)->where('id', $editId)->first();
            }
        }
        $messages = [];

        if ($request->isMethod('post')) {
            $action = (string) $request->input('action', 'save');
            if ($action === 'delete') {
                $data = $request->validate([
                    'id' => ['required','integer','min:1'],
                ]);
                AttendanceLocation::where('company_id', $companyId)
                    ->where('id', (int) $data['id'])
                    ->delete();
                $messages[] = 'Lokasi absen dihapus.';
            } else {
                $data = $request->validate([
                    'id' => ['nullable','integer','min:1'],
                    'location_name' => ['nullable','string','max:120'],
                    'latitude' => ['required','string'],
                    'longitude' => ['required','string'],
                    'radius_m' => ['required','integer','min:1','max:1000'],
                ]);
                $lat = $this->parseCoordinate($data['latitude']);
                $lng = $this->parseCoordinate($data['longitude']);
                if ($lat === null || $lng === null) {
                    return back()->withErrors(['latitude' => 'Format koordinat tidak valid.'])->withInput();
                }
                if (!empty($data['id'])) {
                    AttendanceLocation::where('company_id', $companyId)
                        ->where('id', (int) $data['id'])
                        ->update([
                            'location_name' => $data['location_name'] ?? null,
                            'latitude' => $lat,
                            'longitude' => $lng,
                            'radius_m' => (int) $data['radius_m'],
                        ]);
                    $messages[] = 'Lokasi absen diperbarui.';
                } else {
                    AttendanceLocation::create([
                        'company_id' => $companyId,
                        'location_name' => $data['location_name'] ?? null,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'radius_m' => (int) $data['radius_m'],
                    ]);
                    $messages[] = 'Lokasi absen ditambahkan.';
                }
            }
        }

        $locations = AttendanceLocation::where('company_id', $companyId)->orderBy('id')->get();
        return view('modules.settings.attendance_location', compact('user', 'companyId', 'location', 'locations', 'messages'));
    }
    private function normalizeNameMatch(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9 ]+/i', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private function nameTokens(string $value): array
    {
        $norm = $this->normalizeNameMatch($value);
        return $norm === '' ? [] : explode(' ', $norm);
    }

    private function normalizeHeaderKey(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        $value = strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private function normalizeLabel(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return strtolower($value);
    }

    private function normalizeMalformedCsvRow(array $row, string $delimiter): array
    {
        if (count($row) !== 1) {
            return $row;
        }

        $line = trim((string) ($row[0] ?? ''));
        if ($line === '') {
            return $row;
        }

        $parsedDirect = str_getcsv($line, $delimiter, '"', '\\');
        if (is_array($parsedDirect) && count($parsedDirect) > 1) {
            return $parsedDirect;
        }

        $looksWrapped =
            str_starts_with($line, '"')
            && str_ends_with($line, '"')
            && str_contains($line, '""');
        if (!$looksWrapped) {
            return $row;
        }

        if (strlen($line) >= 2 && $line[0] === '"' && substr($line, -1) === '"') {
            $line = substr($line, 1, -1);
        }
        $line = str_replace('""', '"', $line);

        $parsed = str_getcsv($line, $delimiter, '"', '\\');
        if (is_array($parsed) && count($parsed) > 1) {
            return $parsed;
        }

        return $row;
    }

    private function inferCompanyIdFromText(?string $text): ?int
    {
        $label = $this->normalizeLabel((string) $text);
        if ($label === '') {
            return null;
        }

        static $companies = null;
        if ($companies === null) {
            $companies = Company::query()->select('id', 'company_name')->get();
        }

        foreach ($companies as $c) {
            $name = $this->normalizeLabel((string) ($c->company_name ?? ''));
            if ($name !== '' && str_contains($name, $label)) {
                return (int) $c->id;
            }
        }

        $map = [
            'berkah' => 'berkah cipta persada',
            'bcp' => 'berkah cipta persada',
            'bina' => 'bina control power',
            'keihindo' => 'keihindo inti elsys',
            'resource' => 'resource mitra bersama',
            'mitra bersama' => 'resource mitra bersama',
            'rmb' => 'resource mitra bersama',
        ];
        foreach ($map as $keyword => $targetName) {
            if (!str_contains($label, $keyword)) {
                continue;
            }
            foreach ($companies as $c) {
                $name = $this->normalizeLabel((string) ($c->company_name ?? ''));
                if (str_contains($name, $targetName)) {
                    return (int) $c->id;
                }
            }
        }

        return null;
    }

    private function parseImportScanDateTime(string $scanRaw): ?\DateTime
    {
        $scanRaw = trim($scanRaw);
        if ($scanRaw === '') {
            return null;
        }

        $formats = [
            'd/m/Y H:i',
            'd/m/Y G:i',
            'd/m/Y H.i',
            'd/m/Y G.i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d G:i',
        ];
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $scanRaw);
            if ($dt instanceof \DateTime) {
                return $dt;
            }
        }
        return null;
    }
    public function import(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            return redirect()->route('attendance.import');
        }
        $companyId = current_company_id();
        $importCompanyId = $companyId;
        $companies = Company::orderBy('id')->get();
        $companyImportStats = [];

        $messages = [];
        if ($request->isMethod('post')) {
            if ($user['role'] === 'Super Admin') {
                $rawImportCompany = trim((string) $request->input('company_id', (string) $companyId));
                if ($rawImportCompany === '0' || strtolower($rawImportCompany) === 'all') {
                    $importCompanyId = 0; // 0 = all entities
                } else {
                    $importCompanyId = (int) $rawImportCompany;
                }
            }
            if (!$request->hasFile('file')) {
                $messages[] = 'File upload failed.';
            } else {
                $file = $request->file('file');
                if (!$file->isValid()) {
                    $messages[] = 'File upload failed.';
                } elseif ($file->getSize() > 5 * 1024 * 1024) {
                    $messages[] = 'File terlalu besar (maks 5MB).';
                } else {
                    $ext = strtolower($file->getClientOriginalExtension());
                    if (!in_array($ext, ['csv', 'xls', 'xlsx'], true)) {
                        $messages[] = 'Format harus CSV/XLS/XLSX.';
                    } elseif (in_array($ext, ['xls', 'xlsx'], true)) {
                        $result = $this->importFromXlsLogTab($importCompanyId, $file->getPathname(), $file->getClientOriginalName(), $companyImportStats);
                        if (!empty($result['error'])) {
                            $messages[] = $result['error'];
                        } else {
                            $messages[] = "Import XLS sukses: {$result['count']} log. Unknown employee: {$result['unknown']}.";
                            if (!empty($result['sheets'])) {
                                $messages[] = 'Sheet diproses: ' . implode(', ', $result['sheets']) . '.';
                            }
                            foreach ($this->formatImportCompanySummary($companyImportStats) as $line) {
                                $messages[] = $line;
                            }
                        }
                    } else {
                        $handle = fopen($file->getPathname(), 'r');
                        $delimiter = $this->detectCsvDelimiter($handle);
                        rewind($handle);
                        $header = fgetcsv($handle, 0, $delimiter, '"', '\\');
                        if (is_array($header)) {
                            $header = $this->normalizeMalformedCsvRow($header, $delimiter);
                        }
                        if (!is_array($header) || count($header) === 0) {
                            $messages[] = 'Header CSV kosong.';
                            fclose($handle);
                            return view('modules.attendance.import', compact('user', 'companyId', 'importCompanyId', 'companies', 'messages'));
                        }
                        $map = [];
                        foreach ($header as $i => $h) {
                            $key = $this->normalizeHeaderKey((string) $h);
                            if ($key !== '') {
                                $map[$key] = $i;
                            }
                        }
                        $required = ['name','date/time','location id','verifycode'];
                        $aliases = [
                            'name' => ['nama'],
                            'date/time' => ['date time', 'datetime', 'date-time', 'tgl/waktu', 'tgl waktu', 'tanggal/waktu', 'tanggal waktu', 'waktu'],
                            'location id' => ['location_id', 'locationid', 'lokasi id', 'lokasi_id'],
                            'verifycode' => ['verify code', 'verify_code', 'kode verifikasi', 'kode_verifikasi'],
                            'department' => ['departemen', 'dept', 'department', 'company', 'entitas', 'perusahaan'],
                            'id number' => ['idnumber', 'no.pin', 'no pin', 'no_pin', 'pin', 'no.id', 'no id'],
                            'cardno' => ['card no', 'card_no', 'no.kartu', 'no kartu', 'no_kartu'],
                            'no' => ['no.', 'no id', 'no.id', 'nomor'],
                        ];
                        foreach ($aliases as $key => $opts) {
                            if (isset($map[$key])) {
                                continue;
                            }
                            foreach ($opts as $opt) {
                                if (isset($map[$opt])) {
                                    $map[$key] = $map[$opt];
                                    break;
                                }
                            }
                        }
                        $missing = [];
                        foreach ($required as $req) {
                            if (!array_key_exists($req, $map)) {
                                $missing[] = $req;
                            }
                        }
                        if (!empty($missing)) {
                            $rows = [$header];
                            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                                if (is_array($row)) {
                                    $row = $this->normalizeMalformedCsvRow($row, $delimiter);
                                }
                                $rows[] = $row;
                            }
                            fclose($handle);
                            $unknown = 0;
                            if ($this->isCsvLogMatrix($rows)) {
                                $count = $this->importFromCsvLogMatrix($importCompanyId, $rows, $file->getClientOriginalName(), $unknown, $companyImportStats);
                            } elseif ($this->isCsvAttendanceRecord($rows)) {
                                $count = $this->importFromCsvAttendanceRecord($importCompanyId, $rows, $file->getClientOriginalName(), $unknown, $companyImportStats);
                            } else {
                                $count = 0;
                            }
                            if ($count > 0) {
                            $scopeText = $importCompanyId === 0 ? ' (semua entitas)' : '';
                            $messages[] = "Import CSV (2.1.*) sukses{$scopeText}: {$count} log. Unknown employee: {$unknown}.";
                                foreach ($this->formatImportCompanySummary($companyImportStats) as $line) {
                                    $messages[] = $line;
                                }
                            } else {
                                $messages[] = 'Header CSV tidak sesuai. Kurang kolom: ' . implode(', ', $missing) . '.';
                            }
                        } else {
                            $count = 0;
                            $unknown = 0;
                            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                                if (is_array($row)) {
                                    $row = $this->normalizeMalformedCsvRow($row, $delimiter);
                                }
                                $getValue = fn(string $key) => isset($map[$key]) ? trim($row[$map[$key]] ?? '') : '';

                                $scanRaw = $getValue('date/time');
                                $dt = $this->parseImportScanDateTime($scanRaw);
                                if (!$dt) continue;

                                $scanTime = $dt->format('Y-m-d H:i:s');
                                $preferredCompanyId = $this->inferCompanyIdFromText($getValue('department'));
                                $employeeId = $this->findEmployeeIdForImport($importCompanyId, $getValue, $unknown, $preferredCompanyId);
                                $targetCompanyId = $this->resolveImportCompanyId($importCompanyId, $employeeId);

                                AttendanceService::insertLog([
                                    'company_id' => $targetCompanyId,
                                    'employee_id' => $employeeId,
                                    'device_user_id' => $getValue('no') ?: $getValue('no.'),
                                    'scan_time' => $scanTime,
                                    'verify_type' => $getValue('verifycode'),
                                    'device_id' => $getValue('location id'),
                                ]);
                                $companyImportStats[$targetCompanyId] = (int) ($companyImportStats[$targetCompanyId] ?? 0) + 1;
                                $count++;
                            }
                            fclose($handle);
                            $scopeText = $importCompanyId === 0 ? ' (semua entitas)' : '';
                            $messages[] = "Import sukses{$scopeText}: {$count} log. Unknown employee: {$unknown}.";
                            foreach ($this->formatImportCompanySummary($companyImportStats) as $line) {
                                $messages[] = $line;
                            }
                        }
                    }
                }
            }
        }

        return view('modules.attendance.import', compact('user', 'companyId', 'importCompanyId', 'companies', 'messages'));
    }

    private function importFromXlsLogTab(int $companyId, string $path, string $originalName, array &$companyImportStats): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return [
                'error' => 'File Excel tidak dapat dibaca di server. Silakan Save As tab 2.1.* ke CSV lalu import CSV.',
            ];
        }

        $sheetNames = $spreadsheet->getSheetNames();
        $targetSheets = [];
        foreach ($sheetNames as $name) {
            if (preg_match('/^2\.1\./i', $name)) {
                $targetSheets[] = $name;
            }
        }
        if (count($targetSheets) === 0) {
            return [
                'error' => 'Sheet 2.1.* tidak ditemukan. Pastikan file berisi tab log detail (mis. 2.1.8888).',
            ];
        }

        $total = 0;
        $unknown = 0;
        $processed = [];

        foreach ($targetSheets as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (!$sheet) {
                continue;
            }
            $result = $this->parseXlsLogSheet($companyId, $sheet, $originalName, $unknown, $companyImportStats);
            $total += $result['count'];
            if ($result['count'] > 0) {
                $processed[] = $sheetName;
            }
        }

        return [
            'count' => $total,
            'unknown' => $unknown,
            'sheets' => $processed,
        ];
    }

    private function importFromCsvLogMatrix(int $companyId, array $rows, string $originalName, int &$unknown, array &$companyImportStats): int
    {
        $total = 0;
        if (count($rows) < 5) {
            return 0;
        }
        $maxCol = 0;
        foreach ($rows as $row) {
            $maxCol = max($maxCol, count($row));
        }

        $metaRowIdx = null;
        for ($r = 0; $r < min(20, count($rows)); $r++) {
            $hasNama = false;
            $hasDept = false;
            for ($c = 0; $c < $maxCol; $c++) {
                $label = $this->normalizeLabel((string) ($rows[$r][$c] ?? ''));
                if ($label === 'nama') {
                    $hasNama = true;
                }
                if ($label === 'departemen' || $label === 'dept') {
                    $hasDept = true;
                }
            }
            if ($hasNama && $hasDept) {
                $metaRowIdx = $r;
                break;
            }
        }
        if ($metaRowIdx === null) {
            return 0;
        }
        $infoRowIdx = $metaRowIdx + 1;

        $deptCols = [];
        for ($c = 0; $c < $maxCol; $c++) {
            $label = $this->normalizeLabel((string) ($rows[$metaRowIdx][$c] ?? ''));
            if ($label === 'departemen' || $label === 'dept') {
                $deptCols[] = $c;
            }
        }
        if (count($deptCols) === 0) {
            return 0;
        }

        $periodYm = $this->parseYearMonthFromRows($rows, $originalName);
        $blocks = [];
        for ($i = 0; $i < count($deptCols); $i++) {
            $start = $deptCols[$i];
            $end = ($i + 1 < count($deptCols)) ? ($deptCols[$i + 1] - 1) : ($maxCol - 1);
            $dept = trim((string) ($rows[$metaRowIdx][$start + 1] ?? ''));
            $name = '';
            $no = '';
            $dateRange = '';
            for ($c = $start; $c <= $end; $c++) {
                $labelMeta = $this->normalizeLabel((string) ($rows[$metaRowIdx][$c] ?? ''));
                if ($labelMeta === 'nama') {
                    $name = trim((string) ($rows[$metaRowIdx][$c + 1] ?? ''));
                }
                $labelInfo = $this->normalizeLabel((string) ($rows[$infoRowIdx][$c] ?? ''));
                if ($labelInfo === 'no') {
                    $no = trim((string) ($rows[$infoRowIdx][$c + 1] ?? ''));
                }
                if ($labelInfo === 'tanggal') {
                    $dateRange = trim((string) ($rows[$infoRowIdx][$c + 1] ?? ''));
                }
            }
            $blocks[] = [
                'start' => $start,
                'end' => $end,
                'dept' => $dept,
                'name' => $name,
                'no' => $no,
                'date_range' => $dateRange,
            ];
        }

        $headerRowIdx = null;
        $dateColIdx = null;
        for ($r = $infoRowIdx + 1; $r < min($infoRowIdx + 30, count($rows)); $r++) {
            for ($c = 0; $c < $maxCol; $c++) {
                if ($this->normalizeLabel((string) ($rows[$r][$c] ?? '')) === 'tgl/hari') {
                    $headerRowIdx = $r;
                    $dateColIdx = $c;
                    break 2;
                }
            }
        }
        if ($headerRowIdx === null || $dateColIdx === null) {
            return 0;
        }
        $subHeaderRowIdx = $headerRowIdx + 1;

        foreach ($blocks as $block) {
            $preferredCompanyId = $this->inferCompanyIdFromText($block['dept'] ?? '');
            $employeeId = $this->findEmployeeIdByNoOrName($companyId, $block['no'], $block['name'], $unknown, $preferredCompanyId);
            $ym = $this->parseYearMonth($block['date_range'], $originalName);
            if (!$ym && $periodYm) {
                $ym = $periodYm;
            }
            if (!$ym) {
                continue;
            }

            $colMap = [];
            for ($c = $block['start']; $c <= $block['end']; $c++) {
                $sub = $this->normalizeLabel((string) ($rows[$subHeaderRowIdx][$c] ?? ''));
                if (!in_array($sub, ['msuk', 'masuk', 'kluar', 'keluar'], true)) {
                    continue;
                }
                $group = '';
                for ($lc = $c; $lc >= $block['start']; $lc--) {
                    $g = $this->normalizeLabel((string) ($rows[$headerRowIdx][$lc] ?? ''));
                    if ($g !== '') {
                        $group = $g;
                        break;
                    }
                }
                if ($group === '') {
                    continue;
                }
                $groupKey = null;
                if (str_contains($group, 'jam kerja 1')) {
                    $groupKey = 'jk1';
                } elseif (str_contains($group, 'jam kerja 2')) {
                    $groupKey = 'jk2';
                } elseif (str_contains($group, 'lembur')) {
                    $groupKey = 'ot';
                }
                if ($groupKey === null) {
                    continue;
                }
                $subKey = in_array($sub, ['msuk', 'masuk'], true) ? 'in' : 'out';
                $colMap[$groupKey . '_' . $subKey] = $c;
            }

            for ($r = $subHeaderRowIdx + 1; $r < count($rows); $r++) {
                $tglVal = trim((string) ($rows[$r][$dateColIdx] ?? ''));
                if ($tglVal === '') {
                    continue;
                }
                if (!preg_match('/\d{1,2}/', $tglVal, $m)) {
                    continue;
                }
                $day = (int) $m[0];
                $date = sprintf('%04d-%02d-%02d', $ym['year'], $ym['month'], $day);

                foreach ($colMap as $colIdx) {
                    $timeVal = trim((string) ($rows[$r][$colIdx] ?? ''));
                    if ($timeVal === '' || stripos($timeVal, 'absen') !== false) {
                        continue;
                    }
                    $times = preg_split('/[\s,]+/', $timeVal);
                    foreach ($times as $t) {
                        $t = str_replace('.', ':', $t);
                        if (!preg_match('/^\d{1,2}:\d{2}$/', $t)) {
                            continue;
                        }
                        $scanTime = $date . ' ' . $t . ':00';
                        $targetCompanyId = $this->resolveImportCompanyId($companyId, $employeeId);
                        AttendanceService::insertLog([
                            'company_id' => $targetCompanyId,
                            'employee_id' => $employeeId,
                            'device_user_id' => $block['no'] ?: $block['name'],
                            'scan_time' => $scanTime,
                            'verify_type' => 'csv_import',
                            'device_id' => 'CSV:2.1',
                        ]);
                        $companyImportStats[$targetCompanyId] = (int) ($companyImportStats[$targetCompanyId] ?? 0) + 1;
                        $total++;
                    }
                }
            }
        }

        return $total;
    }

    private function isCsvLogMatrix(array $rows): bool
    {
        $maxCol = 0;
        foreach ($rows as $row) {
            $maxCol = max($maxCol, count($row));
        }
        $hasMeta = false;
        $hasTgl = false;
        for ($r = 0; $r < min(30, count($rows)); $r++) {
            $rowHasNama = false;
            $rowHasDept = false;
            for ($c = 0; $c < $maxCol; $c++) {
                $label = $this->normalizeLabel((string) ($rows[$r][$c] ?? ''));
                if ($label === 'nama') {
                    $rowHasNama = true;
                }
                if ($label === 'departemen' || $label === 'dept') {
                    $rowHasDept = true;
                }
                if ($label === 'tgl/hari') {
                    $hasTgl = true;
                }
            }
            if ($rowHasNama && $rowHasDept) {
                $hasMeta = true;
            }
        }
        return $hasMeta && $hasTgl;
    }

    private function parseYearMonthFromRows(array $rows, string $originalName): ?array
    {
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $cell = trim((string) $cell);
                if ($cell === '') {
                    continue;
                }
                if (preg_match('/(\\d{4})[\\/\\-](\\d{1,2})[\\/\\-](\\d{1,2})/', $cell, $m)) {
                    return ['year' => (int) $m[1], 'month' => (int) $m[2]];
                }
            }
        }
        return $this->parseYearMonth('', $originalName);
    }

    private function isCsvAttendanceRecord(array $rows): bool
    {
        $hasAttendanceDate = false;
        $hasUserId = false;
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $cell = strtolower(trim((string) $cell));
                if ($cell === '') {
                    continue;
                }
                if (str_contains($cell, 'attendance date:')) {
                    $hasAttendanceDate = true;
                }
                if (str_contains($cell, 'user id:')) {
                    $hasUserId = true;
                }
            }
            if ($hasAttendanceDate && $hasUserId) {
                return true;
            }
        }
        return false;
    }

    private function importFromCsvAttendanceRecord(int $companyId, array $rows, string $originalName, int &$unknown, array &$companyImportStats): int
    {
        $startDate = null;
        $endDate = null;
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $cell = trim((string) $cell);
                if ($cell === '') {
                    continue;
                }
                if (preg_match('/attendance date:\\s*(\\d{4}-\\d{2}-\\d{2})\\s*~\\s*(\\d{4}-\\d{2}-\\d{2})/i', $cell, $m)) {
                    $startDate = $m[1];
                    $endDate = $m[2];
                    break 2;
                }
            }
        }

        $startYmd = $startDate ? date_create($startDate) : null;
        $endYmd = $endDate ? date_create($endDate) : null;

        $total = 0;
        $rowCount = count($rows);
        for ($r = 0; $r < $rowCount; $r++) {
            $row = $rows[$r];
            $userId = '';
            $name = '';
            $department = '';

            for ($c = 0; $c < count($row); $c++) {
                $cell = trim((string) ($row[$c] ?? ''));
                if ($cell === '') {
                    continue;
                }
                $cellLower = strtolower($cell);
                if (str_contains($cellLower, 'user id:')) {
                    if (preg_match('/user\s*id\s*:\s*([^\s,;]+)/i', $cell, $m)) {
                        $userId = trim((string) ($m[1] ?? ''));
                    } else {
                        $userId = trim((string) ($row[$c + 1] ?? ''));
                    }
                }
                if (str_contains($cellLower, 'name:')) {
                    if (preg_match('/name\s*:\s*(.+)$/i', $cell, $m)) {
                        $name = trim((string) ($m[1] ?? ''));
                    } else {
                        $name = trim((string) ($row[$c + 1] ?? ''));
                    }
                }
                if (str_contains($cellLower, 'department:')) {
                    if (preg_match('/department\s*:\s*(.+)$/i', $cell, $m)) {
                        $department = trim((string) ($m[1] ?? ''));
                    } else {
                        $department = trim((string) ($row[$c + 1] ?? ''));
                    }
                }
            }

            if ($userId === '' && $name === '' && $department === '') {
                continue;
            }

            $dayRowIdx = $r + 1;
            if ($dayRowIdx >= $rowCount) {
                continue;
            }

            $dayRow = $rows[$dayRowIdx];
            $dayCols = [];
            for ($c = 0; $c < count($dayRow); $c++) {
                $cell = trim((string) ($dayRow[$c] ?? ''));
                if ($cell === '') {
                    continue;
                }
                if (preg_match('/^\\d{1,2}$/', $cell)) {
                    $dayCols[$c] = (int) $cell;
                }
            }
            if (count($dayCols) === 0) {
                continue;
            }

            $preferredCompanyId = $this->inferCompanyIdFromText($department);
            $employeeId = $this->findEmployeeIdByNoOrName($companyId, $userId, $name, $unknown, $preferredCompanyId);

            $dataRowIdx = $r + 2;
            while ($dataRowIdx < $rowCount) {
                $dataRow = $rows[$dataRowIdx];
                $nextRow = $rows[$dataRowIdx + 1] ?? [];
                $nextHasUser = false;
                foreach ($nextRow as $cell) {
                    if (str_contains(strtolower(trim((string) $cell)), 'user id:')) {
                        $nextHasUser = true;
                        break;
                    }
                }

                foreach ($dayCols as $colIdx => $day) {
                    $cell = trim((string) ($dataRow[$colIdx] ?? ''));
                    if ($cell === '') {
                        continue;
                    }
                    if (stripos($cell, 'absen') !== false) {
                        continue;
                    }
                    $times = preg_split('/[\\s,]+/', trim($cell));
                    foreach ($times as $t) {
                        $t = str_replace('.', ':', $t);
                        if (!preg_match('/^\\d{1,2}:\\d{2}$/', $t)) {
                            continue;
                        }
                        $date = $this->resolveAttendanceDate($startYmd, $endYmd, $day);
                        if ($date === null) {
                            continue;
                        }
                        $scanTime = $date . ' ' . $t . ':00';
                        $targetCompanyId = $this->resolveImportCompanyId($companyId, $employeeId);
                        AttendanceService::insertLog([
                            'company_id' => $targetCompanyId,
                            'employee_id' => $employeeId,
                            'device_user_id' => $userId ?: $name,
                            'scan_time' => $scanTime,
                            'verify_type' => 'csv_import',
                            'device_id' => 'CSV:attendance_record',
                        ]);
                        $companyImportStats[$targetCompanyId] = (int) ($companyImportStats[$targetCompanyId] ?? 0) + 1;
                        $total++;
                    }
                }

                if ($nextHasUser) {
                    break;
                }
                $dataRowIdx++;
            }
        }

        return $total;
    }

    private function resolveAttendanceDate(?\DateTime $startDate, ?\DateTime $endDate, int $day): ?string
    {
        if (!$startDate) {
            return null;
        }
        $year = (int) $startDate->format('Y');
        $month = (int) $startDate->format('m');
        $startDay = (int) $startDate->format('d');
        $endMonth = $endDate ? (int) $endDate->format('m') : $month;

        if ($day < $startDay && $endDate && $endMonth !== $month) {
            $month = $endMonth;
            $year = (int) $endDate->format('Y');
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function detectCsvDelimiter($handle): string
    {
        $pos = ftell($handle);
        $lines = [];
        for ($i = 0; $i < 5; $i++) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }
            $lines[] = $line;
        }
        fseek($handle, $pos);

        $semi = 0;
        $comma = 0;
        foreach ($lines as $line) {
            $semi += substr_count($line, ';');
            $comma += substr_count($line, ',');
        }
        return ($semi >= $comma) ? ';' : ',';
    }

    private function parseXlsLogSheet(int $companyId, $sheet, string $originalName, int &$unknown, array &$companyImportStats): array
    {
        $highestRow = (int) $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $maxCol = Coordinate::columnIndexFromString($highestCol);

        $getCell = function (int $row, int $col) use ($sheet): string {
            $val = $sheet->getCellByColumnAndRow($col, $row)->getFormattedValue();
            return trim((string) $val);
        };

        $metaRow = null;
        for ($r = 1; $r <= min(20, $highestRow); $r++) {
            $hasNama = false;
            $hasDept = false;
            for ($c = 1; $c <= $maxCol; $c++) {
                $label = $this->normalizeLabel($getCell($r, $c));
                if ($label === 'nama') {
                    $hasNama = true;
                }
                if ($label === 'departemen' || $label === 'dept') {
                    $hasDept = true;
                }
            }
            if ($hasNama && $hasDept) {
                $metaRow = $r;
                break;
            }
        }
        if ($metaRow === null) {
            return ['count' => 0];
        }

        $infoRow = $metaRow + 1;

        $blocks = [];
        $deptCols = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $label = $this->normalizeLabel($getCell($metaRow, $c));
            if ($label === 'departemen' || $label === 'dept') {
                $deptCols[] = $c;
            }
        }
        if (count($deptCols) === 0) {
            return ['count' => 0];
        }

        for ($i = 0; $i < count($deptCols); $i++) {
            $start = $deptCols[$i];
            $end = ($i + 1 < count($deptCols)) ? ($deptCols[$i + 1] - 1) : $maxCol;
            $dept = $getCell($metaRow, $start + 1);
            $name = '';
            $no = '';
            $dateRange = '';

            for ($c = $start; $c <= $end; $c++) {
                $labelMeta = $this->normalizeLabel($getCell($metaRow, $c));
                if ($labelMeta === 'nama') {
                    $name = $getCell($metaRow, $c + 1);
                }
                $labelInfo = $this->normalizeLabel($getCell($infoRow, $c));
                if ($labelInfo === 'no') {
                    $no = $getCell($infoRow, $c + 1);
                }
                if ($labelInfo === 'tanggal') {
                    $dateRange = $getCell($infoRow, $c + 1);
                }
            }

            $blocks[] = [
                'start' => $start,
                'end' => $end,
                'dept' => $dept,
                'name' => $name,
                'no' => $no,
                'date_range' => $dateRange,
            ];
        }

        $total = 0;
        foreach ($blocks as $block) {
            $preferredCompanyId = $this->inferCompanyIdFromText($block['dept'] ?? '');
            $employeeId = $this->findEmployeeIdByNoOrName($companyId, $block['no'], $block['name'], $unknown, $preferredCompanyId);
            $ym = $this->parseYearMonth($block['date_range'], $originalName);
            if (!$ym) {
                continue;
            }

            $headerRow = null;
            $dateCol = null;
            for ($r = $infoRow + 1; $r <= min($infoRow + 20, $highestRow); $r++) {
                for ($c = $block['start']; $c <= $block['end']; $c++) {
                    if ($this->normalizeLabel($getCell($r, $c)) === 'tgl/hari') {
                        $headerRow = $r;
                        $dateCol = $c;
                        break 2;
                    }
                }
            }
            if ($headerRow === null || $dateCol === null) {
                continue;
            }
            $subHeaderRow = $headerRow + 1;

            $colMap = [];
            for ($c = $block['start']; $c <= $block['end']; $c++) {
                $sub = $this->normalizeLabel($getCell($subHeaderRow, $c));
                if (!in_array($sub, ['msuk', 'masuk', 'kluar', 'keluar'], true)) {
                    continue;
                }
                $group = '';
                for ($lc = $c; $lc >= $block['start']; $lc--) {
                    $g = $this->normalizeLabel($getCell($headerRow, $lc));
                    if ($g !== '') {
                        $group = $g;
                        break;
                    }
                }
                if ($group === '') {
                    continue;
                }
                $groupKey = null;
                if (str_contains($group, 'jam kerja 1')) {
                    $groupKey = 'jk1';
                } elseif (str_contains($group, 'jam kerja 2')) {
                    $groupKey = 'jk2';
                } elseif (str_contains($group, 'lembur')) {
                    $groupKey = 'ot';
                }
                if ($groupKey === null) {
                    continue;
                }
                $subKey = in_array($sub, ['msuk', 'masuk'], true) ? 'in' : 'out';
                $colMap[$groupKey . '_' . $subKey] = $c;
            }

            for ($r = $subHeaderRow + 1; $r <= $highestRow; $r++) {
                $tglVal = $getCell($r, $dateCol);
                if ($tglVal === '') {
                    continue;
                }
                if (!preg_match('/\d{1,2}/', $tglVal, $m)) {
                    continue;
                }
                $day = (int) $m[0];
                $date = sprintf('%04d-%02d-%02d', $ym['year'], $ym['month'], $day);

                foreach ($colMap as $key => $colIdx) {
                    $timeVal = $getCell($r, $colIdx);
                    if ($timeVal === '' || stripos($timeVal, 'absen') !== false) {
                        continue;
                    }
                    $times = preg_split('/[\s,]+/', trim($timeVal));
                    foreach ($times as $t) {
                        $t = str_replace('.', ':', $t);
                        if (!preg_match('/^\d{1,2}:\d{2}$/', $t)) {
                            continue;
                        }
                        $scanTime = $date . ' ' . $t . ':00';
                        $targetCompanyId = $this->resolveImportCompanyId($companyId, $employeeId);
                        AttendanceService::insertLog([
                            'company_id' => $targetCompanyId,
                            'employee_id' => $employeeId,
                            'device_user_id' => $block['no'] ?: $block['name'],
                            'scan_time' => $scanTime,
                            'verify_type' => 'xls_import',
                            'device_id' => 'XLS:' . $sheet->getTitle(),
                        ]);
                        $companyImportStats[$targetCompanyId] = (int) ($companyImportStats[$targetCompanyId] ?? 0) + 1;
                        $total++;
                    }
                }
            }
        }

        return ['count' => $total];
    }

    private function parseYearMonth(string $dateRange, string $originalName): ?array
    {
        if (preg_match('/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/', $dateRange, $m)) {
            return ['year' => (int) $m[1], 'month' => (int) $m[2]];
        }
        if (preg_match('/_(\d{4})_(\d{1,2})_/', $originalName, $m)) {
            return ['year' => (int) $m[1], 'month' => (int) $m[2]];
        }
        return null;
    }

    private function resolveEmployeeIdFromHistoricalLog(int $companyId, string $deviceUserId): ?int
    {
        $deviceUserId = trim($deviceUserId);
        if ($deviceUserId === '') {
            return null;
        }

        $query = DB::table('attendance_logs as l')
            ->join('employees as e', 'e.id', '=', 'l.employee_id')
            ->where('l.device_user_id', $deviceUserId)
            ->whereNotNull('l.employee_id');

        if ($companyId > 0) {
            $query->where('e.company_id', $companyId);
        }

        $candidates = $query
            ->groupBy('l.employee_id')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select('l.employee_id', DB::raw('COUNT(*) as total'))
            ->limit(2)
            ->get();

        if ($candidates->count() !== 1) {
            return null;
        }

        return (int) ($candidates->first()->employee_id ?? 0) ?: null;
    }

    private function resolveEmployeeIdByNumericNikSuffix(int $companyId, string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^\d+$/', $value)) {
            return null;
        }

        $suffixCandidates = [$value];
        if (strlen($value) <= 4) {
            $suffixCandidates[] = str_pad($value, 4, '0', STR_PAD_LEFT);
        }
        $suffixCandidates = array_values(array_unique(array_filter($suffixCandidates, static fn ($v) => $v !== '')));

        foreach ($suffixCandidates as $suffix) {
            $query = Employee::select('id')->where('nik', 'like', '%' . $suffix);
            if ($companyId > 0) {
                $query->where('company_id', $companyId);
            }
            $matches = $query->limit(2)->get();
            if ($matches->count() === 1) {
                return (int) ($matches->first()->id ?? 0) ?: null;
            }
        }

        return null;
    }

    private function resolveEmployeeIdByApproximateName(int $companyId, string $employeeName): ?int
    {
        $needle = $this->normalizeNameMatch($employeeName);
        if ($needle === '') {
            return null;
        }

        $query = Employee::select('id', 'name');
        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }
        $candidates = $query->limit(1000)->get();

        $bestId = null;
        $bestPercent = 0.0;
        $bestDistance = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            $norm = $this->normalizeNameMatch((string) ($candidate->name ?? ''));
            if ($norm === '') {
                continue;
            }

            similar_text($needle, $norm, $percent);
            $distance = levenshtein($needle, $norm);

            if ($percent > $bestPercent || ($percent === $bestPercent && $distance < $bestDistance)) {
                $bestPercent = (float) $percent;
                $bestDistance = $distance;
                $bestId = (int) ($candidate->id ?? 0);
            }
        }

        if ($bestId && ($bestPercent >= 82.0 || $bestDistance <= 2)) {
            return $bestId;
        }

        return null;
    }

    private function findEmployeeIdByNoOrName(int $companyId, string $employeeNo, string $employeeName, int &$unknown, ?int $preferredCompanyId = null): ?int
    {
        $effectiveCompanyId = $companyId > 0 ? $companyId : (int) ($preferredCompanyId ?? 0);
        $employeeNo = trim($employeeNo);
        $employeeName = trim($employeeName);
        if ($employeeNo !== '') {
            $employeeQuery = Employee::select('id')->where('nik', $employeeNo);
            if ($effectiveCompanyId > 0) {
                $employeeQuery->where('company_id', $effectiveCompanyId);
            }
            $employee = $employeeQuery->first();
            if ($employee) {
                return $employee->id;
            }
        }

        if ($employeeNo !== '') {
            $fromNikSuffix = $this->resolveEmployeeIdByNumericNikSuffix($effectiveCompanyId, $employeeNo);
            if ($fromNikSuffix !== null) {
                return $fromNikSuffix;
            }
        }

        if ($employeeNo !== '') {
            $fromHistory = $this->resolveEmployeeIdFromHistoricalLog($effectiveCompanyId, $employeeNo);
            if ($fromHistory !== null) {
                return $fromHistory;
            }
        }

        if ($employeeName !== '') {
            $employeeQuery = Employee::select('id')->where('name', $employeeName);
            if ($effectiveCompanyId > 0) {
                $employeeQuery->where('company_id', $effectiveCompanyId);
            }
            $employee = $employeeQuery->first();
            if ($employee) {
                return $employee->id;
            }
        }

        if ($employeeName !== '') {
            $approx = $this->resolveEmployeeIdByApproximateName($effectiveCompanyId, $employeeName);
            if ($approx !== null) {
                return $approx;
            }
        }

        $unknown++;
        return null;
    }

    /**
     * Find an employee ID based on data from a CSV row for import.
     *
     * @param int $companyId
     * @param \Closure $getValue Function to get value from CSV row by header key
     * @param int $unknown Reference to the counter for unknown employees
     * @return int|null
     */
    private function findEmployeeIdForImport(int $companyId, \Closure $getValue, int &$unknown, ?int $preferredCompanyId = null): ?int
    {
        $effectiveCompanyId = $companyId > 0 ? $companyId : (int) ($preferredCompanyId ?? 0);
        $employeeName = $getValue('name');
        $idNumber = $getValue('id number');
        $cardNo = $getValue('cardno');
        $deviceUserId = $getValue('no') ?: $getValue('no.');

        // 1. Direct match
        $query = Employee::select('id');
        if ($effectiveCompanyId > 0) {
            $query->where('company_id', $effectiveCompanyId);
        }
        $query->where(function ($q) use ($idNumber, $cardNo, $deviceUserId, $employeeName) {
            if ($idNumber !== '') $q->orWhere('nik', $idNumber);
            if ($cardNo !== '') $q->orWhere('nik', $cardNo);
            if ($deviceUserId !== '') $q->orWhere('nik', $deviceUserId);
            if ($employeeName !== '') {
                $q->orWhere('name', $employeeName);
                $q->orWhere('name', 'like', $employeeName . '%');
            }
        });
        $employee = $query->first();

        if ($employee) {
            return $employee->id;
        }

        foreach ([$idNumber, $cardNo, $deviceUserId] as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $fromNikSuffix = $this->resolveEmployeeIdByNumericNikSuffix($effectiveCompanyId, $candidate);
            if ($fromNikSuffix !== null) {
                return $fromNikSuffix;
            }
        }

        foreach ([$idNumber, $cardNo, $deviceUserId] as $candidateUserId) {
            if ($candidateUserId === '') {
                continue;
            }
            $fromHistory = $this->resolveEmployeeIdFromHistoricalLog($effectiveCompanyId, $candidateUserId);
            if ($fromHistory !== null) {
                return $fromHistory;
            }
        }

        // 2. Fuzzy name match
        $normImport = $this->normalizeNameMatch($employeeName);
        if ($normImport === '') {
            $unknown++;
            return null;
        }

        $candidates = Employee::select('id', 'name')
            ->where('name', 'like', '%' . $employeeName . '%');
        if ($effectiveCompanyId > 0) {
            $candidates->where('company_id', $effectiveCompanyId);
        }
        $candidates = $candidates->get();

        foreach ($candidates as $candidate) {
            $normEmp = $this->normalizeNameMatch($candidate->name ?? '');
            if ($normEmp === '') continue;

            // a. Prefix match
            if (str_starts_with($normEmp, $normImport) || str_starts_with($normImport, $normEmp)) {
                return $candidate->id;
            }

            // b. Token match (all words from import name must be in employee name)
            $tokens = $this->nameTokens($employeeName);
            if (empty($tokens)) continue;

            $allFound = collect($tokens)->every(function ($token) use ($normEmp) {
                return $token === '' || str_contains($normEmp, $token);
            });

            if ($allFound) {
                return $candidate->id;
            }
        }

        if ($employeeName !== '') {
            $approx = $this->resolveEmployeeIdByApproximateName($effectiveCompanyId, $employeeName);
            if ($approx !== null) {
                return $approx;
            }
        }

        $unknown++;
        return null;
    }

    private function resolveImportCompanyId(int $selectedCompanyId, ?int $employeeId): int
    {
        if ($selectedCompanyId > 0) {
            return $selectedCompanyId;
        }

        if (($employeeId ?? 0) > 0) {
            static $companyByEmployeeId = [];
            if (!array_key_exists((int) $employeeId, $companyByEmployeeId)) {
                $companyByEmployeeId[(int) $employeeId] = (int) (Employee::where('id', (int) $employeeId)->value('company_id') ?? 0);
            }
            $resolved = (int) ($companyByEmployeeId[(int) $employeeId] ?? 0);
            if ($resolved > 0) {
                return $resolved;
            }
        }

        return current_company_id();
    }

    private function formatImportCompanySummary(array $companyImportStats): array
    {
        if (count($companyImportStats) === 0) {
            return [];
        }

        $companyIds = array_values(array_unique(array_filter(array_map('intval', array_keys($companyImportStats)), static fn ($v) => $v > 0)));
        $nameMap = [];
        if (count($companyIds) > 0) {
            $nameMap = Company::whereIn('id', $companyIds)->pluck('company_name', 'id')->all();
        }

        $lines = ['Distribusi log per entitas:'];
        foreach ($companyImportStats as $cid => $count) {
            $cid = (int) $cid;
            $count = (int) $count;
            if ($count <= 0) {
                continue;
            }
            $label = (string) ($nameMap[$cid] ?? ('Company #' . $cid));
            $lines[] = "- {$label}: {$count} log";
        }
        return $lines;
    }

    public function template()
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Department','Name','No','Date/Time','Location ID','ID Number','VerifyCode','CardNo']);
        fputcsv($handle, ['HRD','Budi', '0001', '11/03/2026 08:01', 'Device01', 'EMP001', 'FP', 'CARD001']);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="attendance_template.csv"');
    }

    public function logs(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $after = $request->query();
            unset($after['set_company']);
            return redirect()->route('attendance.logs', $after);
        }
        $companyId = current_company_id();
        $messages = [];

        if ($request->isMethod('post')) {
            $action = (string) $request->input('action', '');
            if ($action === 'bulk_delete') {
                $ids = $request->input('delete_ids', []);
                $deleted = AttendanceService::deleteLogsByCompany($companyId, $ids);
                $messages[] = "Delete selected selesai: {$deleted} log dihapus.";
            } elseif ($action === 'delete_range') {
                $dateFrom = date_input_to_db((string) $request->input('delete_date_from', ''));
                $dateTo = date_input_to_db((string) $request->input('delete_date_to', ''));
                if (!$dateFrom || !$dateTo) {
                    $messages[] = 'Range tanggal wajib diisi.';
                } elseif ($dateFrom > $dateTo) {
                    $messages[] = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';
                } else {
                    $deleted = AttendanceService::deleteLogsByCompanyDateRange($companyId, $dateFrom, $dateTo);
                    $messages[] = "Delete range selesai: {$deleted} log dihapus ({$dateFrom} s/d {$dateTo}).";
                }
            } elseif ($action === 'delete_all') {
                $deleted = AttendanceService::deleteAllLogsByCompany($companyId);
                $messages[] = "Delete all selesai: {$deleted} log dihapus.";
            } elseif ($action === 'auto_map_unknown') {
                @set_time_limit(120);
                $before = (int) DB::table('attendance_logs')
                    ->where('company_id', $companyId)
                    ->whereNull('employee_id')
                    ->count();
                $updated = AttendanceService::backfillMissingEmployeeIdsByCompany($companyId, 250);
                $after = (int) DB::table('attendance_logs')
                    ->where('company_id', $companyId)
                    ->whereNull('employee_id')
                    ->count();
                $messages[] = "Auto Mapping batch selesai: {$updated} log berhasil dipetakan. Sisa belum terpetakan: {$after} (sebelum: {$before}). Jalankan lagi jika masih ada sisa.";
            }
        }

        $companies = Company::orderBy('id')->get();
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'verify_type' => trim((string) $request->query('verify_type', '')),
            'device_id' => trim((string) $request->query('device_id', '')),
        ];

        $query = $request->query();
        unset($query['page']);
        $queryString = http_build_query($query);
        $queryPrefix = $queryString !== '' ? $queryString . '&' : '';

        $page = (int) $request->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $perPage = 50;
        $total = AttendanceService::logsCountByCompany($companyId, $filters);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $logs = AttendanceService::logsByCompany($companyId, $perPage, $offset, $filters);
        $unknownCount = (int) DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereNull('employee_id')
            ->count();

        return view('modules.attendance.logs', compact('user', 'companyId', 'companies', 'messages', 'filters', 'logs', 'total', 'page', 'totalPages', 'queryString', 'queryPrefix', 'unknownCount'));
    }

    public function daily(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $params = [];
            if ($request->query('date')) {
                $params['date'] = $request->query('date');
            }
            if ($request->query('rebuild')) {
                $params['rebuild'] = 1;
            }
            if ($request->query('only_present')) {
                $params['only_present'] = $request->query('only_present');
            }
            if ($request->query('q')) {
                $params['q'] = $request->query('q');
            }
            return redirect()->route('attendance.daily', $params);
        }
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $company = Company::find($companyId);
        $messages = [];
        $latestLogDate = AttendanceService::latestLogDateByCompany($companyId);

        if ($request->isMethod('post') && $request->input('action') === 'save_daily_verification') {
            $postDate = date_input_to_db($request->input('date', ''));
            $dateForSave = ($postDate && $postDate !== null) ? $postDate : ($latestLogDate ?: date('Y-m-d'));
            $onlyPresentPost = $request->input('only_present') === '1';
            $updatedOt = AttendanceService::saveNoOvertimePermitByCompanyDate(
                $companyId,
                $dateForSave,
                $request->input('employee_ids', []),
                $request->input('no_overtime_permit', [])
            );
            $updatedExcuse = AttendanceService::saveExcuseByCompanyDate(
                $companyId,
                $dateForSave,
                $request->input('employee_ids', []),
                $request->input('is_leave_excused', []),
                $request->input('is_sick_doctor_excused', [])
            );
            $params = ['date' => $dateForSave];
            if ($onlyPresentPost) {
                $params['only_present'] = '1';
            }
            $params['saved_ot'] = (string) $updatedOt;
            $params['saved_excuse'] = (string) $updatedExcuse;
            return redirect()->route('attendance.daily', $params);
        }

        $dateInput = $request->query('date', '');
        $dateParsed = date_input_to_db($dateInput);
        $filterEmployee = trim((string) $request->query('q', ''));
        $onlyPresent = $request->query('only_present') === '1';
        $showExcuseCols = !$onlyPresent;
        $date = $dateParsed && $dateParsed !== null ? $dateParsed : ($latestLogDate ?: date('Y-m-d'));

        if ($request->query('rebuild')) {
            AttendanceService::rebuildDaily($companyId, $date);
            $params = ['date' => $date];
            if ($onlyPresent) {
                $params['only_present'] = '1';
            }
            if ($filterEmployee !== '') {
                $params['q'] = $filterEmployee;
            }
            return redirect()->route('attendance.daily', $params);
        }
        $daily = AttendanceService::dailyAllByCompany($companyId, $date, $onlyPresent);
        $logCount = AttendanceService::logCountByCompanyDate($companyId, $date);
        if (count($daily) === 0 && $logCount > 0) {
            AttendanceService::rebuildDaily($companyId, $date);
            $daily = AttendanceService::dailyAllByCompany($companyId, $date, $onlyPresent);
            if (count($daily) > 0) {
                $messages[] = 'Rekap harian otomatis dibangun dari log absensi untuk tanggal ini.';
            }
        }
        if ($logCount === 0) {
            $messages[] = 'Tidak ada log absensi pada tanggal ini.';
            if ($latestLogDate) {
                $messages[] = 'Log terakhir tersedia pada tanggal ' . format_date_id($latestLogDate) . '.';
            }
        }
        if ($request->query('saved_ot')) {
            $messages[] = 'Verifikasi lembur tersimpan. Jumlah data yang diperbarui: ' . (int) $request->query('saved_ot') . '.';
        }
        if ($request->query('saved_excuse')) {
            $messages[] = 'Verifikasi cuti/sakit tersimpan. Jumlah data yang diperbarui: ' . (int) $request->query('saved_excuse') . '.';
        }
        if ($onlyPresent) {
            $messages[] = 'Filter "Tampilkan hanya yang hadir" sedang aktif. Untuk menandai cuti/sakit karyawan tidak hadir, nonaktifkan filter ini.';
        }

        $workDays = [];
        if ($company && !empty($company->work_days_json)) {
            $decodedWorkDays = json_decode((string) $company->work_days_json, true);
            if (is_array($decodedWorkDays)) {
                $workDays = array_values(array_filter(array_map(static function ($d) {
                    return trim((string) $d);
                }, $decodedWorkDays), static function ($d) {
                    return $d !== '';
                }));
            }
        }
        if (count($workDays) === 0) {
            // fallback default: Senin-Sabtu
            $workDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        }
        $dowMap = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $dow = $dowMap[(int) date('N', strtotime($date)) - 1] ?? '';
        $isOffDay = !in_array($dow, $workDays, true);
        $isNationalHoliday = Holiday::where('company_id', $companyId)
            ->where('holiday_date', $date)
            ->exists();

        $absenceStatusMap = [];
        $approvedAbsences = AbsenceRequest::where('company_id', $companyId)
            ->where('status', 'Approved')
            ->whereDate('date_start', '<=', $date)
            ->whereDate('date_end', '>=', $date)
            ->whereIn('request_type', ['Cuti', 'Izin'])
            ->get(['employee_id', 'request_type', 'reason']);
        foreach ($approvedAbsences as $req) {
            $employeeId = (int) ($req->employee_id ?? 0);
            if ($employeeId <= 0) {
                continue;
            }
            $type = trim((string) ($req->request_type ?? ''));
            $reason = mb_strtolower(trim((string) ($req->reason ?? '')));
            $isCutiBersamaGenerated =
                str_contains($reason, 'cuti lebaran') ||
                str_contains($reason, 'cuti bersama') ||
                str_contains($reason, 'otomatis izin');

            $current = $absenceStatusMap[$employeeId] ?? null;
            if ($current === null || ($current['request_type'] ?? '') !== 'Cuti') {
                $absenceStatusMap[$employeeId] = [
                    'request_type' => $type !== '' ? $type : 'Izin',
                    'is_cuti_bersama' => $isCutiBersamaGenerated,
                ];
            } elseif ($isCutiBersamaGenerated) {
                $absenceStatusMap[$employeeId]['is_cuti_bersama'] = true;
            }
        }

        if ($filterEmployee !== '') {
            $needle = mb_strtolower($filterEmployee);
            $daily = collect($daily)->filter(function ($row) use ($needle) {
                $nik = mb_strtolower((string) ($row->nik ?? ''));
                $name = mb_strtolower((string) ($row->name ?? ''));
                return (strpos($nik, $needle) !== false) || (strpos($name, $needle) !== false);
            })->values();
        }

        return view('modules.attendance.daily', compact('user', 'companyId', 'companies', 'company', 'messages', 'date', 'onlyPresent', 'showExcuseCols', 'daily', 'filterEmployee', 'isOffDay', 'isNationalHoliday', 'absenceStatusMap'));
    }

    private function cutoffRangeByPeriod(int $month, int $year): array
    {
        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $periodEnd = \DateTime::createFromFormat('Y-n-j', sprintf('%d-%d-%d', $year, $month, 20));
        if (!$periodEnd) {
            $periodEnd = new \DateTime(sprintf('%04d-%02d-20', $year, $month));
        }
        $periodStart = (clone $periodEnd)->modify('-1 month')->modify('+1 day');

        return [
            'start_date' => $periodStart->format('Y-m-d'),
            'end_date' => $periodEnd->format('Y-m-d'),
            'label' => $periodStart->format('d/m/Y') . ' - ' . $periodEnd->format('d/m/Y'),
        ];
    }

    private function defaultCutoffPeriod(?string $latestLogDate): array
    {
        if (!empty($latestLogDate)) {
            $anchor = new \DateTime($latestLogDate);
        } else {
            $anchor = new \DateTime('today');
        }
        $day = (int) $anchor->format('j');
        if ($day > 20) {
            $anchor->modify('+1 month');
        }
        return [
            'month' => (int) $anchor->format('n'),
            'year' => (int) $anchor->format('Y'),
        ];
    }

    public function monthly(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $params = [];
            if ($request->query('month')) {
                $params['month'] = $request->query('month');
            }
            if ($request->query('year')) {
                $params['year'] = $request->query('year');
            }
            if ($request->query('only_present')) {
                $params['only_present'] = $request->query('only_present');
            }
            if ($request->query('rebuild_month')) {
                $params['rebuild_month'] = 1;
            }
            if ($request->query('q')) {
                $params['q'] = $request->query('q');
            }
            return redirect()->route('attendance.monthly', $params);
        }
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $company = Company::find($companyId);
        $messages = [];

        $latestLogDate = AttendanceService::latestLogDateByCompany($companyId);
        $defaultMonth = $latestLogDate ? (int) date('m', strtotime($latestLogDate)) : (int) date('m');
        $defaultYear = $latestLogDate ? (int) date('Y', strtotime($latestLogDate)) : (int) date('Y');
        $month = (int) $request->query('month', $defaultMonth);
        $year = (int) $request->query('year', $defaultYear);
        $onlyPresent = $request->query('only_present') === '1';
        $filterEmployee = trim((string) $request->query('q', ''));
        if ($month < 1 || $month > 12) {
            $month = $defaultMonth;
        }
        $range = $this->cutoffRangeByPeriod($month, $year);
        $startDate = $range['start_date'];
        $endDate = $range['end_date'];

        if ($request->query('rebuild_month')) {
            $cursor = $startDate;
            while ($cursor <= $endDate) {
                AttendanceService::rebuildDaily($companyId, $cursor);
                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
            }
            $messages[] = 'Rebuild rekap bulanan selesai untuk periode cut-off ini.';
        }
        $join = $onlyPresent ? 'join' : 'leftJoin';
        $rowsQuery = DB::table('employees as e')
            ->$join('attendance_daily as d', function ($q) use ($startDate, $endDate) {
                $q->on('d.employee_id', '=', 'e.id')
                  ->whereBetween('d.date', [$startDate, $endDate]);
            })
            ->where('e.company_id', $companyId);
        $this->applyExcludeInactiveEmployeeStatuses($rowsQuery, 'e');
        if ($filterEmployee !== '') {
            $rowsQuery->where(function ($q) use ($filterEmployee) {
                $q->where('e.nik', 'like', '%' . $filterEmployee . '%')
                  ->orWhere('e.name', 'like', '%' . $filterEmployee . '%');
            });
        }
        $rows = $rowsQuery
            ->groupBy('e.id', 'e.nik', 'e.name')
            ->orderBy('e.name')
            ->select('e.nik', 'e.name', DB::raw('COUNT(d.date) as hadir'), DB::raw('SUM(d.work_hours) as total_hours'), DB::raw('SUM(d.overtime_hours) as overtime'))
            ->get();

        $logCount = (int) DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereRaw('DATE(scan_time) BETWEEN ? AND ?', [$startDate, $endDate])
            ->count();
        if ($logCount === 0) {
            $messages[] = 'Tidak ada log absensi pada periode cut-off ini.';
        }
        $messages[] = 'Periode cut-off: ' . ($range['label'] ?? '-');
        if ($latestLogDate) {
            $messages[] = 'Log terakhir tersedia pada ' . format_date_id($latestLogDate) . '.';
        }

        return view('modules.attendance.monthly', compact('user', 'companyId', 'companies', 'company', 'messages', 'month', 'year', 'range', 'onlyPresent', 'rows', 'filterEmployee'));
    }

    public function monthlyEmployee(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $params = [];
            if ($request->query('month')) {
                $params['month'] = $request->query('month');
            }
            if ($request->query('year')) {
                $params['year'] = $request->query('year');
            }
            if ($request->query('employee_id')) {
                $params['employee_id'] = $request->query('employee_id');
            }
            if ($request->query('q')) {
                $params['q'] = $request->query('q');
            }
            return redirect()->route('attendance.monthly_employee', $params);
        }

        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();
        $company = Company::find($companyId);
        $messages = [];

        $latestLogDate = AttendanceService::latestLogDateByCompany($companyId);
        $defaultPeriod = $this->defaultCutoffPeriod($latestLogDate);
        $month = (int) ($request->isMethod('post')
            ? $request->input('month', $request->query('month', $defaultPeriod['month']))
            : $request->query('month', $defaultPeriod['month']));
        $year = (int) ($request->isMethod('post')
            ? $request->input('year', $request->query('year', $defaultPeriod['year']))
            : $request->query('year', $defaultPeriod['year']));
        $filterEmployee = trim((string) ($request->isMethod('post')
            ? $request->input('q', $request->query('q', ''))
            : $request->query('q', '')));
        if ($month < 1 || $month > 12) {
            $month = (int) $defaultPeriod['month'];
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) $defaultPeriod['year'];
        }
        $range = $this->cutoffRangeByPeriod($month, $year);
        $startDate = $range['start_date'];
        $endDate = $range['end_date'];

        $employeesQuery = Employee::query()
            ->where('company_id', $companyId);
        $this->applyExcludeInactiveEmployeeStatuses($employeesQuery, '');
        if ($filterEmployee !== '') {
            $employeesQuery->where(function ($q) use ($filterEmployee) {
                $q->where('nik', 'like', '%' . $filterEmployee . '%')
                    ->orWhere('name', 'like', '%' . $filterEmployee . '%');
            });
        }
        $employees = $employeesQuery
            ->orderBy('name')
            ->get(['id', 'nik', 'name']);

        $employeeId = (int) ($request->isMethod('post')
            ? $request->input('employee_id', $request->query('employee_id', 0))
            : $request->query('employee_id', 0));
        if ($employeeId <= 0 && $employees->count() === 1) {
            $employeeId = (int) ($employees->first()->id ?? 0);
        }

        $selectedEmployee = null;
        $rows = [];
        $summary = [
            'hadir' => 0,
            'tidak_hadir' => 0,
            'cuti' => 0,
            'izin' => 0,
            'cuti_bersama' => 0,
            'libur_mingguan' => 0,
            'libur_nasional' => 0,
            'work_hours' => 0.0,
            'overtime_hours' => 0.0,
        ];

        if ($employeeId > 0) {
            $selectedEmployee = Employee::query()
                ->where('company_id', $companyId)
                ->where('id', $employeeId)
                ->where(function ($q) {
                    $q->whereNull('active_status')
                        ->orWhereRaw("TRIM(COALESCE(active_status, '')) = ''")
                        ->orWhereRaw(
                            "LOWER(TRIM(COALESCE(active_status, ''))) NOT IN (?, ?, ?)",
                            [
                                strtolower(Employee::ACTIVE_STATUS_RESIGN),
                                strtolower(Employee::ACTIVE_STATUS_PHK),
                                strtolower(Employee::ACTIVE_STATUS_HABIS_KONTRAK),
                            ]
                        );
                })
                ->first(['id', 'nik', 'name']);
            if (!$selectedEmployee) {
                $messages[] = 'Karyawan tidak ditemukan di company aktif.';
            }
        }

        if ($request->query('saved_verification')) {
            $messages[] = 'Verifikasi bulanan tersimpan. Jumlah data yang diperbarui: ' . (int) $request->query('saved_verification') . '.';
        }

        if ($request->isMethod('post') && (string) $request->input('action') === 'save_monthly_verification') {
            if (!$selectedEmployee) {
                $messages[] = 'Pilih employee terlebih dahulu sebelum simpan verifikasi.';
            } else {
                $rowDates = array_values(array_unique(array_filter(array_map(static function ($v) {
                    $s = trim((string) $v);
                    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
                }, (array) $request->input('row_dates', [])))));

                $checkedNoOt = array_flip(array_values(array_filter(array_map(static function ($v) {
                    $s = trim((string) $v);
                    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
                }, (array) $request->input('no_overtime_permit_dates', [])))));
                $checkedLeave = array_flip(array_values(array_filter(array_map(static function ($v) {
                    $s = trim((string) $v);
                    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
                }, (array) $request->input('is_leave_excused_dates', [])))));
                $checkedSick = array_flip(array_values(array_filter(array_map(static function ($v) {
                    $s = trim((string) $v);
                    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
                }, (array) $request->input('is_sick_doctor_excused_dates', [])))));

                $validDates = array_values(array_filter($rowDates, static function ($d) use ($startDate, $endDate) {
                    return $d >= $startDate && $d <= $endDate;
                }));

                $updated = 0;
                if (count($validDates) > 0) {
                    $existingRows = DB::table('attendance_daily')
                        ->where('employee_id', (int) $selectedEmployee->id)
                        ->whereIn('date', $validDates)
                        ->get(['date', 'check_in', 'check_out', 'work_hours']);
                    $existingMap = [];
                    foreach ($existingRows as $row) {
                        $existingMap[(string) $row->date] = $row;
                    }

                    DB::beginTransaction();
                    try {
                        foreach ($validDates as $date) {
                            $existing = $existingMap[$date] ?? null;
                            $hasAttendance = $existing && (
                                !empty($existing->check_in) ||
                                !empty($existing->check_out) ||
                                ((float) ($existing->work_hours ?? 0) > 0)
                            );

                            if ($hasAttendance) {
                                $noOtFlag = isset($checkedNoOt[$date]) ? 1 : 0;
                                $overtimeHours = 0.0;
                                if ($noOtFlag === 0) {
                                    $calc = OvertimeCalculator::calculateForRecord(
                                        $companyId,
                                        $date,
                                        (string) ($existing->check_in ?? ''),
                                        (string) ($existing->check_out ?? ''),
                                        false
                                    );
                                    $overtimeHours = round((float) ($calc['hours'] ?? 0), 2);
                                }
                                $updated += DB::table('attendance_daily')
                                    ->where('employee_id', (int) $selectedEmployee->id)
                                    ->where('date', $date)
                                    ->update([
                                        'no_overtime_permit' => $noOtFlag,
                                        'overtime_hours' => $overtimeHours,
                                        'is_leave_excused' => 0,
                                        'is_sick_doctor_excused' => 0,
                                    ]);
                                continue;
                            }

                            $leaveFlag = isset($checkedLeave[$date]) ? 1 : 0;
                            $sickFlag = isset($checkedSick[$date]) ? 1 : 0;
                            if ($leaveFlag === 1 && $sickFlag === 1) {
                                $sickFlag = 0;
                            }

                            if ($existing) {
                                $updated += DB::table('attendance_daily')
                                    ->where('employee_id', (int) $selectedEmployee->id)
                                    ->where('date', $date)
                                    ->update([
                                        'no_overtime_permit' => 0,
                                        'overtime_hours' => 0,
                                        'is_leave_excused' => $leaveFlag,
                                        'is_sick_doctor_excused' => $sickFlag,
                                    ]);
                            } elseif ($leaveFlag === 1 || $sickFlag === 1) {
                                DB::statement('INSERT INTO attendance_daily (employee_id, date, check_in, check_out, work_hours, overtime_hours, no_overtime_permit, is_leave_excused, is_sick_doctor_excused)
                                               VALUES (?,?,?,?,?,?,?,?,?)
                                               ON DUPLICATE KEY UPDATE
                                                 no_overtime_permit = VALUES(no_overtime_permit),
                                                 overtime_hours = VALUES(overtime_hours),
                                                 is_leave_excused = VALUES(is_leave_excused),
                                                 is_sick_doctor_excused = VALUES(is_sick_doctor_excused)', [
                                    (int) $selectedEmployee->id,
                                    $date,
                                    null,
                                    null,
                                    0,
                                    0,
                                    0,
                                    $leaveFlag,
                                    $sickFlag,
                                ]);
                                $updated++;
                            }
                        }
                        DB::commit();
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        $messages[] = 'Gagal menyimpan verifikasi bulanan: ' . $e->getMessage();
                    }
                }

                $params = [
                    'month' => $month,
                    'year' => $year,
                    'employee_id' => (int) $selectedEmployee->id,
                    'saved_verification' => $updated,
                ];
                if ($filterEmployee !== '') {
                    $params['q'] = $filterEmployee;
                }
                return redirect()->route('attendance.monthly_employee', $params);
            }
        }

        if ($selectedEmployee) {
            // Auto-heal: if logs exist but daily summary is empty/stale,
            // rebuild daily rows for this employee and period from logs.
            $logAggRows = DB::table('attendance_logs')
                ->where('company_id', $companyId)
                ->where('employee_id', (int) $selectedEmployee->id)
                ->whereRaw('DATE(scan_time) BETWEEN ? AND ?', [$startDate, $endDate])
                ->groupBy(DB::raw('DATE(scan_time)'))
                ->orderBy(DB::raw('DATE(scan_time)'))
                ->selectRaw('DATE(scan_time) as work_date, MIN(scan_time) as min_time, MAX(scan_time) as max_time')
                ->get();

            if ($logAggRows->count() > 0) {
                $existingDailyRows = DB::table('attendance_daily')
                    ->where('employee_id', (int) $selectedEmployee->id)
                    ->whereRaw('date BETWEEN ? AND ?', [$startDate, $endDate])
                    ->get(['date', 'check_in', 'check_out', 'work_hours', 'no_overtime_permit']);
                $existingDailyMap = [];
                foreach ($existingDailyRows as $dr) {
                    $existingDailyMap[(string) $dr->date] = $dr;
                }

                foreach ($logAggRows as $agg) {
                    $workDate = (string) ($agg->work_date ?? '');
                    if ($workDate === '') {
                        continue;
                    }
                    $checkIn = (string) ($agg->min_time ?? '');
                    $checkOut = (string) ($agg->max_time ?? '');
                    if ($checkIn === '' || $checkOut === '') {
                        continue;
                    }

                    $existing = $existingDailyMap[$workDate] ?? null;
                    $isEmptyDaily = !$existing
                        || (
                            empty($existing->check_in)
                            && empty($existing->check_out)
                            && (float) ($existing->work_hours ?? 0) <= 0
                        );
                    if (!$isEmptyDaily) {
                        continue;
                    }

                    $diffHours = max(0, (strtotime($checkOut) - strtotime($checkIn)) / 3600);
                    $workHours = round($diffHours, 2);
                    $noOtPermit = (int) ($existing->no_overtime_permit ?? 0) === 1;
                    $calc = OvertimeCalculator::calculateForRecord(
                        $companyId,
                        $workDate,
                        $checkIn,
                        $checkOut,
                        $noOtPermit
                    );
                    $overtimeHours = round((float) ($calc['hours'] ?? 0), 2);

                    DB::statement('INSERT INTO attendance_daily (employee_id, date, check_in, check_out, work_hours, overtime_hours, no_overtime_permit, is_leave_excused, is_sick_doctor_excused)
                                   VALUES (?,?,?,?,?,?,?,?,?)
                                   ON DUPLICATE KEY UPDATE
                                     check_in = VALUES(check_in),
                                     check_out = VALUES(check_out),
                                     work_hours = VALUES(work_hours),
                                     overtime_hours = VALUES(overtime_hours),
                                     is_leave_excused = 0,
                                     is_sick_doctor_excused = 0', [
                        (int) $selectedEmployee->id,
                        $workDate,
                        $checkIn,
                        $checkOut,
                        $workHours,
                        $overtimeHours,
                        $noOtPermit ? 1 : 0,
                        0,
                        0,
                    ]);
                }
            }

            $dailyRows = DB::table('attendance_daily')
                ->where('employee_id', (int) $selectedEmployee->id)
                ->whereRaw('date BETWEEN ? AND ?', [$startDate, $endDate])
                ->orderBy('date')
                ->get([
                    'date',
                    'check_in',
                    'check_out',
                    'work_hours',
                    'overtime_hours',
                    DB::raw('COALESCE(no_overtime_permit, 0) as no_overtime_permit'),
                    DB::raw('COALESCE(is_leave_excused, 0) as is_leave_excused'),
                    DB::raw('COALESCE(is_sick_doctor_excused, 0) as is_sick_doctor_excused'),
                ]);
            $dailyMap = [];
            foreach ($dailyRows as $d) {
                $dailyMap[(string) $d->date] = $d;
            }

            $holidayRows = Holiday::query()
                ->where('company_id', $companyId)
                ->whereRaw('holiday_date BETWEEN ? AND ?', [$startDate, $endDate])
                ->get(['holiday_date', 'name']);
            $holidayMap = [];
            $holidayCutiBersamaMap = [];
            foreach ($holidayRows as $h) {
                $holidayDate = (string) $h->holiday_date;
                $holidayName = (string) ($h->name ?? 'Libur Nasional');
                $holidayMap[$holidayDate] = $holidayName;
                $holidayNameLower = mb_strtolower(trim($holidayName));
                $holidayCutiBersamaMap[$holidayDate] =
                    str_contains($holidayNameLower, 'cuti bersama') ||
                    str_contains($holidayNameLower, 'cuti lebaran');
            }

            $absenceRows = AbsenceRequest::query()
                ->where('company_id', $companyId)
                ->where('employee_id', (int) $selectedEmployee->id)
                ->where('status', 'Approved')
                ->whereDate('date_start', '<=', $endDate)
                ->whereDate('date_end', '>=', $startDate)
                ->whereIn('request_type', ['Cuti', 'Izin'])
                ->get(['date_start', 'date_end', 'request_type', 'reason']);
            $absenceMap = [];
            foreach ($absenceRows as $req) {
                $reqStart = (string) $req->date_start;
                $reqEnd = (string) $req->date_end;
                if ($reqStart === '' || $reqEnd === '') {
                    continue;
                }
                $cursor = max($startDate, $reqStart);
                $limit = min($endDate, $reqEnd);
                $type = trim((string) ($req->request_type ?? ''));
                $reason = mb_strtolower(trim((string) ($req->reason ?? '')));
                $isCutiBersamaGenerated =
                    str_contains($reason, 'cuti lebaran') ||
                    str_contains($reason, 'cuti bersama') ||
                    str_contains($reason, 'otomatis izin');

                while ($cursor <= $limit) {
                    $current = $absenceMap[$cursor] ?? null;
                    if ($current === null || ($current['request_type'] ?? '') !== 'Cuti') {
                        $absenceMap[$cursor] = [
                            'request_type' => $type !== '' ? $type : 'Izin',
                            'is_cuti_bersama' => $isCutiBersamaGenerated,
                        ];
                    } elseif ($isCutiBersamaGenerated) {
                        $absenceMap[$cursor]['is_cuti_bersama'] = true;
                    }
                    $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
                }
            }

            $workDays = [];
            if ($company && !empty($company->work_days_json)) {
                $decodedWorkDays = json_decode((string) $company->work_days_json, true);
                if (is_array($decodedWorkDays)) {
                    $workDays = array_values(array_filter(array_map(static function ($d) {
                        return trim((string) $d);
                    }, $decodedWorkDays), static function ($d) {
                        return $d !== '';
                    }));
                }
            }
            if (count($workDays) === 0) {
                $workDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            }
            $dayLabels = [
                'Mon' => 'Senin',
                'Tue' => 'Selasa',
                'Wed' => 'Rabu',
                'Thu' => 'Kamis',
                'Fri' => 'Jumat',
                'Sat' => 'Sabtu',
                'Sun' => 'Minggu',
            ];

            $cursor = $startDate;
            while ($cursor <= $endDate) {
                $dow = date('D', strtotime($cursor));
                $daily = $dailyMap[$cursor] ?? null;
                $absence = $absenceMap[$cursor] ?? null;
                $isNationalHoliday = isset($holidayMap[$cursor]);
                $isHolidayCutiBersama = (bool) ($holidayCutiBersamaMap[$cursor] ?? false);
                $isAbsenceCutiBersama = !empty($absence['is_cuti_bersama']);
                $isCutiBersama = $isAbsenceCutiBersama || $isHolidayCutiBersama;
                $isOffDay = !in_array($dow, $workDays, true);
                $hasAttendance = $daily && (
                    !empty($daily->check_in) ||
                    !empty($daily->check_out) ||
                    ((float) ($daily->work_hours ?? 0) > 0)
                );

                $status = 'Tidak Hadir';
                if ($hasAttendance) {
                    $status = 'Hadir';
                    $summary['hadir']++;
                } elseif ($isCutiBersama) {
                    $status = 'Cuti Bersama';
                    $summary['cuti_bersama']++;
                } elseif (($absence['request_type'] ?? '') === 'Cuti') {
                    $status = 'Cuti';
                    $summary['cuti']++;
                } elseif (($absence['request_type'] ?? '') === 'Izin') {
                    $status = 'Izin';
                    $summary['izin']++;
                } elseif ($isNationalHoliday) {
                    $status = 'Libur Nasional';
                    $summary['libur_nasional']++;
                } elseif ($isOffDay) {
                    $status = 'Libur Mingguan';
                    $summary['libur_mingguan']++;
                } else {
                    $summary['tidak_hadir']++;
                }

                $workHours = (float) ($daily->work_hours ?? 0);
                $overtimeHours = (float) ($daily->overtime_hours ?? 0);
                $summary['work_hours'] += $workHours;
                $summary['overtime_hours'] += $overtimeHours;
                $remark = $holidayMap[$cursor] ?? null;
                if (!$remark && $isCutiBersama) {
                    $remark = 'Cuti Bersama';
                }

                $rows[] = (object) [
                    'date' => $cursor,
                    'day_name' => $dayLabels[$dow] ?? $dow,
                    'check_in' => $daily->check_in ?? null,
                    'check_out' => $daily->check_out ?? null,
                    'work_hours' => $workHours,
                    'overtime_hours' => $overtimeHours,
                    'no_overtime_permit' => (int) ($daily->no_overtime_permit ?? 0),
                    'is_leave_excused' => (int) ($daily->is_leave_excused ?? 0),
                    'is_sick_doctor_excused' => (int) ($daily->is_sick_doctor_excused ?? 0),
                    'has_attendance' => $hasAttendance,
                    'status' => $status,
                    'holiday_name' => $remark,
                ];

                $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
            }
        }

        $periodLogCount = (int) DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereRaw('DATE(scan_time) BETWEEN ? AND ?', [$startDate, $endDate])
            ->count();
        if ($periodLogCount === 0) {
            $messages[] = 'Tidak ada log absensi pada periode cut-off ini.';
        }
        if ($latestLogDate) {
            $messages[] = 'Log terakhir tersedia pada ' . format_date_id($latestLogDate) . '.';
        }

        return view('modules.attendance.monthly_employee', compact(
            'user',
            'companyId',
            'companies',
            'company',
            'messages',
            'month',
            'year',
            'range',
            'employees',
            'employeeId',
            'selectedEmployee',
            'rows',
            'summary',
            'filterEmployee'
        ));
    }

    public function report(Request $request)
    {
        $user = current_user();
        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $params = [];
            if ($request->query('month')) {
                $params['month'] = $request->query('month');
            }
            if ($request->query('year')) {
                $params['year'] = $request->query('year');
            }
            return redirect()->route('attendance.report', $params);
        }
        $companyId = current_company_id();
        $companies = Company::orderBy('id')->get();

        $month = $request->query('month', date('m'));
        $year = $request->query('year', date('Y'));

        AttendanceService::ensureDailySchema();
        $rows = DB::table('employees as e')
            ->leftJoin('attendance_daily as d', function ($q) use ($month, $year) {
                $q->on('d.employee_id', '=', 'e.id')
                  ->whereRaw('MONTH(d.date) = ?', [(int) $month])
                  ->whereRaw('YEAR(d.date) = ?', [(int) $year]);
            })
            ->where('e.company_id', $companyId);
        $this->applyExcludeInactiveEmployeeStatuses($rows, 'e');
        $rows = $rows
            ->groupBy('e.id', 'e.nik', 'e.name')
            ->orderBy('e.name')
            ->select('e.nik', 'e.name', DB::raw('COUNT(d.date) as hadir'),
                DB::raw('SUM(CASE WHEN COALESCE(d.no_overtime_permit, 0) = 1 THEN 0 ELSE COALESCE(d.overtime_hours, 0) END) as overtime'))
            ->get();

        return view('modules.attendance.report', compact('user', 'companyId', 'companies', 'month', 'year', 'rows'));
    }
}

