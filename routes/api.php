<?php

use App\Http\Controllers\AuthorsController;
use App\Http\Controllers\BarcodePrintJobsController;
use App\Http\Controllers\BookCopiesController;
use App\Http\Controllers\BooksController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\InventoryBooksController;
use App\Http\Controllers\LibrariesController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PlacesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegionsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\TagsController;
use App\Http\Controllers\UserMembershipsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\UserTagsController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::prefix('v1')->middleware('auth')->group(function (): void {
    Route::get('/roles', [RolesController::class, 'index']);
    Route::get('/roles/assignable', [RolesController::class, 'assignable']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    Route::post('/users/memberships/deactivate', [UserMembershipsController::class, 'bulkDeactivate'])->middleware('can:bulkManageMemberships,App\Models\User');
    Route::post('/users/memberships/activate', [UserMembershipsController::class, 'bulkActivate'])->middleware('can:bulkManageMemberships,App\Models\User');
    Route::delete('/users/memberships', [UserMembershipsController::class, 'bulkRemove'])->middleware('can:bulkRemoveMemberships,App\Models\User');
    Route::post('/users/bulk/deactivate', [UsersController::class, 'bulkDestroy'])->middleware('can:bulkDelete,App\Models\User');
    Route::delete('/users/bulk/force', [UsersController::class, 'bulkForceDestroy'])->middleware('can:bulkForceDelete,App\Models\User');
    Route::post('/users/bulk/barcode', [UsersController::class, 'bulkRegenerateBarcode'])->middleware('can:bulkRegenerateBarcode,App\Models\User');
    Route::post('/users/bulk/barcode/print', [UsersController::class, 'bulkPrintBarcode'])->middleware('can:bulkPrintBarcode,App\Models\User');

    Route::get('/users', [UsersController::class, 'index'])->middleware('can:viewAny,App\Models\User');
    Route::post('/users', [UsersController::class, 'store'])->middleware('can:create,App\Models\User');
    Route::get('/users/{user}/barcode', [UsersController::class, 'barcode'])->middleware('can:view,user');
    Route::get('/users/{user}/barcode/print', [UsersController::class, 'printBarcode'])->middleware('can:view,user');
    Route::get('/users/{user}', [UsersController::class, 'show'])->middleware('can:view,user');
    Route::put('/users/{user}', [UsersController::class, 'update'])->middleware('can:update,user');
    Route::put('/users/{user}/password', [UsersController::class, 'updatePassword'])->middleware('can:update,user');
    Route::delete('/users/{user}', [UsersController::class, 'destroy'])->middleware('can:delete,user');
    Route::delete('/users/{user}/force', [UsersController::class, 'forceDestroy'])->middleware('can:forceDelete,user');
    Route::post('/users/{user}/libraries/deactivate', [UserMembershipsController::class, 'deactivate'])->middleware('can:manageMemberships,user');
    Route::post('/users/{user}/libraries/activate', [UserMembershipsController::class, 'activate'])->middleware('can:manageMemberships,user');
    Route::delete('/users/{user}/libraries', [UserMembershipsController::class, 'destroy'])->middleware('can:removeMemberships,user');
    Route::put('/users/{user}/tags', [UserTagsController::class, 'sync'])->middleware('can:update,user');
    Route::post('/users/tags/assign', [UserTagsController::class, 'assign'])->middleware('can:viewAny,App\Models\User');
    Route::post('/users/tags/remove', [UserTagsController::class, 'remove'])->middleware('can:viewAny,App\Models\User');

    Route::get('/libraries', [LibrariesController::class, 'index'])->middleware('can:viewAny,App\Models\Library');
    Route::post('/libraries', [LibrariesController::class, 'store'])->middleware('can:create,App\Models\Library');
    Route::get('/libraries/{library}', [LibrariesController::class, 'show'])->middleware('can:view,library');
    Route::put('/libraries/{library}', [LibrariesController::class, 'update'])->middleware('can:update,library');
    Route::delete('/libraries/{library}', [LibrariesController::class, 'destroy'])->middleware('can:delete,library');
    Route::post('/libraries/{library}/restore', [LibrariesController::class, 'restore'])->middleware('can:restore,library');

    Route::get('/regions', [RegionsController::class, 'index'])->middleware('can:viewAny,App\Models\Region');
    Route::post('/regions', [RegionsController::class, 'store'])->middleware('can:create,App\Models\Region');
    Route::put('/regions/{region}', [RegionsController::class, 'update'])->middleware('can:update,region');
    Route::delete('/regions/{region}', [RegionsController::class, 'destroy'])->middleware('can:delete,region');

    Route::get('/places', [PlacesController::class, 'index'])->middleware('can:viewAny,App\Models\Place');
    Route::post('/places', [PlacesController::class, 'store'])->middleware('can:create,App\Models\Place');
    Route::put('/places/{place}', [PlacesController::class, 'update'])->middleware('can:update,place');
    Route::delete('/places/{place}', [PlacesController::class, 'destroy'])->middleware('can:delete,place');

    Route::get('/tags', [TagsController::class, 'index'])->middleware('can:viewAny,App\Models\Tag');
    Route::post('/tags', [TagsController::class, 'store'])->middleware('can:create,App\Models\Tag');
    Route::put('/tags/{tag}', [TagsController::class, 'update'])->middleware('can:update,tag');
    Route::delete('/tags/{tag}', [TagsController::class, 'destroy'])->middleware('can:delete,tag');

    Route::get('/categories', [CategoriesController::class, 'index'])->middleware('can:viewAny,App\Models\Category');
    Route::post('/categories', [CategoriesController::class, 'store'])->middleware('can:create,App\Models\Category');
    Route::put('/categories/{category}', [CategoriesController::class, 'update'])->middleware('can:update,category');
    Route::delete('/categories/{category}', [CategoriesController::class, 'destroy'])->middleware('can:delete,category');

    Route::get('/authors', [AuthorsController::class, 'index'])->middleware('can:viewAny,App\Models\Author');
    Route::post('/authors', [AuthorsController::class, 'store'])->middleware('can:create,App\Models\Author');
    Route::put('/authors/{author}', [AuthorsController::class, 'update'])->middleware('can:update,author');
    Route::delete('/authors/{author}', [AuthorsController::class, 'destroy'])->middleware('can:delete,author');

    Route::get('/books/isbn-lookup', [BooksController::class, 'isbnLookup'])->middleware('can:viewAny,App\Models\Book');
    Route::get('/books/duplicate-check', [BooksController::class, 'duplicateCheck'])->middleware('can:viewAny,App\Models\Book');
    Route::get('/books/archive', [BooksController::class, 'archive'])->middleware('can:viewAny,App\Models\Book');
    Route::get('/books', [BooksController::class, 'index'])->middleware('can:viewAny,App\Models\Book');
    Route::post('/books/upload-cover', [BooksController::class, 'uploadCover'])->middleware('can:create,App\Models\Book');
    Route::post('/books', [BooksController::class, 'store'])->middleware('can:create,App\Models\Book');
    Route::post('/books/{book}/restore', [BooksController::class, 'restore'])->middleware('can:restore,book')->withTrashed();
    Route::delete('/books/{book}/force', [BooksController::class, 'forceDestroy'])->middleware('can:forceDelete,book')->withTrashed();
    Route::get('/books/{book}', [BooksController::class, 'show'])->middleware('can:view,book');
    Route::put('/books/{book}', [BooksController::class, 'update'])->middleware('can:update,book');
    Route::delete('/books/{book}', [BooksController::class, 'destroy'])->middleware('can:delete,book');

    Route::post('/books/{book}/copies', [BookCopiesController::class, 'store'])->middleware('can:update,book');

    Route::post('/book-copies/bulk/print', [BookCopiesController::class, 'bulkPrint'])->middleware('can:viewAny,App\Models\BookCopy');
    Route::post('/book-copies/inventory-sequence/sync', [BookCopiesController::class, 'syncInventorySequence'])->middleware('can:syncInventory,App\Models\BookCopy');
    Route::get('/book-copies/archive', [BookCopiesController::class, 'archive'])->middleware('can:viewAny,App\Models\BookCopy');
    Route::get('/book-copies', [BookCopiesController::class, 'index'])->middleware('can:viewAny,App\Models\BookCopy');
    Route::post('/book-copies/{bookCopy}/restore', [BookCopiesController::class, 'restore'])->middleware('can:restore,bookCopy')->withTrashed();
    Route::delete('/book-copies/{bookCopy}/force', [BookCopiesController::class, 'forceDestroy'])->middleware('can:forceDelete,bookCopy')->withTrashed();
    Route::post('/book-copies/{bookCopy}/write-off', [BookCopiesController::class, 'writeOff'])->middleware('can:writeOff,bookCopy');
    Route::post('/book-copies/{bookCopy}/write-off/cancel', [BookCopiesController::class, 'cancelWriteOff'])->middleware('can:writeOff,bookCopy');
    Route::put('/book-copies/{bookCopy}/rec-error', [BookCopiesController::class, 'recError'])->middleware('can:update,bookCopy');
    Route::get('/book-copies/{bookCopy}', [BookCopiesController::class, 'show'])->middleware('can:view,bookCopy');
    Route::put('/book-copies/{bookCopy}', [BookCopiesController::class, 'update'])->middleware('can:update,bookCopy');
    Route::delete('/book-copies/{bookCopy}', [BookCopiesController::class, 'destroy'])->middleware('can:delete,bookCopy');

    Route::get('/barcode-print-jobs', [BarcodePrintJobsController::class, 'index'])->middleware('can:viewAny,App\Models\BarcodePrintJob');
    Route::post('/barcode-print-jobs', [BarcodePrintJobsController::class, 'store'])->middleware('can:create,App\Models\BarcodePrintJob');
    Route::get('/barcode-print-jobs/{barcodePrintJob}/download', [BarcodePrintJobsController::class, 'download'])->middleware('can:download,barcodePrintJob');
    Route::delete('/barcode-print-jobs/{barcodePrintJob}', [BarcodePrintJobsController::class, 'destroy'])->middleware('can:delete,barcodePrintJob');
    Route::get('/barcode-print-jobs/{barcodePrintJob}', [BarcodePrintJobsController::class, 'show'])->middleware('can:view,barcodePrintJob');

    Route::get('/inventory-books', [InventoryBooksController::class, 'index'])->middleware('can:viewAny,App\Models\InventoryBook');
    Route::post('/inventory-books', [InventoryBooksController::class, 'store'])->middleware('can:create,App\Models\InventoryBook');
    Route::get('/inventory-books/{inventoryBook}/download', [InventoryBooksController::class, 'download'])->middleware('can:download,inventoryBook');
    Route::get('/inventory-books/{inventoryBook}', [InventoryBooksController::class, 'show'])->middleware('can:view,inventoryBook');

    Route::get('/news', [NewsController::class, 'index'])->middleware('can:viewAny,App\Models\News');
    Route::post('/news', [NewsController::class, 'store'])->middleware('can:create,App\Models\News');
    Route::post('/news/upload-image', [NewsController::class, 'uploadImage'])->middleware('can:create,App\Models\News');
    Route::get('/news/{news}', [NewsController::class, 'show'])->middleware('can:view,news');
    Route::put('/news/{news}', [NewsController::class, 'update'])->middleware('can:update,news');
    Route::delete('/news/{news}', [NewsController::class, 'destroy'])->middleware('can:delete,news');
});
