<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SettingLog;
use App\Models\User;

/**
 * Globalna podesavanja aplikacije, kesirana jednom po requestu/job-u.
 *
 * Servis je registrovan kao `scoped` u kontejneru: svi pozivi unutar istog
 * requesta dele keš, a na kraju requesta/job-a instanca se odbacuje. Time se
 * izbegava i ponovljeni SELECT i curenje podataka izmedju requestova.
 */
class GlobalSettings
{
    public const SUPER_ADMIN_USER_IDS = 'super_admin_user_ids';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values === null) {
            $this->values = Setting::query()
                ->get(['key', 'value'])
                ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value])
                ->all();
        }

        return $this->values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * @return array<int, int>
     */
    public function superAdminIds(): array
    {
        return collect((array) $this->get(self::SUPER_ADMIN_USER_IDS, []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function isSuperAdmin(User $user): bool
    {
        return in_array((int) $user->id, $this->superAdminIds(), true);
    }

    public function set(string $key, mixed $value, ?User $actor = null): void
    {
        $old = $this->get($key);

        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->values[$key] = $value;

        SettingLog::create([
            'key' => $key,
            'old_value' => $old,
            'new_value' => $value,
            'changed_by_id' => $actor?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values, ?User $actor = null): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $actor);
        }
    }

    public function addSuperAdmin(int $userId, ?User $actor = null): void
    {
        $ids = $this->superAdminIds();

        if (! in_array($userId, $ids, true)) {
            $ids[] = $userId;
        }

        $this->set(self::SUPER_ADMIN_USER_IDS, $ids, $actor);
    }

    public function removeSuperAdmin(int $userId, ?User $actor = null): void
    {
        $ids = $this->superAdminIds();

        if (! in_array($userId, $ids, true)) {
            return;
        }

        $this->set(
            self::SUPER_ADMIN_USER_IDS,
            array_values(array_filter($ids, fn (int $id): bool => $id !== $userId)),
            $actor,
        );
    }

    /**
     * Uklanja ID-jeve korisnika koji su fizicki obrisani. Settings nema FK,
     * pa je ovo jedina zastita od "mrtvih" superadmin ID-jeva.
     */
    public function pruneMissingSuperAdmins(): int
    {
        $ids = $this->superAdminIds();

        if ($ids === []) {
            return 0;
        }

        $existing = User::withTrashed()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $missing = array_values(array_diff($ids, $existing));

        if ($missing === []) {
            return 0;
        }

        $this->set(
            self::SUPER_ADMIN_USER_IDS,
            array_values(array_intersect($ids, $existing)),
            null,
        );

        return count($missing);
    }

    public function flush(): void
    {
        $this->values = null;
    }
}
