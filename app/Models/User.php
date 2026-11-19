<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\LibraryRole;
use App\Services\ActiveLibraryService;
use App\Services\GlobalSettings;
use App\Services\Mail\UserMailService;
use App\Services\UserAccessContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'locale',
    'password',
    'username',
    'first_name',
    'last_name',
    'jmbg',
    'address',
    'city',
    'post_code',
    'bar_code',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        app(UserMailService::class)->sendPasswordReset($this, $token);
    }

    protected static function booted(): void
    {
        // Settings nema FK na users; fizicki obrisan superadmin mora da
        // nestane i iz super_admin_user_ids.
        static::forceDeleted(function (User $user): void {
            app(GlobalSettings::class)->removeSuperAdmin((int) $user->id);
        });
    }

    /**
     * Globalna superadmin privilegija dolazi iz settings tabele i kešira se
     * jednom po requestu/job-u.
     */
    public function isSuperAdmin(): bool
    {
        return app(UserAccessContext::class)->isSuperAdmin($this);
    }

    public function roleIn(int|Library $library): ?LibraryRole
    {
        $libraryId = $library instanceof Library ? (int) $library->id : $library;

        return app(UserAccessContext::class)->roleIn($this, $libraryId);
    }

    public function hasRoleIn(int|Library $library, LibraryRole $role): bool
    {
        return $this->roleIn($library) === $role;
    }

    public function isMemberOf(int|Library $library): bool
    {
        $libraryId = $library instanceof Library ? (int) $library->id : $library;

        return app(UserAccessContext::class)->isMemberOf($this, $libraryId);
    }

    public function isStaffIn(int|Library $library): bool
    {
        $libraryId = $library instanceof Library ? (int) $library->id : $library;

        return app(UserAccessContext::class)->isStaffIn($this, $libraryId);
    }

    public function isLibraryAdminIn(int|Library $library): bool
    {
        $libraryId = $library instanceof Library ? (int) $library->id : $library;

        return app(UserAccessContext::class)->isLibraryAdminIn($this, $libraryId);
    }

    /**
     * Uloga u aktivnoj biblioteci (za globalne/nav permisije).
     */
    public function activeRole(): ?LibraryRole
    {
        $library = app(ActiveLibraryService::class)->resolve($this);

        return $library === null ? null : $this->roleIn($library->id);
    }

    public function isLibraryAdmin(?int $libraryId = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($libraryId !== null) {
            return $this->isLibraryAdminIn($libraryId);
        }

        $active = app(ActiveLibraryService::class)->resolve($this);

        if ($active !== null) {
            return $this->isLibraryAdminIn($active->id);
        }

        // Bez aktivne biblioteke propustamo do controllera koji vraca 422
        // ("izaberite aktivnu biblioteku"), ali samo ako je korisnik admin
        // u bar jednoj biblioteci.
        return $this->isLibraryAdminAnywhere();
    }

    /**
     * Da li je korisnik osoblje u bar jednoj biblioteci (bez potrebe za
     * aktivnom bibliotekom). Koristi se za collection-level policy provere.
     */
    public function isStaffAnywhere(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach (app(UserAccessContext::class)->roles($this) as $role) {
            if ($role->isStaff()) {
                return true;
            }
        }

        return false;
    }

    public function isLibraryAdminAnywhere(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach (app(UserAccessContext::class)->roles($this) as $role) {
            if ($role->isLibraryAdmin()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Administrativne role (superadmin + library_admin u aktivnoj biblioteci).
     */
    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->isLibraryAdmin();
    }

    /**
     * Osoblje biblioteke (superadmin + library_admin + librarian u aktivnoj biblioteci).
     */
    public function isStaff(?int $libraryId = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($libraryId !== null) {
            return $this->isStaffIn($libraryId);
        }

        $active = app(ActiveLibraryService::class)->resolve($this);

        if ($active !== null) {
            return $this->isStaffIn($active->id);
        }

        // Bez aktivne biblioteke propustamo do controllera koji vraca 422.
        return $this->isStaffAnywhere();
    }

    public function canManageLibrary(?int $libraryId = null): bool
    {
        return $this->isStaff($libraryId);
    }

    /**
     * Aktivne biblioteke u kojima korisnik ima nalog (many-to-many).
     *
     * Isključuje deaktivirana članstva (soft-deleted pivot redove).
     *
     * @return BelongsToMany<Library, $this>
     */
    public function libraries(): BelongsToMany
    {
        return $this->belongsToMany(Library::class)
            ->using(LibraryUserPivot::class)
            ->withPivot('role')
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    /**
     * Rezervacije korisnika (ukljucujuci istoriju).
     *
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Pozajmice korisnika (ukljucujuci istoriju).
     *
     * @return HasMany<Borrow, $this>
     */
    public function borrows(): HasMany
    {
        return $this->hasMany(Borrow::class);
    }

    /**
     * Trenutna odeljenja korisnika po bibliotekama i skolskim godinama.
     *
     * @return HasMany<ClassEnrollment, $this>
     */
    public function classEnrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    /**
     * Sva trenutna odeljenja korisnika (jedan red po biblioteci i godini).
     *
     * @return BelongsToMany<SchoolClass, $this>
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'student_class', 'user_id', 'class_id')
            ->withPivot(['library_id', 'school_year_id', 'assigned_by_id'])
            ->withTimestamps();
    }

    public function getClassByLibrary(int|Library $library, ?SchoolYear $schoolYear = null): ?SchoolClass
    {
        $libraryId = $library instanceof Library ? (int) $library->id : $library;
        $schoolYear ??= SchoolYear::getActiveSchoolYear();

        if ($schoolYear === null) {
            return null;
        }

        return $this->classes()
            ->where('classes.library_id', $libraryId)
            ->where('classes.school_year_id', $schoolYear->id)
            ->where('classes.is_active', true)
            ->first();
    }

    /**
     * Istorija promena odeljenja korisnika.
     *
     * @return HasMany<ClassroomHistory, $this>
     */
    public function classroomHistory(): HasMany
    {
        return $this->hasMany(ClassroomHistory::class);
    }

    /**
     * Sve biblioteke uključujući deaktivirana članstva.
     *
     * @return BelongsToMany<Library, $this>
     */
    public function librariesWithTrashed(): BelongsToMany
    {
        return $this->belongsToMany(Library::class)
            ->using(LibraryUserPivot::class)
            ->withPivot(['role', 'deleted_at'])
            ->withTimestamps();
    }

    /**
     * Tagovi dodeljeni korisniku (many-to-many, per-biblioteka preko tag.library_id).
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /**
     * Da li korisnik upravlja datom bibliotekom (superadmin: sve; inače član te biblioteke).
     */
    public function managesLibrary(int $libraryId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->isMemberOf($libraryId);
    }

    /**
     * Da li korisnik deli bar jednu aktivnu biblioteku sa drugim korisnikom.
     */
    public function sharesLibraryWith(User $other): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $otherIds = $other->libraries()->pluck('libraries.id');

        return $this->libraries()->whereIn('libraries.id', $otherIds)->exists();
    }

    /**
     * Ime za prikaz: firstName + lastName, a ako oni nisu popunjeni koristi "name".
     */
    public function displayName(): string
    {
        if ($this->first_name || $this->last_name) {
            return trim($this->first_name.' '.$this->last_name);
        }

        return $this->name;
    }
}
