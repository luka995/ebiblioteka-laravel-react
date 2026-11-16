<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;

/**
 * Uloga korisnika u konkretnoj biblioteci (library_user.role).
 *
 * Superadmin nije deo ove uloge — to je globalna privilegija koja se cuva
 * u tabeli `settings`.
 */
enum LibraryRole: string
{
    use EnumToArray;

    case User = 'user';
    case Librarian = 'librarian';
    case LibraryAdmin = 'library_admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::User => __('roles.user'),
            self::Librarian => __('roles.librarian'),
            self::LibraryAdmin => __('roles.library_admin'),
        };
    }

    public function isLibraryAdmin(): bool
    {
        return $this === self::LibraryAdmin;
    }

    /**
     * Osoblje biblioteke (librarian + library_admin).
     */
    public function isStaff(): bool
    {
        return in_array($this, [self::Librarian, self::LibraryAdmin], true);
    }

    public function canManageLibrary(): bool
    {
        return $this->isStaff();
    }

    /**
     * Role koje dati akter sme da dodeli unutar biblioteke.
     *
     * @return array<int, self>
     */
    public static function assignableBy(self $actor): array
    {
        return match ($actor) {
            self::LibraryAdmin => [self::Librarian, self::User],
            self::Librarian, self::User => [self::User],
        };
    }

    public static function fromLegacy(string $legacyRole): ?self
    {
        return match (strtolower($legacyRole)) {
            'library_admin', 'roole_admin', 'role_admin' => self::LibraryAdmin,
            'librarian', 'roole_librarian', 'role_librarian' => self::Librarian,
            'user', 'roole_user', 'role_user' => self::User,
            default => null,
        };
    }
}
