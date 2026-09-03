<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
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

    public function isSuperAdmin(): bool
    {
        return $this->role->isSuperAdmin();
    }

    public function isLibraryAdmin(): bool
    {
        return $this->role->isLibraryAdmin();
    }

    public function canManageLibrary(): bool
    {
        return $this->role->canManageLibrary();
    }

    /**
     * Biblioteke u kojima korisnik ima nalog (many-to-many).
     *
     * @return BelongsToMany<Library, $this>
     */
    public function libraries(): BelongsToMany
    {
        return $this->belongsToMany(Library::class)->withTimestamps();
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
