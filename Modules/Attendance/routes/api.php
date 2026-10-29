<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\App\Http\Controllers\EmployeeAttendanceCheckInContextController;
use Modules\Attendance\App\Http\Controllers\EmployeeAttendancePunchController;
use Modules\Attendance\App\Http\Controllers\EmployeeLeaveBalanceController;
use Modules\Attendance\App\Http\Controllers\EmployeeLeaveCatalogController;
use Modules\Attendance\App\Http\Controllers\EmployeeLeaveRequestController;
use Modules\Attendance\App\Http\Controllers\EmployeeLeaveWorkflowController;
use Modules\Attendance\App\Http\Controllers\EmployeeTimesheetApprovalController;

Route::middleware('auth')->prefix('attendance')->name('attendance.')->group(function () {
    Route::get('/leave-types', [EmployeeLeaveCatalogController::class, 'leaveTypes'])
        ->name('leave-types.index');
    Route::get('/leave-balance', [EmployeeLeaveBalanceController::class, 'show'])
        ->name('leave-balance.show');
    Route::get('/leave-workflow', [EmployeeLeaveWorkflowController::class, 'show'])
        ->name('leave-workflow.show');
    Route::get('/leave-requests', [EmployeeLeaveRequestController::class, 'index'])
        ->name('leave-requests.index');
    Route::post('/leave-requests', [EmployeeLeaveRequestController::class, 'store'])
        ->name('leave-requests.store');

    Route::get('/check-in-context', [EmployeeAttendanceCheckInContextController::class, 'show'])
        ->name('check-in-context.show');
    Route::get('/punches/today', [EmployeeAttendancePunchController::class, 'today'])
        ->name('punches.today');
    Route::post('/punches', [EmployeeAttendancePunchController::class, 'store'])
        ->name('punches.store');
    Route::post('/punches/sync', [EmployeeAttendancePunchController::class, 'sync'])
        ->name('punches.sync');

    // Trưởng đơn vị duyệt bảng chấm công HRM (API v1 attendance/timesheet-approvals).
    Route::get('/timesheet-approvals', [EmployeeTimesheetApprovalController::class, 'index'])
        ->name('timesheet-approvals.index');
    Route::get('/timesheet-approvals/count', [EmployeeTimesheetApprovalController::class, 'count'])
        ->name('timesheet-approvals.count');
    Route::get('/timesheet-approvals/{uuid}', [EmployeeTimesheetApprovalController::class, 'show'])
        ->whereUuid('uuid')
        ->name('timesheet-approvals.show');
    Route::post('/timesheet-approvals/{uuid}/approve', [EmployeeTimesheetApprovalController::class, 'approve'])
        ->whereUuid('uuid')
        ->name('timesheet-approvals.approve');
    Route::post('/timesheet-approvals/{uuid}/reject', [EmployeeTimesheetApprovalController::class, 'reject'])
        ->whereUuid('uuid')
        ->name('timesheet-approvals.reject');
});
