<?php

use Illuminate\Support\Facades\Route;
use Modules\Example\App\Http\Controllers\ExampleController;

/*
|--------------------------------------------------------------------------
| Example Module API Routes
|--------------------------------------------------------------------------
| Được nạp tự động bởi nwidart/laravel-modules dưới prefix "api".
*/

Route::prefix('example')->name('example.')->group(function () {
    Route::get('/', [ExampleController::class, 'index'])->name('index');
});
