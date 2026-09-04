<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Resources\UserResource;
use App\Models\Library;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('guest')
        ->name('login');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('guest')
        ->name('password.email');

    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('guest')
        ->name('password.store');

    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['auth', 'signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware(['auth', 'throttle:6,1'])
        ->name('verification.send');

    Route::get('/me', function (Request $request, ActiveLibraryService $activeLibrary) {
        $user = $request->user();
        $active = $activeLibrary->resolve($user);

        return (new UserResource($user))
            ->additional([
                'permissions' => app(AuthorizationService::class)->globalPermissions($user),
                'selectable_libraries' => $activeLibrary->selectable($user)
                    ->map(fn (Library $library) => [
                        'id' => $library->id,
                        'name' => $library->name,
                    ])
                    ->values(),
                'active_library' => $active instanceof Library
                    ? ['id' => $active->id, 'name' => $active->name]
                    : null,
            ]);
    })->middleware('auth')->name('me');

    Route::put('/active-library', function (Request $request, ActiveLibraryService $activeLibrary) {
        $data = $request->validate([
            'library_id' => ['nullable', 'integer'],
        ]);

        $activeLibrary->set($request->user(), $data['library_id'] ?? null);

        return response()->json(status: 204);
    })->middleware('auth')->name('active-library');
});
