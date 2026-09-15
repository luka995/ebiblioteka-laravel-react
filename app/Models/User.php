<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Services\Mail\UserMailService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
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
            'role' => UserRole::class,
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        app(UserMailService::class)->sendPasswordReset($this, $token);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role->isSuperAdmin();
    }

    public function isLibraryAdmin(): bool
    {
        return $this->role->isLibraryAdmin();
    }

    /**
     * Administrativne role (superadmin + library_admin).
     */
    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->isLibraryAdmin();
    }

    /**
     * Osoblje biblioteke (superadmin + library_admin + librarian).
     */
    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function canManageLibrary(): bool
    {
        return $this->role->canManageLibrary();
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
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
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
            ->withTimestamps()
            ->withPivot('deleted_at');
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

        return $this->libraries()->whereKey($libraryId)->exists();
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
