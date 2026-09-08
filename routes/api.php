<?php

use App\Http\Controllers\LibrariesController;
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

    Route::get('/users/barcode/next', [UsersController::class, 'nextBarcode'])->middleware('can:create,App\Models\User');
    Route::get('/users', [UsersController::class, 'index'])->middleware('can:viewAny,App\Models\User');
    Route::post('/users', [UsersController::class, 'store'])->middleware('can:create,App\Models\User');
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
});
