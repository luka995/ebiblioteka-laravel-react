<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\PublicLibraryController;
use App\Http\Controllers\PublicProjectController;

Route::get('/', PublicHomeController::class)->name('home');
Route::get('/o-projektu', PublicProjectController::class)->name('project.about');
Route::get('/biblioteke', [PublicLibraryController::class, 'index'])->name('libraries.index');
Route::get('/biblioteke/{library}/kategorija/{category}', [PublicLibraryController::class, 'category'])->name('libraries.category');
Route::get('/biblioteke/{library}/kategorija/{category}/knjiga/{book}', [PublicLibraryController::class, 'book'])->name('libraries.book');
Route::get('/biblioteke/{library}', [PublicLibraryController::class, 'show'])->name('libraries.show');

require __DIR__.'/auth.php';
