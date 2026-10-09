<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\App\Http\Controllers\EmployeeLeaveCatalogController;
use Modules\Attendance\App\Http\Controllers\EmployeeLeaveRequestController;
use Modules\Attendance\App\Http\Controllers\EmployeeLeaveWorkflowController;

Route::middleware('auth')->prefix('attendance')->name('attendance.')->group(function () {
    Route::get('/leave-types', [EmployeeLeaveCatalogController::class, 'leaveTypes'])
        ->name('leave-types.index');
    Route::get('/leave-workflow', [EmployeeLeaveWorkflowController::class, 'show'])
        ->name('leave-workflow.show');
    Route::get('/leave-requests', [EmployeeLeaveRequestController::class, 'index'])
        ->name('leave-requests.index');
    Route::post('/leave-requests', [EmployeeLeaveRequestController::class, 'store'])
        ->name('leave-requests.store');
});
