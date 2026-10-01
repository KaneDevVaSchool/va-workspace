<?php

use Illuminate\Support\Facades\Route;
use Modules\Contract\App\Http\Controllers\SupplierController;
use Modules\Contract\App\Http\Controllers\SupplierDocumentController;

Route::middleware(['auth', 'permission:contract.view|contract.manage_department|contract.*'])
    ->prefix('contract')
    ->name('contract.')
    ->group(function () {
        Route::get('/suppliers/options', [SupplierController::class, 'options'])->name('suppliers.options');
        Route::get('/suppliers/next-code', [SupplierController::class, 'nextCode'])->name('suppliers.next-code');
        Route::get('/suppliers/check-tax-code', [SupplierController::class, 'checkTaxCode'])->name('suppliers.check-tax-code');
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->whereNumber('supplier')->name('suppliers.show');
    });

Route::middleware(['auth', 'permission:contract.manage_department|contract.*'])
    ->prefix('contract')
    ->name('contract.')
    ->group(function () {
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->whereNumber('supplier')->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->whereNumber('supplier')->name('suppliers.destroy');
        Route::post('/suppliers/{supplier}/status', [SupplierController::class, 'changeStatus'])->whereNumber('supplier')->name('suppliers.status');
        Route::post('/suppliers/{supplier}/documents', [SupplierDocumentController::class, 'store'])->whereNumber('supplier')->name('suppliers.documents.store');
        Route::put('/suppliers/{supplier}/documents/{document}/review', [SupplierDocumentController::class, 'review'])
            ->whereNumber(['supplier', 'document'])
            ->name('suppliers.documents.review');
    });
