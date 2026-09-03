<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;

/**
 * Role korisnika u sistemu eBiblioteka.
 *
 * Mapiranje iz Symfony/FOS legacy resenja (videti PRD sekciju 5.2):
 * - ROLE_SUPER_ADMIN -> SuperAdmin
 * - ROLE_ADMIN       -> LibraryAdmin
 * - ROLE_LIBRARAIN   -> Librarian
 * - ROLE_ENDUSER/USER -> User
 */
enum UserRole: string
{
    use EnumToArray;

    case SuperAdmin = 'superadmin';
    case LibraryAdmin = 'library_admin';
    case Librarian = 'librarian';
    case User = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => __('roles.superadmin'),
            self::LibraryAdmin => __('roles.library_admin'),
            self::Librarian => __('roles.librarian'),
            self::User => __('roles.user'),
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function isLibraryAdmin(): bool
    {
        return $this === self::LibraryAdmin;
    }

    /**
     * Osoblje biblioteke (moze da upravlja podacima svoje biblioteke).
     */
    public function isStaff(): bool
    {
        return in_array($this, [self::SuperAdmin, self::LibraryAdmin, self::Librarian], true);
    }

    public function canManageLibrary(): bool
    {
        return in_array($this, [self::SuperAdmin, self::LibraryAdmin, self::Librarian], true);
    }

    /**
     * Role koje dati akter sme da dodeli prilikom kreiranja korisnika.
     *
     * @return array<int, self>
     */
    public static function assignableBy(UserRole $actor): array
    {
        return match ($actor) {
            self::SuperAdmin => self::cases(),
            self::LibraryAdmin => [self::Librarian, self::User],
            self::Librarian, self::User => [self::User],
        };
    }

    public static function fromLegacy(string $legacyRole): ?self
    {
        return match (strtoupper($legacyRole)) {
            'ROLE_SUPER_ADMIN' => self::SuperAdmin,
            'ROLE_ADMIN', 'ROLE_LIBRARY_ADMIN' => self::LibraryAdmin,
            'ROLE_LIBRARAIN', 'ROLE_LIBRARIAN' => self::Librarian,
            'ROLE_ENDUSER', 'ROLE_USER' => self::User,
            default => null,
        };
    }
}
