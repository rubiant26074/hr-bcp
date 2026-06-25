<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EmployeesController;
use App\Http\Controllers\ContractsController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\RbacController;
use App\Http\Controllers\TvDashboardController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\ApprovalSettingsController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\DinasLuarController;
use App\Http\Controllers\PensionController;
use App\Http\Controllers\PhkController;
use App\Http\Controllers\MobileAppController;
use App\Http\Controllers\SecurityRosterController;

Route::get('/', function () {
    return is_logged_in()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'registerForm'])->name('register.form');
Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
Route::get('/register/pending', [AuthController::class, 'registerPending'])->name('register.pending');
Route::post('/register/resend-verification', [AuthController::class, 'resendVerification'])->name('register.resend_verification');
Route::get('/register/verify/{token}', [AuthController::class, 'verify'])->name('register.verify');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
$serveAndroidApk = function () {
    $candidatePaths = [
        // Preferred filename for mobile dedicated app.
        public_path('apk/mobile_hr-bcp.apk'),
        // Preferred path for hosting deployment (publicly uploaded file).
        public_path('apk/HR-BCP.apk'),
        public_path('android/hr-bcp.apk'),
        public_path('apk/app-debug.apk'),
        // Optional storage path if symlink is used.
        storage_path('app/public/apk/mobile_hr-bcp.apk'),
        storage_path('app/public/apk/HR-BCP.apk'),
        storage_path('app/public/apk/app-debug.apk'),
        // Local dev/build fallback.
        base_path('android/HRBCPAndroid/app/build/outputs/apk/debug/app-debug.apk'),
    ];

    $apkPath = null;
    foreach ($candidatePaths as $path) {
        if (is_file($path)) {
            $apkPath = $path;
            break;
        }
    }

    if ($apkPath === null) {
        abort(404, 'APK belum tersedia di server. Upload ke public/apk/mobile_hr-bcp.apk.');
    }

    return Response::download($apkPath, 'mobile_hr-bcp.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
    ]);
};

Route::get('/android/hr-bcp', $serveAndroidApk)->name('android.apk');
Route::get('/android/hr-bcp.apk', $serveAndroidApk);

Route::prefix('m')->group(function () {
    Route::get('/login', [MobileAppController::class, 'loginForm'])->name('mobile.login');
    Route::post('/login', [MobileAppController::class, 'login'])->name('mobile.login.submit');
    Route::get('/register', [MobileAppController::class, 'registerForm'])->name('mobile.register');
    Route::post('/register', [MobileAppController::class, 'register'])->name('mobile.register.submit');
    Route::get('/logout', [MobileAppController::class, 'logout'])->name('mobile.logout');
    Route::get('/', [MobileAppController::class, 'home'])->name('mobile.home');
    Route::get('/attendance', [MobileAppController::class, 'attendance'])->name('mobile.attendance');
    Route::get('/recap', [MobileAppController::class, 'recap'])->name('mobile.recap');
    Route::get('/payslip', [MobileAppController::class, 'payslip'])->name('mobile.payslip');
});

Route::middleware(['legacy.auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::match(['get','post'], '/account', [AccountController::class, 'index'])->name('account');
    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::match(['get','post'], '/notifications', [\App\Http\Controllers\NotificationsController::class, 'index'])->name('notifications.index');
});

Route::middleware(['legacy.auth'])->group(function () {
    Route::match(['get','post'], '/company', [CompanyController::class, 'index'])->name('company.index');
    Route::match(['get','post'], '/company/form', [CompanyController::class, 'form'])->name('company.form');
});
Route::middleware(['legacy.auth'])->group(function () {
    Route::get('/company/detail/{id}', [CompanyController::class, 'detail'])->name('company.detail');
    Route::match(['get','post'], '/org-structure', [\App\Http\Controllers\OrgStructureController::class, 'index'])->name('org_structure.index');
});

Route::middleware(['legacy.auth'])->group(function () {
    Route::match(['get','post'], '/employees', [EmployeesController::class, 'index'])->name('employees.index');
    Route::match(['get','post'], '/employees/form', [EmployeesController::class, 'form'])->name('employees.form');
    Route::get('/employees/detail/{id}', [EmployeesController::class, 'detail'])->name('employees.detail');
    Route::post('/employees/draft-upload', [EmployeesController::class, 'draftUpload'])->name('employees.draft_upload');
    Route::match(['get','post'], '/employees/active-status', [EmployeesController::class, 'activeStatus'])->name('employees.active_status');
    Route::match(['get','post'], '/employees/status', [EmployeesController::class, 'status'])->name('employees.status');
    Route::get('/employees/type', [EmployeesController::class, 'type'])->name('employees.type');
    Route::match(['get','post'], '/employees/department', [EmployeesController::class, 'department'])->name('employees.department');
    Route::match(['get','post'], '/employees/position', [EmployeesController::class, 'position'])->name('employees.position');
    Route::match(['get','post'], '/employees/grade', [EmployeesController::class, 'grade'])->name('employees.grade');
    Route::match(['get','post'], '/employees/allin-overtime', [EmployeesController::class, 'allInOvertime'])->name('employees.allin_overtime');
    Route::match(['get','post'], '/employees/import', [EmployeesController::class, 'import'])->name('employees.import');
    Route::get('/employees/export', [EmployeesController::class, 'export'])->name('employees.export');
    Route::get('/employees/export-excel', [EmployeesController::class, 'exportExcel'])->name('employees.export_excel');
    Route::get('/employees/export-pdf', [EmployeesController::class, 'exportPdf'])->name('employees.export_pdf');
    Route::get('/employees/template', [EmployeesController::class, 'template'])->name('employees.template');
    Route::match(['get','post'], '/pension', [PensionController::class, 'index'])->name('pension.index');
    Route::get('/pension/pdf', [PensionController::class, 'pdf'])->name('pension.pdf');
    Route::match(['get','post'], '/phk', [PhkController::class, 'index'])->name('phk.index');
    Route::match(['get','post'], '/leave', [\App\Http\Controllers\LeaveController::class, 'index'])->name('leave.index');
    Route::match(['get','post'], '/holidays', [\App\Http\Controllers\HolidayController::class, 'index'])->name('holidays.index');
    Route::get('/holidays/template', [\App\Http\Controllers\HolidayController::class, 'template'])->name('holidays.template');
});

Route::middleware(['legacy.auth'])->group(function () {
    Route::match(['get','post'], '/contracts', [ContractsController::class, 'index'])->name('contracts.index');
    Route::match(['get','post'], '/contracts/form', [ContractsController::class, 'form'])->name('contracts.form');
    Route::get('/contracts/template', [ContractsController::class, 'template'])->name('contracts.template');
});

Route::middleware(['legacy.auth'])->group(function () {
    Route::match(['get','post'], '/attendance/import', [AttendanceController::class, 'import'])->name('attendance.import');
    Route::get('/attendance/template', [AttendanceController::class, 'template'])->name('attendance.template');
    Route::match(['get','post'], '/attendance/logs', [AttendanceController::class, 'logs'])->name('attendance.logs');
    Route::match(['get','post'], '/attendance/daily', [AttendanceController::class, 'daily'])->name('attendance.daily');
    Route::match(['get','post'], '/attendance/monthly', [AttendanceController::class, 'monthly'])->name('attendance.monthly');
    Route::match(['get','post'], '/attendance/monthly-employee', [AttendanceController::class, 'monthlyEmployee'])->name('attendance.monthly_employee');
    Route::match(['get','post'], '/attendance/security-roster', [SecurityRosterController::class, 'index'])->name('attendance.security_roster');
    Route::match(['get','post'], '/settings/attendance-location', [AttendanceController::class, 'locationSettings'])->name('attendance.location');
});

Route::middleware(['legacy.auth'])->group(function () {
    Route::match(['get','post'], '/permissions/absence', [PermissionsController::class, 'absence'])->name('permissions.absence');
    Route::get('/permissions/absence/{id}/pdf', [PermissionsController::class, 'absencePdf'])->name('permissions.absence_pdf');
    Route::get('/permissions/absence/{id}/preview', [PermissionsController::class, 'absencePreview'])->name('permissions.absence_preview');
    Route::match(['get','post'], '/permissions/out-office', [PermissionsController::class, 'outOffice'])->name('permissions.out_office');
    Route::get('/permissions/out-office/{id}/pdf', [PermissionsController::class, 'outOfficePdf'])->name('permissions.out_office_pdf');
    Route::get('/permissions/out-office/{id}/preview', [PermissionsController::class, 'outOfficePreview'])->name('permissions.out_office_preview');
    Route::match(['get','post'], '/permissions/overtime', [PermissionsController::class, 'overtime'])->name('permissions.overtime');
    Route::get('/permissions/overtime/{id}/pdf', [PermissionsController::class, 'overtimePdf'])->name('permissions.overtime_pdf');
    Route::get('/permissions/overtime/{id}/preview', [PermissionsController::class, 'overtimePreview'])->name('permissions.overtime_preview');
    Route::get('/attendance/mobile', [AttendanceController::class, 'mobile'])->name('attendance.mobile');
    Route::get('/attendance/face/profile', [AttendanceController::class, 'faceProfile'])->name('attendance.face_profile');
    Route::post('/attendance/face/enroll', [AttendanceController::class, 'faceEnroll'])->name('attendance.face_enroll');
    Route::get('/attendance/face/enroll-native', [AttendanceController::class, 'faceEnrollNative'])->name('attendance.face_enroll_native');
    Route::post('/attendance/face/checkin', [AttendanceController::class, 'faceCheckin'])->name('attendance.face_checkin');
    Route::get('/attendance/face/checkin-native', [AttendanceController::class, 'faceCheckinNative'])->name('attendance.face_checkin_native');
    Route::match(['get','post'], '/dinas-luar', [DinasLuarController::class, 'index'])->name('dinas_luar.index');
    Route::match(['get','post'], '/dinas-luar/form', [DinasLuarController::class, 'form'])->name('dinas_luar.form');
    Route::get('/dinas-luar/detail/{id}', [DinasLuarController::class, 'detail'])->name('dinas_luar.detail');
    Route::get('/dinas-luar/{id}/pdf', [DinasLuarController::class, 'pdf'])->name('dinas_luar.pdf');
});
Route::middleware(['legacy.auth'])->group(function () {
    Route::get('/attendance/report', [AttendanceController::class, 'report'])->name('attendance.report');
});

Route::middleware(['legacy.auth'])->group(function () {
    Route::match(['get','post'], '/payroll/period', [PayrollController::class, 'period'])->name('payroll.period');
    Route::match(['get','post'], '/payroll/run', [PayrollController::class, 'run'])->name('payroll.run');
});
Route::middleware(['legacy.auth'])->group(function () {
    Route::get('/payroll/review', [PayrollController::class, 'review'])->name('payroll.review');
    Route::match(['get','post'], '/payroll/report', [PayrollController::class, 'report'])->name('payroll.report');
    Route::match(['get','post'], '/payroll/report-approval', [PayrollController::class, 'reportApproval'])->name('payroll.report_approval');
    Route::match(['get','post'], '/payroll/pph21', [PayrollController::class, 'pph21'])->name('payroll.pph21');
    Route::match(['get','post'], '/payroll/pph21-approval', [PayrollController::class, 'pph21Approval'])->name('payroll.pph21_approval');
    Route::match(['get','post'], '/payroll/bank-transfer', [PayrollController::class, 'bankTransfer'])->name('payroll.bank_transfer');
});
Route::middleware(['legacy.auth'])->group(function () {
    Route::get('/payroll/slip', [PayrollController::class, 'slip'])->name('payroll.slip');
    Route::get('/payroll/qr', [PayrollController::class, 'qr'])->name('payroll.qr');
});

Route::middleware(['legacy.auth'])->group(function () {
    Route::get('/tv', [TvDashboardController::class, 'index'])->name('tv.index');
    Route::match(['get','post'], '/users', [UsersController::class, 'index'])->name('users.index');
    Route::match(['get','post'], '/users/form', [UsersController::class, 'form'])->name('users.form');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::match(['get','post'], '/settings/theme', [SettingsController::class, 'theme'])->name('settings.theme');
    Route::match(['get','post'], '/settings/backup', [SettingsController::class, 'backup'])->name('settings.backup');
    Route::match(['get','post'], '/settings/migrate', [SettingsController::class, 'migrate'])->name('settings.migrate');
    Route::match(['get','post'], '/settings/approval', [ApprovalSettingsController::class, 'index'])->name('settings.approval');
    Route::match(['get','post'], '/settings/roles', [RoleManagementController::class, 'index'])->name('settings.roles');
    Route::match(['get','post'], '/settings/reset', [SettingsController::class, 'reset'])->name('settings.reset');
    Route::match(['get','post'], '/rbac', [RbacController::class, 'index'])->name('rbac.index');
});

