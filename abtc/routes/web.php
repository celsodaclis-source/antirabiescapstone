<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\NurseStationController;
use App\Http\Controllers\DoctorStationController;
use App\Http\Controllers\QueueMonitoringController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->user()?->role === 'Staff'
        ? redirect()->route('staff.dashboard')
        : redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('role:Admin,Doctor,Nurse')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::middleware('role:Admin')->prefix('queue-monitoring')->name('queue-monitoring.')->group(function () {
        Route::get('/', [QueueMonitoringController::class, 'index'])->name('index');
        Route::get('/data', [QueueMonitoringController::class, 'data'])->name('data');
    });
    Route::middleware('role:Nurse')->prefix('nurse/stations')->name('nurse.stations.')->group(function () {
        Route::post('/{station}/assign', [NurseStationController::class, 'assign'])->name('assign');
        Route::delete('/release', [NurseStationController::class, 'release'])->name('release');
    });
    Route::middleware('role:Nurse')->prefix('nurse/station')->name('nurse.station.')->group(function () {
        Route::get('/', [NurseStationController::class, 'show'])->name('show');
        Route::post('/call-next', [NurseStationController::class, 'callNext'])->name('call-next');
        Route::post('/tickets/{ticket}/assessment', [NurseStationController::class, 'completeAssessment'])->name('assessment.complete');
    });
    Route::middleware('role:Doctor')->prefix('doctor/stations')->name('doctor.stations.')->group(function () {
        Route::post('/{station}/assign', [DoctorStationController::class, 'assign'])->name('assign');
        Route::delete('/release', [DoctorStationController::class, 'release'])->name('release');
    });
    Route::middleware('role:Doctor')->prefix('doctor/station')->name('doctor.station.')->group(function () {
        Route::get('/', [DoctorStationController::class, 'show'])->name('show');
        Route::post('/call-next', [DoctorStationController::class, 'callNext'])->name('call-next');
        Route::post('/tickets/{ticket}/complete', [DoctorStationController::class, 'complete'])->name('complete');
    });

    // Patient Registration & Records
    Route::resource('patients', PatientController::class)->except(['create', 'store']);
    Route::middleware('role:Admin')->group(function () {
        Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
        Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
        Route::get('/patients/export/csv', [PatientController::class, 'exportCsv'])->name('patients.export.csv');
    });

    // Ticket Queue Management
    Route::middleware('role:Admin,Doctor')->group(function () {
    Route::prefix('queue')->name('queue.')->group(function () {
        Route::get('/', [QueueController::class, 'index'])->name('index');
        Route::get('/data', [QueueController::class, 'data'])->name('data'); // polling endpoint
        Route::post('/call-next', [QueueController::class, 'callNext'])->name('call-next');
        Route::post('/call/{ticket}', [QueueController::class, 'callSpecific'])->name('call');
        Route::post('/hold/{ticket}', [QueueController::class, 'hold'])->name('hold');
        Route::post('/complete/{ticket}', [QueueController::class, 'complete'])->name('complete');
        Route::post('/serve/{ticket}', [QueueController::class, 'serve'])->name('serve');
    });
    });

    // Vaccination Scheduling & Dose Monitoring
    Route::prefix('schedules')->name('schedules.')->group(function () {
        Route::get('/', [ScheduleController::class, 'index'])->name('index');
        Route::get('/patient/{patient}', [ScheduleController::class, 'show'])->name('show');
        Route::middleware('role:Admin,Nurse')->group(function () {
            Route::post('/{schedule}/queue', [QueueController::class, 'queueSchedule'])->name('queue');
            Route::get('/{schedule}/edit', [ScheduleController::class, 'edit'])->name('edit');
            Route::put('/{schedule}', [ScheduleController::class, 'update'])->name('update');
            Route::post('/{schedule}/administer', [ScheduleController::class, 'administer'])->name('administer');
        });
        Route::get('/overdue/list', [ScheduleController::class, 'overdue'])->name('overdue');
    });

    // Vaccine Inventory Monitoring
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::get('/create', [InventoryController::class, 'create'])->name('create');
        Route::post('/', [InventoryController::class, 'store'])->name('store');
        Route::get('/{inventory}/edit', [InventoryController::class, 'edit'])->name('edit');
        Route::put('/{inventory}', [InventoryController::class, 'update'])->name('update');
        Route::delete('/{inventory}', [InventoryController::class, 'destroy'])->name('destroy');
    });

    // SMS Reminders
    Route::middleware('role:Admin,Doctor')->group(function () {
    Route::prefix('sms')->name('sms.')->group(function () {
        Route::get('/', [SmsController::class, 'index'])->name('index');
        Route::get('/template', [SmsController::class, 'template'])->name('template');
        Route::post('/template', [SmsController::class, 'updateTemplate'])->name('template.update');
        Route::post('/send/{schedule}', [SmsController::class, 'send'])->name('send');
        Route::post('/send-patient/{patient}', [SmsController::class, 'sendToPatient'])->name('send-patient');
        Route::post('/send-overdue', [SmsController::class, 'sendOverdue'])->name('send-overdue');
        Route::post('/test-connection', [SmsController::class, 'testConnection'])->name('test-connection');
        Route::get('/settings', [SmsController::class, 'settings'])->name('settings');
        Route::post('/settings', [SmsController::class, 'updateSettings'])->name('settings.update');
    });
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/export/csv', [ReportController::class, 'exportCsv'])->name('export.csv');
        Route::get('/export/pdf', [ReportController::class, 'exportPdf'])->name('export.pdf');
    });

    // Users / Settings (Admin only)
    Route::middleware('role:Admin')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('index');
        Route::get('/users', [SettingController::class, 'users'])->name('users');
        Route::get('/users/create', [SettingController::class, 'createUser'])->name('users.create');
        Route::post('/users', [SettingController::class, 'storeUser'])->name('users.store');
        Route::get('/users/{user}/edit', [SettingController::class, 'editUser'])->name('users.edit');
        Route::put('/users/{user}', [SettingController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [SettingController::class, 'destroyUser'])->name('users.destroy');
        Route::put('/users/{user}/password', [SettingController::class, 'resetUserPassword'])->name('users.password.reset');
        Route::get('/audit', [SettingController::class, 'audit'])->name('audit');
        Route::post('/config', [SettingController::class, 'config'])->name('config');

        // Backup & Recovery
        Route::get('/backup', [BackupController::class, 'index'])->name('backup');
        Route::post('/backup', [BackupController::class, 'store'])->name('backup.store');
        Route::get('/backup/{backup}/download', [BackupController::class, 'download'])->name('backup.download');
        Route::delete('/backup/{backup}', [BackupController::class, 'destroy'])->name('backup.destroy');
    });

    });

    // Clinical assessment: vaccination plan (Doctors only)
    Route::middleware('role:Doctor')->group(function () {
        Route::get('/patients/{patient}/plan', [PatientController::class, 'plan'])->name('patients.plan');
        Route::put('/patients/{patient}/plan', [PatientController::class, 'storePlan'])->name('patients.plan.store');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('role:Staff')->prefix('staff')->name('staff.')->group(function () {
        Route::get('/dashboard', [StaffController::class, 'dashboard'])->name('dashboard');
        Route::get('/patients/register', [PatientController::class, 'create'])->name('patients.create');
        Route::post('/patients/register', [PatientController::class, 'store'])->name('patients.store');
        Route::get('/patients/search', [StaffController::class, 'searchPatients'])->name('patients.search');
        Route::get('/tickets/create', [StaffController::class, 'createTicket'])->name('tickets.create');
        Route::post('/tickets', [StaffController::class, 'storeTicket'])->name('tickets.store');
        Route::get('/tickets/{ticket}/print', [StaffController::class, 'printTicket'])->name('tickets.print');
        Route::get('/queue', [StaffController::class, 'queue'])->name('queue.index');
    });
});

require __DIR__.'/auth.php';
