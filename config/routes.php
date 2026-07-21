<?php
declare(strict_types=1);

use App\Controllers\AdminAttendanceController;
use App\Controllers\AdminAttendanceGalleryController;
use App\Controllers\AdminEventsController;
use App\Controllers\AdminImportController;
use App\Controllers\AdminLogsController;
use App\Controllers\AdminRegistrantsController;
use App\Controllers\AdminSignatureController;
use App\Controllers\AdminSeoController;
use App\Controllers\AdminUsersController;
use App\Controllers\AdvancedExportController;
use App\Controllers\AttendanceController;
use App\Controllers\AuthController;
use App\Controllers\ParticipantController;
use App\Controllers\RegisterController;
use App\Controllers\ReportController;
use App\Controllers\SampleCsvController;
use App\Controllers\ScanController;
use App\Controllers\SettingsController;
use App\Controllers\AdminExportPageController;
use App\Controllers\AdminCoaController;
use App\Controllers\ExportController;

$rolesAdmin = 'role:admin';
$rolesOps = 'role:admin|checker';
$rolesSeoRead = 'role:admin|seo_viewer';

return [
    'register' => [
        'GET' => [RegisterController::class, 'show'],
    ],
    'register_submit' => [
        'POST' => [RegisterController::class, 'submit'],
        '_fallback' => static function (): void {
            header('Location: ?r=register');
            exit;
        },
    ],
    'register_success' => [
        'GET' => [RegisterController::class, 'success'],
    ],
    'scan' => [
        'GET' => [ScanController::class, 'show'],
    ],
    'api_participant' => [
        'GET' => [ParticipantController::class, 'getByUuidJson'],
    ],
    'attendance_submit' => [
        'POST' => [AttendanceController::class, 'submit'],
        '_guards' => ['staff'],
    ],
    'admin_login' => [
        'GET' => [AuthController::class, 'loginForm'],
    ],
    'admin_login_post' => [
        'POST' => [AuthController::class, 'login'],
    ],
    'admin_logout' => [
        'GET' => [AuthController::class, 'logout'],
    ],
    'admin_registrants' => [
        'GET' => [AdminRegistrantsController::class, 'list'],
        '_guards' => [$rolesOps],
    ],
    'admin_generate_qr' => [
        'POST' => [AdminRegistrantsController::class, 'generateQrBatch'],
        '_guards' => [$rolesOps],
    ],
    'admin_registrant_email' => [
        'POST' => [AdminRegistrantsController::class, 'sendQrEmail'],
        '_guards' => [$rolesOps],
    ],
    'admin_qr' => [
        'GET' => [AdminRegistrantsController::class, 'qrPreview'],
        '_guards' => [$rolesOps],
    ],
    'admin_registrant_vip' => [
        'POST' => [AdminRegistrantsController::class, 'toggleVip'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_attendance' => [
        'GET' => [AdminAttendanceController::class, 'list'],
        '_guards' => [$rolesOps],
    ],
    'admin_attendance_kpi' => [
        'GET' => [AdminAttendanceController::class, 'kpiJson'],
        '_guards' => ['role:admin|checker|seo_viewer'],
    ],
    'admin_attendance_kpi_stream' => [
        'GET' => [AdminAttendanceController::class, 'kpiStream'],
        '_guards' => ['role:admin|checker|seo_viewer'],
    ],
    'admin_attendance_search' => [
        'GET' => [AdminAttendanceController::class, 'searchParticipants'],
        '_guards' => [$rolesOps],
    ],
    'admin_attendance_manual' => [
        'POST' => [AdminAttendanceController::class, 'manualAttendance'],
        '_guards' => [$rolesOps],
    ],
    'admin_attendance_mark_absent' => [
        'POST' => [AdminAttendanceController::class, 'markAbsent'],
        '_guards' => [$rolesOps],
    ],
    'admin_attendance_clear_absent' => [
        'POST' => [AdminAttendanceController::class, 'clearAbsent'],
        '_guards' => [$rolesOps],
    ],
    'admin_attendance_gallery' => [
        'GET' => [AdminAttendanceGalleryController::class, 'list'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_seo_dashboard' => [
        'GET' => [AdminSeoController::class, 'dashboard'],
        '_guards' => [$rolesSeoRead],
    ],
    'admin_seo_summary' => [
        'GET' => [AdminSeoController::class, 'summaryJson'],
        '_guards' => [$rolesSeoRead],
    ],
    'admin_seo_search' => [
        'GET' => [AdminSeoController::class, 'searchJson'],
        '_guards' => [$rolesSeoRead],
    ],
    'admin_users' => [
        'GET' => [AdminUsersController::class, 'list'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_users_create' => [
        'POST' => [AdminUsersController::class, 'create'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_users_update' => [
        'POST' => [AdminUsersController::class, 'update'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_settings' => [
        'GET' => [SettingsController::class, 'form'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_settings_save' => [
        'POST' => [SettingsController::class, 'save'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_import' => [
        'GET' => [AdminImportController::class, 'form'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_import_template' => [
        'GET' => [AdminImportController::class, 'downloadTemplate'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_import_preview' => [
        'POST' => [AdminImportController::class, 'preview'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_import_execute' => [
        'POST' => [AdminImportController::class, 'execute'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_import_history' => [
        'GET' => [AdminImportController::class, 'history'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_export' => [
        'GET' => [AdminExportPageController::class, 'index'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_coa' => [
        'GET' => [AdminCoaController::class, 'compose'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_coa_preview' => [
        'POST' => [AdminCoaController::class, 'preview'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_coa_send' => [
        'POST' => [AdminCoaController::class, 'send'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_coa_signatories' => [
        'GET' => [AdminCoaController::class, 'signatories'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_coa_signatory_save' => [
        'POST' => [AdminCoaController::class, 'saveSignatory'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_coa_signatory_image' => [
        'GET' => [AdminCoaController::class, 'signatoryImage'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_coa_monitor' => [
        'GET' => [AdminCoaController::class, 'monitor'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_coa_queue_failed' => [
        'POST' => [AdminCoaController::class, 'queueFailed'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_coa_resend_queued' => [
        'POST' => [AdminCoaController::class, 'resendQueued'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_report' => [
        'GET' => [ReportController::class, 'form'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_report_generate' => [
        'POST' => [ReportController::class, 'generate'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_report_save' => [
        'POST' => [ReportController::class, 'saveTemplate'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_report_load' => [
        'GET' => [ReportController::class, 'loadTemplate'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_signature_replace' => [
        'POST' => [AdminSignatureController::class, 'replace'],
        '_guards' => [$rolesOps],
    ],
    'admin_signature_new' => [
        'POST' => [AdminSignatureController::class, 'addNew'],
        '_guards' => [$rolesOps],
    ],
    'admin_logs' => [
        'GET' => [AdminLogsController::class, 'list'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_events' => [
        'GET' => [AdminEventsController::class, 'list'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_events_create' => [
        'POST' => [AdminEventsController::class, 'create'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_events_set_active' => [
        'POST' => [AdminEventsController::class, 'setActive'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_events_deactivate' => [
        'POST' => [AdminEventsController::class, 'deactivate'],
        '_guards' => [$rolesAdmin],
    ],
    'admin_events_delete' => [
        'POST' => [AdminEventsController::class, 'delete'],
        '_guards' => [$rolesAdmin],
    ],
    'export_registrants_csv' => [
        'GET' => [ExportController::class, 'registrantsCsv'],
        '_guards' => [$rolesAdmin],
    ],
    'export_attendance_csv' => [
        'GET' => [ExportController::class, 'attendanceCsv'],
        '_guards' => [$rolesAdmin],
    ],
    'export_registrants_xlsx' => [
        'GET' => [AdvancedExportController::class, 'registrantsXlsx'],
        '_guards' => [$rolesAdmin],
    ],
    'export_attendance_xlsx' => [
        'GET' => [AdvancedExportController::class, 'attendanceXlsx'],
        '_guards' => [$rolesAdmin],
    ],
    'export_attendance_pdf' => [
        'GET' => [AdvancedExportController::class, 'attendancePdf'],
        '_guards' => [$rolesAdmin],
    ],
    'sample_csv' => [
        'GET' => [SampleCsvController::class, 'download'],
        '_guards' => [$rolesAdmin],
    ],
];
