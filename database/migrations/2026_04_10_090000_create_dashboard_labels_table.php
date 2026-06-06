<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('dashboard_labels')) {
            Schema::create('dashboard_labels', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(0);
                $table->string('label_key', 120);
                $table->text('label_value');
                $table->timestamps();

                $table->unique(['company_id', 'label_key'], 'uniq_dashboard_labels_company_key');
                $table->index(['company_id', 'label_key']);
            });
        }

        $now = now();
        $defaults = [
            'hero.kicker' => 'HR-BCP CONTROL CENTER',
            'hero.title' => 'Dashboard HR Group',
            'hero.sub_template' => 'Multi-company HRIS (:companies) dengan payroll, pajak, BPJS terpisah.',
            'hero.tag.mobile_desktop' => 'Mobile + Desktop',
            'hero.tag.stack' => 'Laravel + MariaDB',
            'filter.company' => 'Company',
            'filter.range' => 'Range',
            'filter.range.day' => 'Harian',
            'filter.range.week' => 'Mingguan',
            'filter.range.month' => 'Bulanan',
            'filter.period' => 'Periode',
            'filter.date_range' => 'Rentang Tanggal',
            'filter.apply' => 'Terapkan',
            'metric.total_employees' => 'TOTAL KARYAWAN',
            'metric.total_employees_foot' => 'Master Karyawan',
            'metric.attendance_today' => 'KEHADIRAN HARI INI',
            'metric.attendance_today_foot' => 'Attendance',
            'metric.late_range' => 'TERLAMBAT',
            'metric.late_range_foot' => 'Kedatangan > 09:00',
            'metric.overtime_range' => 'TOTAL LEMBUR',
            'metric.overtime_range_foot' => 'Jam',
            'panel.trend' => 'Trend Kehadiran',
            'panel.composition' => 'Komposisi Kehadiran',
            'panel.attendance_summary' => 'Ringkas Kehadiran',
            'panel.approval_queue' => 'Approval Queue',
            'panel.contract' => 'Kontrak & Masa Aktif',
            'panel.monitoring' => 'Monitoring HR',
            'panel.activity' => 'Aktivitas HR Terbaru',
            'panel.activity_subtitle' => 'Workflow / Payroll / Attendance',
            'panel.pending_payroll_report' => 'Approval Payroll Report (Pending)',
            'panel.pending_payroll_pph21' => 'Approval Payroll PPh21 (Pending)',
            'panel.top5_department_ot' => 'Top 5 Departemen Lembur',
            'panel.payroll_cost' => 'Payroll Cost per Company',
            'common.present' => 'Hadir',
            'common.late' => 'Terlambat',
            'common.absent' => 'Tidak Hadir',
            'common.overtime_today' => 'Lembur Hari Ini',
            'common.leave' => 'Cuti',
            'common.permission' => 'Izin',
            'common.overtime' => 'Lembur',
            'common.reimbursement' => 'Reimbursement',
            'common.payroll_report' => 'Payroll Report',
            'common.payroll_pph21' => 'Payroll PPh21',
            'common.open_approval_report' => 'Buka Approval Payroll Report',
            'common.open_approval_pph21' => 'Buka Approval Payroll PPh21',
            'common.pkwt_30' => 'PKWT Habis 30 Hari',
            'common.pkwt_7' => 'PKWT Habis 7 Hari',
            'common.new_employee_30' => 'Karyawan Baru (30 Hari)',
            'common.mutation_company' => 'Mutasi Company',
            'common.hr_notifications' => 'Notifikasi HR',
            'common.unread' => 'Belum Dibaca',
            'common.workflow_7days' => 'Workflow 7 Hari',
            'common.activity_hr' => 'Aktivitas HR',
            'common.activity_logged_suffix' => 'Absensi tercatat.',
            'common.empty_activity' => 'Belum ada aktivitas.',
            'common.top5' => 'Top 5',
            'common.no_pending_payroll_report' => 'Tidak ada pending approval payroll report.',
            'common.no_pending_payroll_pph21' => 'Tidak ada pending approval payroll PPh21.',
            'table.period' => 'Periode',
            'table.requester' => 'Requester',
            'table.step' => 'Step',
            'table.action' => 'Aksi',
            'table.department' => 'Departemen',
            'table.overtime_hours' => 'Overtime (jam)',
            'button.approve' => 'Approve',
            'button.reject' => 'Reject',
            'confirm.reject_payroll_report' => 'Tolak approval payroll report?',
            'confirm.reject_payroll_pph21' => 'Tolak approval payroll PPh21?',
            'common.no_overtime_data' => 'Belum ada data lembur.',
            'common.latest_finalized_period' => 'Latest finalized period',
            'common.no_finalized_period' => 'No finalized period',
            'common.no_payroll_data' => 'Belum ada data payroll.',
            'chart.present' => 'Hadir',
            'chart.absent' => 'Tidak Hadir',
            'common.step_prefix' => 'Step',
        ];

        foreach ($defaults as $key => $value) {
            $exists = DB::table('dashboard_labels')
                ->where('company_id', 0)
                ->where('label_key', $key)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('dashboard_labels')->insert([
                'company_id' => 0,
                'label_key' => $key,
                'label_value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_labels');
    }
};

