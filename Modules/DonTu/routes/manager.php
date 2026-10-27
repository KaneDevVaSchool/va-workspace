<?php

use Illuminate\Support\Facades\Route;
use Modules\DonTu\App\Http\Controllers\DonTuFormTemplateController;
use Modules\DonTu\App\Http\Controllers\DonTuStatusSetController;
use Modules\DonTu\App\Http\Controllers\DonTuWorkflowController;

Route::prefix('don-tu')->name('don_tu.')->group(function () {
    Route::get('status-sets', [DonTuStatusSetController::class, 'index'])->name('status_sets.index');
    Route::post('status-sets', [DonTuStatusSetController::class, 'store'])->name('status_sets.store');
    Route::get('status-sets/{id}', [DonTuStatusSetController::class, 'show'])
        ->whereNumber('id')
        ->name('status_sets.show');
    Route::post('status-sets/{id}/statuses', [DonTuStatusSetController::class, 'storeStatus'])
        ->whereNumber('id')
        ->name('status_sets.statuses.store');

    Route::get('form-templates', [DonTuFormTemplateController::class, 'index'])->name('form_templates.index');
    Route::post('form-templates', [DonTuFormTemplateController::class, 'store'])->name('form_templates.store');
    Route::get('form-templates/{id}', [DonTuFormTemplateController::class, 'show'])
        ->whereNumber('id')
        ->name('form_templates.show');
    Route::patch('form-templates/{id}', [DonTuFormTemplateController::class, 'update'])
        ->whereNumber('id')
        ->name('form_templates.update');
    Route::post('form-templates/{id}/duplicate', [DonTuFormTemplateController::class, 'duplicate'])
        ->whereNumber('id')
        ->name('form_templates.duplicate');
    Route::post('form-templates/{id}/archive', [DonTuFormTemplateController::class, 'archive'])
        ->whereNumber('id')
        ->name('form_templates.archive');
    Route::delete('form-templates/{id}', [DonTuFormTemplateController::class, 'destroy'])
        ->whereNumber('id')
        ->name('form_templates.destroy');

    Route::get('workflows', [DonTuWorkflowController::class, 'index'])->name('workflows.index');
    Route::post('workflows', [DonTuWorkflowController::class, 'store'])->name('workflows.store');
    Route::get('workflows/{id}', [DonTuWorkflowController::class, 'show'])
        ->whereNumber('id')
        ->name('workflows.show');
    Route::patch('workflows/{id}', [DonTuWorkflowController::class, 'update'])
        ->whereNumber('id')
        ->name('workflows.update');
    Route::delete('workflows/{id}', [DonTuWorkflowController::class, 'destroy'])
        ->whereNumber('id')
        ->name('workflows.destroy');
});
