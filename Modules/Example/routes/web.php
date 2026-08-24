<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Example Module Web Routes (public)
|--------------------------------------------------------------------------
*/

Route::prefix('example')->name('example.')->group(function () {
    // Route::get('/', [ExampleController::class, 'index'])->name('index');
});
