<?php

namespace App\Http\Controllers;

use App\Models\SettingLog;
use App\Models\User;
use App\Queries\Concerns\AppliesTransliteratedSearch;
use App\Services\GlobalSettings;
use App\Services\UserAccessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    use AppliesTransliteratedSearch;

    /**
     * Globalna podesavanja — dostupno samo superadminima.
     */
    public function index(Request $request, GlobalSettings $settings): JsonResponse
    {
        $this->ensureSuperAdmin($request);
        $settings->pruneMissingSuperAdmins();

        $ids = $settings->superAdminIds();

        return response()->json([
            'data' => [
                'super_admin_user_ids' => $ids,
                'super_admins' => User::query()
                    ->whereIn('id', $ids)
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get()
                    ->map(fn (User $user): array => [
                        'id' => $user->id,
                        'name' => $user->displayName(),
                        'email' => $user->email,
                    ])
                    ->values(),
                'logs' => SettingLog::query()
                    ->with('changedBy')
                    ->latest()
                    ->limit(20)
                    ->get()
                    ->map(fn (SettingLog $log): array => [
                        'id' => $log->id,
                        'key' => $log->key,
                        'old_value' => $log->old_value,
                        'new_value' => $log->new_value,
                        'changed_by' => $log->changedBy?->displayName(),
                        'created_at' => $log->created_at?->toIso8601String(),
                        'created_at_formatted' => $log->created_at?->format('d.m.Y. H:i'),
                    ])
                    ->values(),
            ],
        ]);
    }

    public function update(Request $request, GlobalSettings $settings, UserAccessContext $access): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'super_admin_user_ids' => ['required', 'array', 'min:1'],
            'super_admin_user_ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        /** @var array<int, int> $newIds */
        $newIds = array_values(array_unique(array_map('intval', $data['super_admin_user_ids'])));
        $oldIds = $settings->superAdminIds();

        $this->ensureOnlyActiveUsers($newIds);

        $settings->set(GlobalSettings::SUPER_ADMIN_USER_IDS, $newIds, $request->user());

        foreach (array_unique([...$oldIds, ...$newIds]) as $id) {
            $access->flush($id);
        }

        return $this->index($request, $settings);
    }

    /**
     * Pretraga korisnika za izbor superadmina.
     */
    public function users(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        $term = trim((string) $request->query('search', ''));

        $users = User::query()
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function (Builder $group) use ($term): void {
                    $this->whereTransliterated($group, 'first_name', $term);
                    $this->whereTransliterated($group, 'last_name', $term, 'or');
                    $this->whereTransliterated($group, 'name', $term, 'or');
                    $this->whereTransliterated($group, 'email', $term, 'or');
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(25)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->displayName(),
                'email' => $user->email,
            ])
            ->values();

        return response()->json(['data' => $users]);
    }

    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function ensureOnlyActiveUsers(array $ids): void
    {
        $valid = User::query()->whereIn('id', $ids)->count();

        abort_unless($valid === count($ids), 422, __('validation.custom.super_admin_invalid_user'));
    }
}
