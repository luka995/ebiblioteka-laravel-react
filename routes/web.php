<?php

use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\PublicLibraryController;
use App\Http\Controllers\PublicNewsController;
use App\Http\Controllers\PublicProjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomeController::class)->name('home');
Route::get('/o-projektu', PublicProjectController::class)->name('project.about');
Route::get('/kontakt', [PublicContactController::class, 'create'])->name('contact.create');
Route::post('/kontakt', [PublicContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
Route::get('/biblioteke', [PublicLibraryController::class, 'index'])->name('libraries.index');
Route::get('/biblioteke/{library}/kategorija/{category}', [PublicLibraryController::class, 'category'])->name('libraries.category');
Route::get('/biblioteke/{library}/kategorija/{category}/knjiga/{book}', [PublicLibraryController::class, 'book'])->name('libraries.book');
Route::get('/biblioteke/{library}', [PublicLibraryController::class, 'show'])->name('libraries.show');
Route::get('/novosti', [PublicNewsController::class, 'index'])->name('news.index');
Route::get('/novosti/{news:slug}', [PublicNewsController::class, 'show'])->name('news.show');
