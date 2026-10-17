<?php

use App\Http\Controllers\Api\FileMaintenanceApiController;
use App\Http\Controllers\Api\PatientApiController;
use App\Http\Controllers\Api\QueueApiController;
use App\Http\Controllers\Api\ScheduleApiController;
use App\Http\Controllers\Api\InventoryApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\ReportApiController;
use App\Http\Controllers\Api\AuthApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| N-VISION ABTC REST API
|--------------------------------------------------------------------------
|
| Provides JSON endpoints for the queue TV display, mobile app (staff),
| and external integrations. All authenticated routes use Sanctum tokens.
|
*/

// Public status endpoint for health checks
Route::get('/ping', fn () => response()->json([
    'status' => 'ok',
    'app' => config('app.name'),
    'version' => '1.0.0',
    'timestamp' => now()->toIso8601String(),
]));

// Authentication
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthApiController::class, 'login']);
    Route::post('register', [AuthApiController::class, 'register']);
});

// Public queue display (TV / waiting area screen — no auth)
Route::prefix('queue')->name('api.queue.')->group(function () {
    Route::get('display', [QueueApiController::class, 'display'])->name('display');
    Route::get('counters', [QueueApiController::class, 'counters'])->name('counters');
});

// Protected API routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [AuthApiController::class, 'user']);
    Route::post('logout', [AuthApiController::class, 'logout']);

    // Dashboard stats
    Route::get('dashboard', [DashboardApiController::class, 'index']);

// Patient management
    Route::apiResource('patients', PatientApiController::class)->names('api.patients');

    // Queue management
    Route::prefix('queue')->group(function () {
        Route::get('waiting', [QueueApiController::class, 'waiting']);
        Route::get('serving', [QueueApiController::class, 'serving']);
        Route::post('call-next', [QueueApiController::class, 'callNext']);
        Route::post('schedules/{schedule}/queue', [QueueApiController::class, 'queueSchedule']);
        Route::post('call/{ticket}', [QueueApiController::class, 'callSpecific']);
        Route::post('serve/{ticket}', [QueueApiController::class, 'serve']);
        Route::post('hold/{ticket}', [QueueApiController::class, 'hold']);
        Route::post('complete/{ticket}', [QueueApiController::class, 'complete']);
    });

    // Schedules
    Route::get('schedules', [ScheduleApiController::class, 'index']);
    Route::get('schedules/overdue', [ScheduleApiController::class, 'overdue']);
    Route::get('schedules/patient/{patient}', [ScheduleApiController::class, 'byPatient']);
    Route::post('schedules/{schedule}/administer', [ScheduleApiController::class, 'administer']);

    // Inventory
    Route::get('inventory', [InventoryApiController::class, 'index']);
    Route::get('inventory/low-stock', [InventoryApiController::class, 'lowStock']);
    Route::get('inventory/expiring', [InventoryApiController::class, 'expiring']);
    Route::post('inventory', [InventoryApiController::class, 'store']);
    Route::put('inventory/{inventory}', [InventoryApiController::class, 'update']);
    Route::delete('inventory/{inventory}', [InventoryApiController::class, 'destroy']);

    // File Maintenance
    Route::apiResource('files', FileMaintenanceApiController::class)->names('api.files');
    Route::get('files/categories', [FileMaintenanceApiController::class, 'categories']);

    // Reports
    Route::get('reports/summary', [ReportApiController::class, 'summary']);
    Route::get('reports/barangays', [ReportApiController::class, 'barangayStats']);
    Route::get('reports/exposure', [ReportApiController::class, 'exposureBreakdown']);
});

