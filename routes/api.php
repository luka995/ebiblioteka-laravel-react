<?php

use App\Http\Controllers\LibrariesController;
use App\Http\Controllers\PlacesController;
use App\Http\Controllers\RegionsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::prefix('v1')->middleware('auth')->group(function (): void {
    Route::get('/roles', [RolesController::class, 'index']);
    Route::get('/roles/assignable', [RolesController::class, 'assignable']);

    Route::get('/users/barcode/next', [UsersController::class, 'nextBarcode']);
    Route::get('/users', [UsersController::class, 'index'])->middleware('can:manage-users');
    Route::post('/users', [UsersController::class, 'store'])->middleware('can:manage-users');

    Route::get('/libraries', [LibrariesController::class, 'index'])->middleware('can:manage-libraries');
    Route::post('/libraries', [LibrariesController::class, 'store'])->middleware('can:manage-libraries');

    Route::get('/regions', [RegionsController::class, 'index'])->middleware('can:manage-libraries');
    Route::post('/regions', [RegionsController::class, 'store'])->middleware('can:manage-libraries');
    Route::get('/places', [PlacesController::class, 'index'])->middleware('can:manage-libraries');
    Route::post('/places', [PlacesController::class, 'store'])->middleware('can:manage-libraries');
});
