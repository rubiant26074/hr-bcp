<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\SecurityRosterService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SecurityRosterController extends Controller
{
    public function index(Request $request)
    {
        $user = current_user();
        $month = (int) ($request->input('month', $request->query('month', date('n'))));
        $year = (int) ($request->input('year', $request->query('year', date('Y'))));
        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }
        if ($year < 2020 || $year > 2100) {
            $year = (int) date('Y');
        }

        if ((int) $request->query('download_template', 0) === 1) {
            return $this->downloadSecurityRosterTemplate($month, $year);
        }

        if (current_user_has_global_scope($user) && $request->has('set_company')) {
            session(['company_id' => (int) $request->query('set_company')]);
            $params = [];
            foreach (['month', 'year', 'q', 'period_mode', 'start_date', 'end_date', 'sort', 'dir'] as $k) {
                if ($request->query($k) !== null && $request->query($k) !== '') {
                    $params[$k] = $request->query($k);
                }
            }
            return redirect()->route('attendance.security_roster', $params);
        }

        $companyId = current_company_id();
        SecurityRosterService::ensureDefaultShiftDefinitionsForCompany($companyId);
        $companies = Company::orderBy('id')->get(['id', 'company_name']);
        $company = Company::find($companyId);
        $messages = [];

        $q = trim((string) ($request->input('q', $request->query('q', ''))));
        $periodMode = strtolower(trim((string) ($request->input('period_mode', $request->query('period_mode', 'cutoff')))));
        if (!in_array($periodMode, ['cutoff', 'date_range'], true)) {
            $periodMode = 'cutoff';
        }
        $startDateInput = trim((string) ($request->input('start_date', $request->query('start_date', ''))));
        $endDateInput = trim((string) ($request->input('end_date', $request->query('end_date', ''))));
        $sort = strtolower(trim((string) ($request->input('sort', $request->query('sort', 'tanggal')))));
        $dir = strtolower(trim((string) ($request->input('dir', $request->query('dir', 'asc')))));
        $allowedSort = ['tanggal', 'nik', 'nama', 'jabatan'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'tanggal';
        }
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }

        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        if ($periodMode === 'cutoff') {
            $range = $this->cutoffRangeByPeriod($month, $year);
            $periodStart = $range['start_date'];
            $periodEnd = $range['end_date'];
        } else {
            $periodStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateInput) ? $startDateInput : $monthStart;
            $periodEnd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateInput) ? $endDateInput : $monthEnd;
            if ($periodEnd < $periodStart) {
                $tmp = $periodStart;
                $periodStart = $periodEnd;
                $periodEnd = $tmp;
            }
        }

        if ((int) $request->query('download_pdf', 0) === 1) {
            return $this->downloadSecurityRosterPdf($companyId, $month, $year, $q, $periodMode, $periodStart, $periodEnd);
        }
        if ((int) $request->query('download_excel', 0) === 1) {
            return $this->downloadSecurityRosterExcel($companyId, $month, $year, $q, $periodMode, $periodStart, $periodEnd);
        }

        $hasDefs = Schema::hasTable('security_shift_definitions');
        $hasRoster = Schema::hasTable('security_rosters');
        $hasLogs = Schema::hasTable('security_roster_change_logs');
        $hasReqs = Schema::hasTable('security_shift_requests');
        if (!$hasDefs || !$hasRoster) {
            $messages[] = 'Tabel roster security belum tersedia. Jalankan migration terlebih dahulu.';
        }

        if ($request->isMethod('post') && $hasDefs && $hasRoster) {
            $action = trim((string) $request->input('action', ''));
            try {
                if ($action === 'generate') {
                    $anchor = trim((string) $request->input('anchor', '2026-04-28'));
                    $cycle = strtoupper(trim((string) $request->input('cycle', 'PPSSMMOO')));
                    $reason = trim((string) $request->input('reason', 'Generated from UI'));
                    $instructionRef = trim((string) $request->input('instruction_ref', ''));
                    $force = (int) $request->input('force', 0) === 1 ? '1' : '0';

                    Artisan::call('security:generate-roster', [
                        '--company_id' => $companyId,
                        '--month' => $month,
                        '--year' => $year,
                        '--anchor' => $anchor,
                        '--cycle' => $cycle,
                        '--force' => $force,
                        '--reason' => $reason,
                        '--instruction_ref' => $instructionRef,
                        '--actor_user_id' => (int) ($user['id'] ?? 0),
                    ]);

                    $messages[] = trim((string) Artisan::output()) !== ''
                        ? trim((string) Artisan::output())
                        : 'Generate roster selesai.';
                }

                if ($action === 'swap') {
                    $employeeA = (int) $request->input('employee_a', 0);
                    $employeeB = (int) $request->input('employee_b', 0);
                    $workDate = trim((string) $request->input('work_date', ''));
                    $reason = trim((string) $request->input('reason', 'Swap by UI'));
                    $instructionRef = trim((string) $request->input('instruction_ref', ''));

                    SecurityRosterService::applySwap(
                        $companyId,
                        $employeeA,
                        $employeeB,
                        $workDate,
                        $reason !== '' ? $reason : null,
                        $instructionRef !== '' ? $instructionRef : null,
                        (int) ($user['id'] ?? 0)
                    );
                    $messages[] = 'Swap roster berhasil disimpan.';
                }

                if ($action === 'replace') {
                    $fromEmployee = (int) $request->input('from_employee', 0);
                    $toEmployee = (int) $request->input('to_employee', 0);
                    $workDate = trim((string) $request->input('work_date', ''));
                    $reason = trim((string) $request->input('reason', 'Replace by UI'));
                    $instructionRef = trim((string) $request->input('instruction_ref', ''));

                    SecurityRosterService::applyReplace(
                        $companyId,
                        $fromEmployee,
                        $toEmployee,
                        $workDate,
                        $reason !== '' ? $reason : null,
                        $instructionRef !== '' ? $instructionRef : null,
                        (int) ($user['id'] ?? 0)
                    );
                    $messages[] = 'Replace roster berhasil disimpan.';
                }

                if ($action === 'manual_save') {
                    $employeeId = (int) $request->input('employee_id', 0);
                    $workDate = trim((string) $request->input('work_date', ''));
                    $shiftCode = strtoupper(trim((string) $request->input('shift_code', 'OFF')));
                    $siteGuard = trim((string) $request->input('site_guard', ''));
                    $reason = trim((string) $request->input('reason', 'Input manual dari UI'));
                    $instructionRef = trim((string) $request->input('instruction_ref', ''));
                    if ($siteGuard !== '') {
                        $reason = trim(($reason !== '' ? $reason . ' | ' : '') . 'Site: ' . $siteGuard);
                    }

                    SecurityRosterService::upsertManualRoster(
                        $companyId,
                        $employeeId,
                        $workDate,
                        $shiftCode,
                        $reason !== '' ? $reason : null,
                        $instructionRef !== '' ? $instructionRef : null,
                        (int) ($user['id'] ?? 0)
                    );
                    $messages[] = 'Roster manual berhasil disimpan.';
                }

                if ($action === 'delete_roster') {
                    $rosterId = (int) $request->input('roster_id', 0);
                    $reason = trim((string) $request->input('reason', 'Hapus roster dari UI'));
                    $instructionRef = trim((string) $request->input('instruction_ref', ''));

                    SecurityRosterService::deleteRoster(
                        $companyId,
                        $rosterId,
                        $reason !== '' ? $reason : null,
                        $instructionRef !== '' ? $instructionRef : null,
                        (int) ($user['id'] ?? 0)
                    );
                    $messages[] = 'Roster berhasil dihapus.';
                }

                if ($action === 'delete_roster_bulk') {
                    $rosterIds = $request->input('roster_ids', []);
                    $reason = trim((string) $request->input('reason', 'Hapus massal roster dari UI'));
                    $instructionRef = trim((string) $request->input('instruction_ref', ''));

                    $result = SecurityRosterService::deleteRosterBulk(
                        $companyId,
                        is_array($rosterIds) ? $rosterIds : [],
                        $reason !== '' ? $reason : null,
                        $instructionRef !== '' ? $instructionRef : null,
                        (int) ($user['id'] ?? 0)
                    );
                    $messages[] = 'Roster berhasil dihapus: ' . (int) ($result['deleted'] ?? 0) . ' data.';
                }

                if ($action === 'import_roster_xls') {
                    if (!$request->hasFile('roster_file')) {
                        throw new \RuntimeException('File import belum dipilih.');
                    }
                    $file = $request->file('roster_file');
                    if (!$file || !$file->isValid()) {
                        throw new \RuntimeException('Upload file gagal.');
                    }
                    $ext = strtolower((string) $file->getClientOriginalExtension());
                    if (!in_array($ext, ['xls', 'xlsx', 'csv'], true)) {
                        throw new \RuntimeException('Format file harus .xls, .xlsx, atau .csv');
                    }
                    if ($ext === 'csv') {
                        $result = $this->importSecurityRosterCsv(
                            $companyId,
                            $file->getPathname(),
                            (string) $file->getClientOriginalName(),
                            (int) ($user['id'] ?? 0),
                            $month,
                            $year
                        );
                    } else {
                        try {
                            $result = $this->importSecurityRosterSpreadsheet(
                                $companyId,
                                $file->getPathname(),
                                (string) $file->getClientOriginalName(),
                                (int) ($user['id'] ?? 0),
                                $month,
                                $year
                            );
                        } catch (\Throwable $excelError) {
                            // Fallback: beberapa file .xls/.xlsx dari export tertentu sebenarnya CSV/teks.
                            $result = $this->importSecurityRosterCsv(
                                $companyId,
                                $file->getPathname(),
                                (string) $file->getClientOriginalName(),
                                (int) ($user['id'] ?? 0),
                                $month,
                                $year
                            );
                            $messages[] = 'Info: file dibaca dengan mode CSV fallback karena parser Excel gagal (' . $excelError->getMessage() . ').';
                        }
                    }
                    $messages[] = 'Import roster selesai. Berhasil ' . (int) $result['ok'] . ' baris, dilewati ' . (int) $result['skip'] . ' baris.';
                    foreach ($result['warnings'] as $warn) {
                        $messages[] = $warn;
                    }
                }
            } catch (\Throwable $e) {
                $messages[] = 'Proses gagal: ' . $e->getMessage();
            }
        }

        $securityEmployees = DB::table('employees')
            ->where('company_id', $companyId)
            ->where(function ($qq) {
                $qq->whereRaw("UPPER(COALESCE(position, '')) LIKE '%SECURITY%'")
                    ->orWhereRaw("UPPER(COALESCE(position, '')) LIKE '%SCURITY%'")
                    ->orWhereRaw("UPPER(COALESCE(position, '')) LIKE '%SATPAM%'");
            })
            ->orderBy('name')
            ->get(['id', 'nik', 'name', 'position', 'department']);
        $securitySites = collect(['DELTAMAS', 'D SILICON', 'CITARIK', 'MBJ']);

        $rows = collect();
        if ($hasRoster) {
            $rowsQuery = DB::table('security_rosters as r')
                ->join('employees as e', 'e.id', '=', 'r.employee_id')
                ->where('r.company_id', $companyId)
                ->whereBetween('r.work_date', [$periodStart, $periodEnd]);
            if ($q !== '') {
                $rowsQuery->where(function ($qq) use ($q) {
                    $qq->where('e.name', 'like', '%' . $q . '%')
                        ->orWhere('e.nik', 'like', '%' . $q . '%')
                        ->orWhere('r.shift_code', 'like', '%' . $q . '%');
                });
            }
            $rows = $rowsQuery
                ->when($sort === 'tanggal', function ($qq) use ($dir) {
                    $qq->orderBy('r.work_date', $dir)->orderBy('e.name');
                })
                ->when($sort === 'nik', function ($qq) use ($dir) {
                    $qq->orderBy('e.nik', $dir)->orderBy('r.work_date');
                })
                ->when($sort === 'nama', function ($qq) use ($dir) {
                    $qq->orderBy('e.name', $dir)->orderBy('r.work_date');
                })
                ->when($sort === 'jabatan', function ($qq) use ($dir) {
                    $qq->orderBy('e.position', $dir)->orderBy('e.name');
                })
                ->get([
                    'r.id',
                    'r.work_date',
                    'r.shift_code',
                    'r.shift_start_at',
                    'r.shift_end_at',
                    'r.source',
                    'r.note',
                    'e.id as employee_id',
                    'e.nik',
                    'e.name',
                    'e.department',
                    'e.position',
                ]);
            $rows = $rows->map(function ($r) {
                $site = trim((string) ($r->department ?? ''));
                if (preg_match('/Site:\s*([^|]+)/i', (string) ($r->note ?? ''), $m)) {
                    $site = trim((string) $m[1]);
                }
                $r->site_display = $site !== '' ? $site : '-';
                return $r;
            });
        }

        $stats = [
            'total' => (int) $rows->count(),
            'p' => (int) $rows->where('shift_code', 'P')->count(),
            's' => (int) $rows->where('shift_code', 'S')->count(),
            'm' => (int) $rows->where('shift_code', 'M')->count(),
            'off' => (int) $rows->where('shift_code', 'OFF')->count(),
        ];

        return view('modules.attendance.security_roster', compact(
            'user',
            'companyId',
            'companies',
            'company',
            'messages',
            'month',
            'year',
            'q',
            'periodMode',
            'startDateInput',
            'endDateInput',
            'sort',
            'dir',
            'periodStart',
            'periodEnd',
            'hasDefs',
            'hasRoster',
            'hasLogs',
            'hasReqs',
            'securityEmployees',
            'securitySites',
            'rows',
            'stats'
        ));
    }

    private function cutoffRangeByPeriod(int $month, int $year): array
    {
        $month = max(1, min(12, $month));
        $year = max(2000, min(2100, $year));

        $periodEnd = new \DateTime(sprintf('%04d-%02d-20', $year, $month));
        $periodStart = (clone $periodEnd)->modify('-1 month')->modify('+1 day');
        return [
            'start_date' => $periodStart->format('Y-m-d'),
            'end_date' => $periodEnd->format('Y-m-d'),
            'label' => $periodStart->format('d/m/Y') . ' - ' . $periodEnd->format('d/m/Y'),
        ];
    }

    private function importSecurityRosterSpreadsheet(int $companyId, string $path, string $originalName, int $actorUserId, int $month, int $year): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'ZipArchive') !== false) {
                throw new \RuntimeException('File Excel tidak dapat dibaca karena ekstensi PHP zip belum aktif di server. Solusi cepat: Save As ke CSV lalu import CSV.');
            }
            throw new \RuntimeException('File Excel tidak dapat dibaca: ' . $msg);
        }

        $sheet = $spreadsheet->getSheet(0);
        $highestRow = (int) $sheet->getHighestDataRow();
        $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        $rows = [];
        for ($r = 1; $r <= $highestRow; $r++) {
            $line = [];
            for ($c = 1; $c <= $highestCol; $c++) {
                $line[$c] = trim((string) $sheet->getCellByColumnAndRow($c, $r)->getFormattedValue());
            }
            $rows[$r] = $line;
        }

        $mode = $this->detectRosterImportMode($rows, $highestRow, $highestCol);
        if ($mode === 'list') {
            return $this->importRosterListMode($companyId, $sheet, $rows, $actorUserId, $originalName);
        }
        if ($mode === 'matrix') {
            return $this->importRosterMatrixMode($companyId, $sheet, $rows, $actorUserId, $originalName, $month, $year);
        }
        if ($mode === 'schedule_grid') {
            return $this->importRosterScheduleGridMode($companyId, $sheet, $rows, $actorUserId, $originalName, $month, $year);
        }

        throw new \RuntimeException('Format tabel tidak dikenali. Gunakan format list (Tanggal/NIK/Nama/Shift) atau matrix (NIK/Nama + kolom tanggal).');
    }

    private function detectRosterImportMode(array $rows, int $highestRow, int $highestCol): ?string
    {
        for ($r = 1; $r <= min($highestRow, 20); $r++) {
            $map = [];
            for ($c = 1; $c <= $highestCol; $c++) {
                $key = $this->normHeader((string) ($rows[$r][$c] ?? ''));
                if ($key !== '') {
                    $map[$key] = $c;
                }
            }
            $hasDate = isset($map['tanggal']) || isset($map['date']) || isset($map['tgl']);
            $hasPerson = isset($map['nik']) || isset($map['nama']) || isset($map['name']);
            $hasShift = isset($map['shift']) || isset($map['sift']) || isset($map['kode_shift']) || isset($map['kode']);
            if ($hasDate && $hasPerson && $hasShift) {
                return 'list';
            }
        }

        for ($r = 1; $r <= min($highestRow, 30); $r++) {
            $nikCol = 0;
            $namaCol = 0;
            $dateCount = 0;
            for ($c = 1; $c <= $highestCol; $c++) {
                $v = (string) ($rows[$r][$c] ?? '');
                $k = $this->normHeader($v);
                if ($k === 'nik') {
                    $nikCol = $c;
                }
                if ($k === 'nama' || $k === 'name') {
                    $namaCol = $c;
                }
                if ($this->tryParseDateCellString($v) !== null) {
                    $dateCount++;
                }
            }
            if (($nikCol > 0 || $namaCol > 0) && $dateCount >= 7) {
                return 'matrix';
            }
        }

        for ($r = 1; $r <= min($highestRow, 30); $r++) {
            for ($c = 1; $c <= $highestCol; $c++) {
                $k = $this->normHeader((string) ($rows[$r][$c] ?? ''));
                if (in_array($k, ['tanggal', 'date', 'tgl'], true)) {
                    $dayCount = 0;
                    for ($cc = $c + 1; $cc <= $highestCol; $cc++) {
                        $v = trim((string) ($rows[$r][$cc] ?? ''));
                        if (preg_match('/^\d{1,2}$/', $v) && (int) $v >= 1 && (int) $v <= 31) {
                            $dayCount++;
                        }
                    }
                    if ($dayCount >= 7) {
                        return 'schedule_grid';
                    }
                }
            }
        }

        return null;
    }

    private function importSecurityRosterCsv(int $companyId, string $path, string $originalName, int $actorUserId, int $month, int $year): array
    {
        $rows = [];
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('File CSV tidak dapat dibaca.');
        }
        $raw = $this->normalizeCsvEncoding((string) $raw);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        // Fast path: parser khusus format CSV jadwal security (baris TANGGAL;28;29;...)
        $direct = $this->importSecurityRosterCsvDirectSchedule($companyId, (string) $raw, $actorUserId, $originalName, $month, $year);
        if ($direct !== null) {
            return $direct;
        }

        $delimiter = $this->detectCsvDelimiter($raw);
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);

        $r = 0;
        while (($cols = fgetcsv($fh, 0, $delimiter)) !== false) {
            $r++;
            $line = [];
            $c = 0;
            foreach ((array) $cols as $v) {
                $c++;
                $line[$c] = trim((string) $v);
            }
            $rows[$r] = $line;
        }
        fclose($fh);

        $highestRow = count($rows);
        $highestCol = 0;
        foreach ($rows as $line) {
            $highestCol = max($highestCol, count((array) $line));
        }
        // Fallback: beberapa server/locale menyimpan CSV sebagai 1 kolom berisi ';'
        // meskipun delimiter auto-detect gagal pada pass pertama.
        if ($highestCol <= 1) {
            $reRows = [];
            foreach ($rows as $ri => $line) {
                $rawLine = (string) (($line[1] ?? '') ?: '');
                if ($rawLine === '') {
                    $reRows[$ri] = [1 => ''];
                    continue;
                }
                if (str_contains($rawLine, ';')) {
                    $parts = str_getcsv($rawLine, ';');
                    $tmp = [];
                    $ci = 0;
                    foreach ($parts as $p) {
                        $ci++;
                        $tmp[$ci] = trim((string) $p);
                    }
                    $reRows[$ri] = $tmp;
                } else {
                    $reRows[$ri] = [1 => trim($rawLine)];
                }
            }
            $rows = $reRows;
            $highestRow = count($rows);
            $highestCol = $this->maxColFromRows($rows);
        }
        if ($highestRow === 0 || $highestCol === 0) {
            throw new \RuntimeException('File CSV kosong.');
        }

        $exact = $this->importRosterExactSecurityScheduleIfMatch($companyId, $rows, $actorUserId, $originalName, $month, $year);
        if ($exact !== null) {
            return $exact;
        }

        $mode = $this->detectRosterImportMode($rows, $highestRow, $highestCol);
        if ($mode === 'list') {
            return $this->importRosterListMode($companyId, null, $rows, $actorUserId, $originalName);
        }
        if ($mode === 'matrix') {
            return $this->importRosterMatrixMode($companyId, null, $rows, $actorUserId, $originalName, $month, $year);
        }
        if ($mode === 'schedule_grid') {
            return $this->importRosterScheduleGridMode($companyId, null, $rows, $actorUserId, $originalName, $month, $year);
        }

        throw new \RuntimeException('Format CSV tidak dikenali. Gunakan format tabel jadwal/list yang sama.');
    }

    private function importSecurityRosterCsvDirectSchedule(int $companyId, string $raw, int $actorUserId, string $originalName, int $month, int $year): ?array
    {
        $lines = preg_split("/\r\n|\n|\r/", $raw);
        if (!$lines || count($lines) === 0) {
            return null;
        }
        foreach ([';', ',', "\t", '|'] as $delim) {
            $split = [];
            foreach ($lines as $line) {
                $split[] = str_getcsv((string) $line, $delim);
            }

            $tanggalRow = -1;
            $dateByCol = [];
            $tanggalCol = -1;
            foreach ($split as $ri => $cols) {
                $colCount = count($cols);
                for ($c = 0; $c < $colCount; $c++) {
                    $key = $this->normHeader((string) ($cols[$c] ?? ''));
                    if (!in_array($key, ['tanggal', 'tgl', 'date'], true)) {
                        continue;
                    }
                    $tmpDayCols = [];
                    for ($cc = $c + 1; $cc < $colCount; $cc++) {
                        $v = trim((string) ($cols[$cc] ?? ''));
                        if (preg_match('/^\d{1,2}$/', $v) && (int) $v >= 1 && (int) $v <= 31) {
                            $tmpDayCols[$cc + 1] = (int) $v;
                        }
                    }
                    if (count($tmpDayCols) >= 7) {
                        $tanggalRow = $ri;
                        $tanggalCol = $c + 1;
                        $dateByCol = $this->buildDatesFromDayNumbers($tmpDayCols, $month, $year);
                        break 2;
                    }
                }
            }

            if ($tanggalRow < 0 || empty($dateByCol)) {
                continue;
            }

            $ok = 0;
            $skip = 0;
            $warnings = [];

            for ($ri = $tanggalRow + 1; $ri < count($split); $ri++) {
                $cols = $split[$ri];
                $name = trim((string) ($cols[$tanggalCol - 1] ?? ''));
                if ($name === '') {
                    // fallback: ambil text terpanjang di kiri kolom shift
                    $best = '';
                    for ($i = 0; $i < $tanggalCol - 1; $i++) {
                        $v = trim((string) ($cols[$i] ?? ''));
                        if (preg_match('/[A-Za-z]/', $v) && strlen($v) > strlen($best)) {
                            $best = $v;
                        }
                    }
                    $name = $best;
                }
                if ($name === '') {
                    continue;
                }
                $nameUp = strtoupper($name);
                if (str_contains($nameUp, 'NOTASI') || in_array($nameUp, ['PAGI', 'SIANG', 'MALAM', 'OFF'], true)) {
                    continue;
                }

                $employeeId = $this->findSecurityEmployeeId($companyId, '', $name);
                if (!$employeeId) {
                    $warnings[] = 'Baris ' . ($ri + 1) . ': karyawan tidak ditemukan [' . $name . ']';
                    $skip += count($dateByCol);
                    continue;
                }
                $siteFromRow = trim((string) ($cols[0] ?? ''));

                foreach ($dateByCol as $col1 => $workDate) {
                    $shiftRaw = trim((string) ($cols[$col1 - 1] ?? ''));
                    $shiftCode = $this->parseShiftCode($shiftRaw);
                    if ($shiftCode === null) {
                        $skip++;
                        continue;
                    }
                    SecurityRosterService::upsertManualRoster(
                        $companyId,
                        $employeeId,
                        $workDate,
                        $shiftCode,
                        'Import CSV jadwal security (direct): ' . $originalName . ($siteFromRow !== '' ? ' | Site: ' . $siteFromRow : ''),
                        null,
                        $actorUserId
                    );
                    $ok++;
                }
            }

            return ['ok' => $ok, 'skip' => $skip, 'warnings' => $warnings];
        }

        return null;
    }

    private function importRosterExactSecurityScheduleIfMatch(int $companyId, array $rows, int $actorUserId, string $originalName, int $month, int $year): ?array
    {
        $highestRow = count($rows);
        $highestCol = $this->maxColFromRows($rows);
        $tanggalRow = 0;
        $tanggalCol = 0;
        $dayCols = [];

        for ($r = 1; $r <= min($highestRow, 30); $r++) {
            for ($c = 1; $c <= $highestCol; $c++) {
                if ($this->normHeader((string) ($rows[$r][$c] ?? '')) !== 'tanggal') {
                    continue;
                }
                $tmp = [];
                for ($cc = $c + 1; $cc <= $highestCol; $cc++) {
                    $v = trim((string) ($rows[$r][$cc] ?? ''));
                    if (preg_match('/^\d{1,2}$/', $v) && (int) $v >= 1 && (int) $v <= 31) {
                        $tmp[$cc] = (int) $v;
                    }
                }
                if (count($tmp) >= 10) {
                    $tanggalRow = $r;
                    $tanggalCol = $c;
                    $dayCols = $tmp;
                    break 2;
                }
            }
        }

        if ($tanggalRow === 0) {
            return null;
        }

        $dateByCol = $this->buildDatesFromDayNumbers($dayCols, $month, $year);
        $ok = 0;
        $skip = 0;
        $warnings = [];

        for ($r = $tanggalRow + 1; $r <= $highestRow; $r++) {
            $name = trim((string) ($rows[$r][$tanggalCol] ?? ''));
            $noVal = trim((string) ($rows[$r][$tanggalCol - 1] ?? ''));

            if ($name === '' && $noVal === '') {
                continue;
            }

            $nameUp = strtoupper($name);
            if ($nameUp === 'NOTASI' || str_contains($nameUp, 'NOTASI') || in_array($nameUp, ['PAGI', 'SIANG', 'MALAM', 'OFF'], true)) {
                continue;
            }

            if ($name === '' || preg_match('/^\d+$/', $name)) {
                $skip += count($dateByCol);
                continue;
            }

            $employeeId = $this->findSecurityEmployeeId($companyId, '', $name);
            if (!$employeeId) {
                $warnings[] = 'Baris ' . $r . ': karyawan tidak ditemukan [' . $name . ']';
                $skip += count($dateByCol);
                continue;
            }

            foreach ($dateByCol as $c => $workDate) {
                $shiftRaw = trim((string) ($rows[$r][$c] ?? ''));
                $shiftCode = $this->parseShiftCode($shiftRaw);
                if ($shiftCode === null) {
                    $skip++;
                    continue;
                }
                SecurityRosterService::upsertManualRoster(
                    $companyId,
                    $employeeId,
                    $workDate,
                    $shiftCode,
                    'Import CSV jadwal security: ' . $originalName,
                    null,
                    $actorUserId
                );
                $ok++;
            }
        }

        return ['ok' => $ok, 'skip' => $skip, 'warnings' => $warnings];
    }

    private function detectCsvDelimiter(string $raw): string
    {
        $sample = '';
        $parts = preg_split("/\r\n|\n|\r/", $raw);
        $limit = min(8, count($parts));
        for ($i = 0; $i < $limit; $i++) {
            $sample .= $parts[$i] . "\n";
        }

        $candidates = [',', ';', "\t", '|'];
        $best = ',';
        $bestScore = -1;
        foreach ($candidates as $d) {
            $lines = preg_split("/\r\n|\n|\r/", trim($sample));
            $score = 0;
            foreach ($lines as $line) {
                if ($line === '') {
                    continue;
                }
                $count = count(str_getcsv($line, $d));
                if ($count > 1) {
                    $score += $count;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $d;
            }
        }
        return $best;
    }

    private function importRosterListMode(int $companyId, $sheet, array $rows, int $actorUserId, string $originalName): array
    {
        $highestRow = is_object($sheet) ? (int) $sheet->getHighestDataRow() : count($rows);
        $highestCol = is_object($sheet)
            ? \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn())
            : $this->maxColFromRows($rows);
        $headerRow = 0;
        $colDate = 0;
        $colNIK = 0;
        $colName = 0;
        $colShift = 0;

        for ($r = 1; $r <= min($highestRow, 20); $r++) {
            for ($c = 1; $c <= $highestCol; $c++) {
                $k = $this->normHeader((string) ($rows[$r][$c] ?? ''));
                if (in_array($k, ['tanggal', 'date', 'tgl'], true)) {
                    $colDate = $c;
                } elseif ($k === 'nik') {
                    $colNIK = $c;
                } elseif (in_array($k, ['nama', 'name'], true)) {
                    $colName = $c;
                } elseif (in_array($k, ['shift', 'sift', 'kode_shift', 'kode'], true)) {
                    $colShift = $c;
                }
            }
            if ($colDate > 0 && ($colNIK > 0 || $colName > 0) && $colShift > 0) {
                $headerRow = $r;
                break;
            }
        }

        if ($headerRow === 0) {
            throw new \RuntimeException('Header list tidak ditemukan.');
        }

        $ok = 0;
        $skip = 0;
        $warnings = [];
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $dateVal = is_object($sheet) ? $sheet->getCellByColumnAndRow($colDate, $r)->getValue() : ($rows[$r][$colDate] ?? null);
            $dateTxt = (string) ($rows[$r][$colDate] ?? '');
            $workDate = $this->parseWorkDateFromCell($dateVal, $dateTxt);
            $shiftRaw = (string) ($rows[$r][$colShift] ?? '');
            $shiftCode = $this->parseShiftCode($shiftRaw);
            $nik = $colNIK > 0 ? trim((string) ($rows[$r][$colNIK] ?? '')) : '';
            $name = $colName > 0 ? trim((string) ($rows[$r][$colName] ?? '')) : '';
            $siteFromRow = trim((string) ($rows[$r][1] ?? ''));

            if ($workDate === null || $shiftCode === null || ($nik === '' && $name === '')) {
                $skip++;
                continue;
            }

            $employeeId = $this->findSecurityEmployeeId($companyId, $nik, $name);
            if (!$employeeId) {
                $display = $name !== '' ? $name : $nik;
                $warnings[] = 'Baris ' . $r . ': karyawan tidak ditemukan [' . $display . ']';
                $skip++;
                continue;
            }

            SecurityRosterService::upsertManualRoster(
                $companyId,
                $employeeId,
                $workDate,
                $shiftCode,
                'Import XLS: ' . $originalName . ($siteFromRow !== '' ? ' | Site: ' . $siteFromRow : ''),
                null,
                $actorUserId
            );
            $ok++;
        }

        return ['ok' => $ok, 'skip' => $skip, 'warnings' => $warnings];
    }

    private function importRosterMatrixMode(int $companyId, $sheet, array $rows, int $actorUserId, string $originalName, int $month, int $year): array
    {
        $highestRow = is_object($sheet) ? (int) $sheet->getHighestDataRow() : count($rows);
        $highestCol = is_object($sheet)
            ? \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn())
            : $this->maxColFromRows($rows);
        $headerRow = 0;
        $colNIK = 0;
        $colName = 0;
        $dateCols = [];

        for ($r = 1; $r <= min($highestRow, 30); $r++) {
            $tmpNIK = 0;
            $tmpName = 0;
            $tmpDateCols = [];
            for ($c = 1; $c <= $highestCol; $c++) {
                $k = $this->normHeader((string) ($rows[$r][$c] ?? ''));
                if ($k === 'nik') {
                    $tmpNIK = $c;
                }
                if ($k === 'nama' || $k === 'name') {
                    $tmpName = $c;
                }
                $dateTxt = (string) ($rows[$r][$c] ?? '');
                $parsed = $this->tryParseDateCellString($dateTxt);
                if ($parsed !== null) {
                    $tmpDateCols[$c] = $parsed;
                }
            }
            if (($tmpNIK > 0 || $tmpName > 0) && count($tmpDateCols) >= 7) {
                $headerRow = $r;
                $colNIK = $tmpNIK;
                $colName = $tmpName;
                $dateCols = $tmpDateCols;
                break;
            }
        }

        if ($headerRow === 0 || count($dateCols) === 0) {
            throw new \RuntimeException('Header matrix tidak ditemukan.');
        }

        $ok = 0;
        $skip = 0;
        $warnings = [];
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $nik = $colNIK > 0 ? trim((string) ($rows[$r][$colNIK] ?? '')) : '';
            $name = $colName > 0 ? trim((string) ($rows[$r][$colName] ?? '')) : '';
            $siteFromRow = trim((string) ($rows[$r][1] ?? ''));
            if ($nik === '' && $name === '') {
                continue;
            }

            $employeeId = $this->findSecurityEmployeeId($companyId, $nik, $name);
            if (!$employeeId) {
                $display = $name !== '' ? $name : $nik;
                $warnings[] = 'Baris ' . $r . ': karyawan tidak ditemukan [' . $display . ']';
                $skip += count($dateCols);
                continue;
            }

            foreach ($dateCols as $c => $workDate) {
                $shiftRaw = trim((string) ($rows[$r][$c] ?? ''));
                $shiftCode = $this->parseShiftCode($shiftRaw);
                if ($shiftCode === null) {
                    $skip++;
                    continue;
                }
                SecurityRosterService::upsertManualRoster(
                    $companyId,
                    $employeeId,
                    $workDate,
                    $shiftCode,
                    'Import XLS: ' . $originalName . ($siteFromRow !== '' ? ' | Site: ' . $siteFromRow : ''),
                    null,
                    $actorUserId
                );
                $ok++;
            }
        }

        return ['ok' => $ok, 'skip' => $skip, 'warnings' => $warnings];
    }

    private function importRosterScheduleGridMode(int $companyId, $sheet, array $rows, int $actorUserId, string $originalName, int $month, int $year): array
    {
        $highestRow = is_object($sheet) ? (int) $sheet->getHighestDataRow() : count($rows);
        $highestCol = is_object($sheet)
            ? \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn())
            : $this->maxColFromRows($rows);
        $headerRow = 0;
        $headerCol = 0;
        $dayCols = [];

        for ($r = 1; $r <= min($highestRow, 30); $r++) {
            for ($c = 1; $c <= $highestCol; $c++) {
                $k = $this->normHeader((string) ($rows[$r][$c] ?? ''));
                if (!in_array($k, ['tanggal', 'date', 'tgl'], true)) {
                    continue;
                }
                $tmp = [];
                for ($cc = $c + 1; $cc <= $highestCol; $cc++) {
                    $v = trim((string) ($rows[$r][$cc] ?? ''));
                    if (preg_match('/^\d{1,2}$/', $v) && (int) $v >= 1 && (int) $v <= 31) {
                        $tmp[$cc] = (int) $v;
                    }
                }
                if (count($tmp) >= 7) {
                    $headerRow = $r;
                    $headerCol = $c;
                    $dayCols = $tmp;
                    break 2;
                }
            }
        }

        if ($headerRow === 0 || empty($dayCols)) {
            throw new \RuntimeException('Header grid jadwal tidak ditemukan.');
        }

        $dateByCol = $this->buildDatesFromDayNumbers($dayCols, $month, $year);
        $nameCol = max(1, $headerCol);
        $nikCol = max(1, $headerCol - 1);
        $firstShiftCol = (int) min(array_keys($dateByCol));

        $ok = 0;
        $skip = 0;
        $warnings = [];
        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $name = trim((string) ($rows[$r][$nameCol] ?? ''));
            $nik = trim((string) ($rows[$r][$nikCol] ?? ''));
            $siteFromRow = trim((string) ($rows[$r][1] ?? ''));

            // Fallback parser: ambil identitas dari blok kiri sebelum kolom shift.
            // Ini menghindari kasus kolom NO (1,2,3,...) terbaca sebagai NIK.
            if ($name === '' || preg_match('/^\d+$/', $name)) {
                $leftCells = [];
                for ($c = 1; $c < $firstShiftCol; $c++) {
                    $v = trim((string) ($rows[$r][$c] ?? ''));
                    if ($v !== '') {
                        $leftCells[$c] = $v;
                    }
                }
                foreach ($leftCells as $v) {
                    if ($nik === '' && preg_match('/^[A-Z]{1,4}\d{4,}$/i', $v)) {
                        $nik = $v;
                    }
                }
                // pilih sel teks paling panjang yang mengandung huruf sebagai nama
                $bestName = '';
                foreach ($leftCells as $v) {
                    if (!preg_match('/[A-Za-z]/', $v)) {
                        continue;
                    }
                    $vv = strtoupper($v);
                    if (in_array($vv, ['NO', 'HARI', 'TANGGAL'], true)) {
                        continue;
                    }
                    if (mb_strlen($v) > mb_strlen($bestName)) {
                        $bestName = $v;
                    }
                }
                if ($bestName !== '') {
                    $name = $bestName;
                }
            }
            if ($name === '' && $nik === '') {
                continue;
            }

            $nameUpper = strtoupper($name);
            if (str_contains($nameUpper, 'NOTASI') || str_contains($nameUpper, 'OFF=') || str_contains($nameUpper, 'PAGI') || str_contains($nameUpper, 'SIANG') || str_contains($nameUpper, 'MALAM')) {
                continue;
            }

            $employeeId = $this->findSecurityEmployeeId($companyId, $nik, $name);
            if (!$employeeId) {
                $display = $name !== '' ? $name : $nik;
                $warnings[] = 'Baris ' . $r . ': karyawan tidak ditemukan [' . $display . ']';
                $skip += count($dateByCol);
                continue;
            }

            foreach ($dateByCol as $c => $workDate) {
                $shiftRaw = trim((string) ($rows[$r][$c] ?? ''));
                $shiftCode = $this->parseShiftCode($shiftRaw);
                if ($shiftCode === null) {
                    $skip++;
                    continue;
                }
                SecurityRosterService::upsertManualRoster(
                    $companyId,
                    $employeeId,
                    $workDate,
                    $shiftCode,
                    'Import XLS grid: ' . $originalName . ($siteFromRow !== '' ? ' | Site: ' . $siteFromRow : ''),
                    null,
                    $actorUserId
                );
                $ok++;
            }
        }

        return ['ok' => $ok, 'skip' => $skip, 'warnings' => $warnings];
    }

    private function buildDatesFromDayNumbers(array $dayCols, int $month, int $year): array
    {
        $cols = array_keys($dayCols);
        sort($cols);
        $anchorCol = 0;
        foreach ($cols as $c) {
            if ((int) $dayCols[$c] === 1) {
                $anchorCol = $c;
                break;
            }
        }
        if ($anchorCol === 0) {
            $firstCol = $cols[0];
            $d = (int) $dayCols[$firstCol];
            if (!checkdate($month, $d, $year)) {
                $d = 1;
            }
            $anchorDate = new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $d));
            $anchorCol = $firstCol;
        } else {
            $anchorDate = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        }

        $dateByCol = [];
        $dateByCol[$anchorCol] = $anchorDate->format('Y-m-d');

        $cursor = clone $anchorDate;
        for ($c = $anchorCol + 1; isset($dayCols[$c]); $c++) {
            $cursor->modify('+1 day');
            $dateByCol[$c] = $cursor->format('Y-m-d');
        }

        $cursor = clone $anchorDate;
        for ($c = $anchorCol - 1; isset($dayCols[$c]); $c--) {
            $cursor->modify('-1 day');
            $dateByCol[$c] = $cursor->format('Y-m-d');
        }

        ksort($dateByCol);
        return $dateByCol;
    }

    private function findSecurityEmployeeId(int $companyId, string $nik, string $name): int
    {
        $nik = trim($nik);
        $name = trim($name);
        $nameNorm = $this->normalizePersonName($name);

        // CSV jadwal sering memakai kolom NO (1,2,3,...) tepat di kiri nama.
        // Angka pendek murni numerik tidak diperlakukan sebagai NIK.
        if (preg_match('/^\d{1,5}$/', $nik)) {
            $nik = '';
        }

        if ($nik !== '') {
            $emp = DB::table('employees')
                ->where('company_id', $companyId)
                ->where('nik', $nik)
                ->first(['id']);
            if ($emp && isset($emp->id)) {
                return (int) $emp->id;
            }
        }

        if ($name !== '') {
            $emp = DB::table('employees')
                ->where('company_id', $companyId)
                ->whereRaw('UPPER(name)=?', [strtoupper($name)])
                ->first(['id']);
            if ($emp && isset($emp->id)) {
                return (int) $emp->id;
            }
            $emp = DB::table('employees')
                ->where('company_id', $companyId)
                ->where('name', 'like', '%' . $name . '%')
                ->orderBy('id')
                ->first(['id']);
            if ($emp && isset($emp->id)) {
                return (int) $emp->id;
            }

            // Token-based fallback match (lebih tahan singkatan/spasi/tanda baca)
            $candidates = DB::table('employees')
                ->where('company_id', $companyId)
                ->get(['id', 'name']);
            $targetTokens = $this->nameTokens($nameNorm);
            $bestId = 0;
            $bestScore = 0;
            foreach ($candidates as $cand) {
                $candNorm = $this->normalizePersonName((string) ($cand->name ?? ''));
                if ($candNorm === '') {
                    continue;
                }
                if ($candNorm === $nameNorm) {
                    return (int) $cand->id;
                }

                $score = 0;
                $candTokens = $this->nameTokens($candNorm);
                foreach ($targetTokens as $tk) {
                    if (in_array($tk, $candTokens, true)) {
                        $score++;
                    }
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestId = (int) $cand->id;
                }
            }
            if ($bestId > 0 && $bestScore >= max(1, min(2, count($targetTokens)))) {
                return $bestId;
            }
        }

        return 0;
    }

    private function parseShiftCode(string $value): ?string
    {
        $v = strtoupper(trim($value));
        if ($v === '') {
            return null;
        }
        $v = str_replace(['SHIFT', 'SIFT', ':', '-', '_', ' '], '', $v);
        if (in_array($v, ['P', 'PAGI'], true)) {
            return 'P';
        }
        if (in_array($v, ['S', 'SIANG'], true)) {
            return 'S';
        }
        if (in_array($v, ['M', 'MALAM', 'N'], true)) {
            return 'M';
        }
        if (in_array($v, ['OFF', 'O', 'LIBUR'], true)) {
            return 'OFF';
        }
        return null;
    }

    private function parseWorkDateFromCell($rawValue, string $displayValue): ?string
    {
        if (is_numeric($rawValue)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $rawValue)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }
        return $this->tryParseDateCellString($displayValue);
    }

    private function tryParseDateCellString(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        $v = str_replace('.', '/', $v);
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m)) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $v, $m)) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }
        $ts = strtotime($v);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }
        return null;
    }

    private function normHeader(string $v): string
    {
        $v = strtolower(trim($v));
        $v = str_replace(['.', ':', '-', '_', '/', '\\', ' '], '', $v);
        return $v;
    }

    private function normalizePersonName(string $v): string
    {
        $v = strtoupper(trim($v));
        $v = preg_replace('/[^A-Z0-9 ]+/u', ' ', $v);
        $v = preg_replace('/\s+/', ' ', (string) $v);
        return trim((string) $v);
    }

    private function nameTokens(string $v): array
    {
        if ($v === '') {
            return [];
        }
        $raw = explode(' ', $v);
        $tokens = [];
        foreach ($raw as $t) {
            $t = trim($t);
            if ($t === '' || strlen($t) < 2) {
                continue;
            }
            $tokens[] = $t;
        }
        return array_values(array_unique($tokens));
    }

    private function maxColFromRows(array $rows): int
    {
        $max = 0;
        foreach ($rows as $line) {
            $max = max($max, count((array) $line));
        }
        return $max;
    }

    private function normalizeCsvEncoding(string $raw): string
    {
        if ($raw === '') {
            return $raw;
        }
        // UTF-16LE BOM
        if (strncmp($raw, "\xFF\xFE", 2) === 0) {
            $converted = @mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
            return is_string($converted) ? $converted : $raw;
        }
        // UTF-16BE BOM
        if (strncmp($raw, "\xFE\xFF", 2) === 0) {
            $converted = @mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
            return is_string($converted) ? $converted : $raw;
        }
        // Heuristic: banyak null-byte => UTF-16
        if (strpos($raw, "\x00") !== false) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }
        return $raw;
    }

    private function downloadSecurityRosterTemplate(int $month, int $year)
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        $dayCount = (int) date('t', strtotime($start));
        $periodLabel = strtoupper(date('F', strtotime($start))) . ' ' . $year;

        $siteRows = [
            ['site' => 'DELTAMAS', 'no' => 1, 'name' => 'NAMA PERSONEL 1'],
            ['site' => '', 'no' => 2, 'name' => 'NAMA PERSONEL 2'],
            ['site' => '', 'no' => 3, 'name' => 'NAMA PERSONEL 3'],
            ['site' => '', 'no' => 4, 'name' => 'NAMA PERSONEL 4'],
            ['site' => 'D SILICON', 'no' => 5, 'name' => 'NAMA PERSONEL 5'],
            ['site' => '', 'no' => 6, 'name' => 'NAMA PERSONEL 6'],
            ['site' => '', 'no' => 7, 'name' => 'NAMA PERSONEL 7'],
            ['site' => '', 'no' => 8, 'name' => 'NAMA PERSONEL 8'],
            ['site' => 'CITARIK', 'no' => 9, 'name' => 'NAMA PERSONEL 9'],
            ['site' => '', 'no' => 10, 'name' => 'NAMA PERSONEL 10'],
            ['site' => '', 'no' => 11, 'name' => 'NAMA PERSONEL 11'],
            ['site' => '', 'no' => 12, 'name' => 'NAMA PERSONEL 12'],
            ['site' => '', 'no' => 13, 'name' => 'NAMA PERSONEL 13'],
        ];

        $dowMap = ['SUN' => 'MIN', 'MON' => 'SEN', 'TUE' => 'SEL', 'WED' => 'RAB', 'THU' => 'KAM', 'FRI' => 'JUM', 'SAT' => 'SAB'];
        $headersHari = ['SITE', 'NO', 'HARI'];
        $headersTanggal = ['', '', 'TANGGAL'];
        for ($d = 1; $d <= $dayCount; $d++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $en = strtoupper(date('D', strtotime($date)));
            $headersHari[] = $dowMap[$en] ?? $en;
            $headersTanggal[] = (string) $d;
        }

        $html = '<table border="1" cellspacing="0" cellpadding="4">';
        $html .= '<tr><td colspan="' . (3 + $dayCount) . '"><b>JADWAL SECURITY BCP-GROUP</b></td></tr>';
        $html .= '<tr><td colspan="' . (3 + $dayCount) . '"><b>PERIODE BULAN ' . e($periodLabel) . '</b></td></tr>';
        $html .= '<tr><td colspan="' . (3 + $dayCount) . '"></td></tr>';

        $html .= '<tr>';
        foreach ($headersHari as $h) {
            $html .= '<td><b>' . e($h) . '</b></td>';
        }
        $html .= '</tr>';

        $html .= '<tr>';
        foreach ($headersTanggal as $h) {
            $html .= '<td><b>' . e($h) . '</b></td>';
        }
        $html .= '</tr>';

        foreach ($siteRows as $row) {
            $html .= '<tr>';
            $html .= '<td>' . e((string) $row['site']) . '</td>';
            $html .= '<td>' . e((string) $row['no']) . '</td>';
            $html .= '<td>' . e((string) $row['name']) . '</td>';
            for ($d = 1; $d <= $dayCount; $d++) {
                $html .= '<td></td>';
            }
            $html .= '</tr>';
        }

        $html .= '<tr><td></td><td></td><td><b>NOTASI :</b></td>';
        for ($d = 1; $d <= $dayCount; $d++) {
            $html .= '<td></td>';
        }
        $html .= '</tr>';

        $notes = [
            'OFF = LIBUR',
            'P = PAGI (07:00 - 15:00)',
            'S = SIANG (15:00 - 23:00)',
            'M = MALAM (23:00 - 07:00)',
        ];
        foreach ($notes as $n) {
            $html .= '<tr><td></td><td></td><td>' . e($n) . '</td>';
            for ($d = 1; $d <= $dayCount; $d++) {
                $html .= '<td></td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        $filename = 'template_jadwal_security_' . $year . sprintf('%02d', $month) . '.xls';
        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'public',
            'Expires' => '0',
        ]);
    }

    private function downloadSecurityRosterPdf(
        int $companyId,
        int $month,
        int $year,
        string $q = '',
        string $periodMode = 'cutoff',
        ?string $periodStart = null,
        ?string $periodEnd = null
    )
    {
        if (!$periodStart || !$periodEnd) {
            $range = $this->resolvePeriodRange($periodMode, $month, $year, null, null);
            $periodStart = $range['start_date'];
            $periodEnd = $range['end_date'];
        }
        $dayCount = (int) ((strtotime($periodEnd) - strtotime($periodStart)) / 86400) + 1;

        $employeesQuery = DB::table('employees')
            ->where('company_id', $companyId)
            ->where(function ($qq) {
                $qq->whereRaw("UPPER(COALESCE(position, '')) LIKE '%SECURITY%'")
                    ->orWhereRaw("UPPER(COALESCE(position, '')) LIKE '%SCURITY%'")
                    ->orWhereRaw("UPPER(COALESCE(position, '')) LIKE '%SATPAM%'");
            });
        if ($q !== '') {
            $employeesQuery->where(function ($qq) use ($q) {
                $qq->where('name', 'like', '%' . $q . '%')
                    ->orWhere('nik', 'like', '%' . $q . '%')
                    ->orWhere('department', 'like', '%' . $q . '%')
                    ->orWhere('position', 'like', '%' . $q . '%');
            });
        }
        $employees = $employeesQuery
            ->orderBy('department')
            ->orderBy('name')
            ->get(['id', 'nik', 'name', 'department', 'position']);

        $employeeIds = $employees->pluck('id')->all();
        $rosterRows = collect();
        if (!empty($employeeIds)) {
            $rosterRows = DB::table('security_rosters')
                ->where('company_id', $companyId)
                ->whereIn('employee_id', $employeeIds)
                ->whereBetween('work_date', [$periodStart, $periodEnd])
                ->get(['employee_id', 'work_date', 'shift_code', 'note']);
        }

        $shiftMap = [];
        $siteByEmployee = [];
        foreach ($rosterRows as $rr) {
            $shiftMap[(int) $rr->employee_id . '|' . (string) $rr->work_date] = (string) $rr->shift_code;
            if (preg_match('/Site:\s*([^|]+)/i', (string) ($rr->note ?? ''), $m)) {
                $siteByEmployee[(int) $rr->employee_id] = trim((string) $m[1]);
            }
        }

        $days = [];
        $dowMap = ['SUN' => 'MIN', 'MON' => 'SEN', 'TUE' => 'SEL', 'WED' => 'RAB', 'THU' => 'KAM', 'FRI' => 'JUM', 'SAT' => 'SAB'];
        for ($d = 0; $d < $dayCount; $d++) {
            $date = date('Y-m-d', strtotime($periodStart . ' +' . $d . ' day'));
            $en = strtoupper(date('D', strtotime($date)));
            $days[] = [
                'date' => $date,
                'day_no' => (int) date('j', strtotime($date)),
                'dow' => $dowMap[$en] ?? $en,
            ];
        }

        $groups = [];
        foreach ($employees as $emp) {
            $site = trim((string) ($siteByEmployee[(int) $emp->id] ?? ''));
            if ($site === '') {
                $site = trim((string) ($emp->department ?? ''));
            }
            if ($site === '') {
                $site = '-';
            }
            if (!isset($groups[$site])) {
                $groups[$site] = [];
            }
            $rowShifts = [];
            foreach ($days as $d) {
                $key = (int) $emp->id . '|' . $d['date'];
                $rowShifts[] = $shiftMap[$key] ?? '';
            }
            $groups[$site][] = [
                'nik' => (string) $emp->nik,
                'name' => (string) $emp->name,
                'position' => (string) ($emp->position ?? ''),
                'shifts' => $rowShifts,
            ];
        }

        $company = Company::find($companyId);
        $html = view('modules.attendance.security_roster_pdf', [
            'company' => $company,
            'month' => $month,
            'year' => $year,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'days' => $days,
            'groups' => $groups,
        ])->render();

        $dompdf = new Dompdf([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A3', 'landscape');
        $dompdf->render();

        $filename = 'jadwal_security_' . $year . sprintf('%02d', $month) . '.pdf';
        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function downloadSecurityRosterExcel(
        int $companyId,
        int $month,
        int $year,
        string $q = '',
        string $periodMode = 'cutoff',
        ?string $periodStart = null,
        ?string $periodEnd = null
    )
    {
        if (!$periodStart || !$periodEnd) {
            $range = $this->resolvePeriodRange($periodMode, $month, $year, null, null);
            $periodStart = $range['start_date'];
            $periodEnd = $range['end_date'];
        }
        $dayCount = (int) ((strtotime($periodEnd) - strtotime($periodStart)) / 86400) + 1;

        $employeesQuery = DB::table('employees')
            ->where('company_id', $companyId)
            ->where(function ($qq) {
                $qq->whereRaw("UPPER(COALESCE(position, '')) LIKE '%SECURITY%'")
                    ->orWhereRaw("UPPER(COALESCE(position, '')) LIKE '%SCURITY%'")
                    ->orWhereRaw("UPPER(COALESCE(position, '')) LIKE '%SATPAM%'");
            });
        if ($q !== '') {
            $employeesQuery->where(function ($qq) use ($q) {
                $qq->where('name', 'like', '%' . $q . '%')
                    ->orWhere('nik', 'like', '%' . $q . '%')
                    ->orWhere('department', 'like', '%' . $q . '%')
                    ->orWhere('position', 'like', '%' . $q . '%');
            });
        }
        $employees = $employeesQuery
            ->orderBy('department')
            ->orderBy('name')
            ->get(['id', 'nik', 'name', 'department', 'position']);

        $employeeIds = $employees->pluck('id')->all();
        $rosterRows = collect();
        if (!empty($employeeIds)) {
            $rosterRows = DB::table('security_rosters')
                ->where('company_id', $companyId)
                ->whereIn('employee_id', $employeeIds)
                ->whereBetween('work_date', [$periodStart, $periodEnd])
                ->get(['employee_id', 'work_date', 'shift_code', 'note']);
        }

        $shiftMap = [];
        $siteByEmployee = [];
        foreach ($rosterRows as $rr) {
            $shiftMap[(int) $rr->employee_id . '|' . (string) $rr->work_date] = strtoupper((string) $rr->shift_code);
            if (preg_match('/Site:\s*([^|]+)/i', (string) ($rr->note ?? ''), $m)) {
                $siteByEmployee[(int) $rr->employee_id] = trim((string) $m[1]);
            }
        }

        $days = [];
        $dowMap = ['SUN' => 'MIN', 'MON' => 'SEN', 'TUE' => 'SEL', 'WED' => 'RAB', 'THU' => 'KAM', 'FRI' => 'JUM', 'SAT' => 'SAB'];
        for ($d = 0; $d < $dayCount; $d++) {
            $date = date('Y-m-d', strtotime($periodStart . ' +' . $d . ' day'));
            $en = strtoupper(date('D', strtotime($date)));
            $days[] = ['date' => $date, 'day_no' => (int) date('j', strtotime($date)), 'dow' => $dowMap[$en] ?? $en];
        }

        $groups = [];
        foreach ($employees as $emp) {
            $site = trim((string) ($siteByEmployee[(int) $emp->id] ?? ''));
            if ($site === '') {
                $site = trim((string) ($emp->department ?? ''));
            }
            if ($site === '') {
                $site = '-';
            }
            if (!isset($groups[$site])) {
                $groups[$site] = [];
            }
            $rowShifts = [];
            foreach ($days as $d) {
                $key = (int) $emp->id . '|' . $d['date'];
                $rowShifts[] = $shiftMap[$key] ?? '';
            }
            $groups[$site][] = ['name' => (string) $emp->name, 'shifts' => $rowShifts];
        }

        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->setTitle('Jadwal Security');
        $lastCol = 3 + $dayCount;
        $colRef = static function (int $col, int $row): string {
            return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
        };
        $set = static function ($sheetObj, int $col, int $row, $val) use ($colRef): void {
            $sheetObj->setCellValue($colRef($col, $row), $val);
        };
        $merge = static function ($sheetObj, int $c1, int $r1, int $c2, int $r2) use ($colRef): void {
            $sheetObj->mergeCells($colRef($c1, $r1) . ':' . $colRef($c2, $r2));
        };
        $style = static function ($sheetObj, int $c1, int $r1, int $c2, int $r2) use ($colRef) {
            return $sheetObj->getStyle($colRef($c1, $r1) . ':' . $colRef($c2, $r2));
        };

        $set($sheet, 1, 1, 'JADWAL SECURITY BCP-GROUP');
        $merge($sheet, 1, 1, $lastCol, 1);
        $set($sheet, 1, 2, 'PERIODE ' . date('d/m/Y', strtotime($periodStart)) . ' - ' . date('d/m/Y', strtotime($periodEnd)));
        $merge($sheet, 1, 2, $lastCol, 2);
        $sheet->getStyle('A1:A2')->getFont()->setBold(true);

        $row = 4;
        $set($sheet, 1, $row, 'SITE');
        $set($sheet, 2, $row, 'NO');
        $set($sheet, 3, $row, 'HARI');
        foreach ($days as $i => $d) {
            $set($sheet, 4 + $i, $row, $d['dow']);
        }
        $row++;
        $set($sheet, 3, $row, 'TANGGAL');
        foreach ($days as $i => $d) {
            $set($sheet, 4 + $i, $row, $d['day_no']);
        }

        $rowNo = 1;
        foreach ($groups as $site => $items) {
            foreach ($items as $idx => $it) {
                $row++;
                $set($sheet, 1, $row, $site);
                $set($sheet, 2, $row, $rowNo++);
                $set($sheet, 3, $row, $it['name']);
                foreach ($it['shifts'] as $i => $s) {
                    $col = 4 + $i;
                    $set($sheet, $col, $row, $s);
                    if ($s === 'OFF') {
                        $sheet->getStyle($colRef($col, $row))->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FF1A1A');
                        $sheet->getStyle($colRef($col, $row))->getFont()->getColor()->setRGB('FFFFFF');
                    } else {
                        $sheet->getStyle($colRef($col, $row))->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F7EED0');
                    }
                }
            }
        }

        $endDataRow = $row;
        $style($sheet, 1, 4, $lastCol, $endDataRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $style($sheet, 1, 4, $lastCol, 5)->getFont()->setBold(true);
        $style($sheet, 1, 4, $lastCol, 5)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $style($sheet, 3, 6, 3, $endDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(6);
        $sheet->getColumnDimension('C')->setWidth(28);
        for ($c = 4; $c <= $lastCol; $c++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setWidth(4);
        }

        $legendRow = $endDataRow + 2;
        $set($sheet, 3, $legendRow, 'NOTASI:');
        $set($sheet, 3, $legendRow + 1, 'OFF = LIBUR');
        $set($sheet, 3, $legendRow + 2, 'P = PAGI (07:00 - 15:00)');
        $set($sheet, 3, $legendRow + 3, 'S = SIANG (15:00 - 23:00)');
        $set($sheet, 3, $legendRow + 4, 'M = MALAM (23:00 - 07:00)');

        $writer = new Xlsx($sheet->getParent());
        $tmp = tempnam(sys_get_temp_dir(), 'roster_excel_');
        $writer->save($tmp);
        $filename = 'jadwal_security_' . $year . sprintf('%02d', $month) . '.xlsx';
        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function resolvePeriodRange(string $periodMode, int $month, int $year, ?string $startDateInput, ?string $endDateInput): array
    {
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        if ($periodMode === 'cutoff') {
            $range = $this->cutoffRangeByPeriod($month, $year);
            return ['start_date' => $range['start_date'], 'end_date' => $range['end_date']];
        }
        $periodStart = (is_string($startDateInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateInput)) ? $startDateInput : $monthStart;
        $periodEnd = (is_string($endDateInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDateInput)) ? $endDateInput : $monthEnd;
        if ($periodEnd < $periodStart) {
            $tmp = $periodStart;
            $periodStart = $periodEnd;
            $periodEnd = $tmp;
        }
        return ['start_date' => $periodStart, 'end_date' => $periodEnd];
    }
}
