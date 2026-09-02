<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;

class PublicTheme
{
    public const CLASSIC = 'classic';

    public const REFERENCE = 'ref';

    public const COOKIE = 'public_theme';

    public const VIEW_PREFIX = 'themes.ref.';

    public static function active(): string
    {
        $themes = [self::CLASSIC, self::REFERENCE];

        $fromQuery = strtolower((string) request()->query('theme'));
        if (in_array($fromQuery, $themes, true)) {
            return $fromQuery;
        }

        $fromCookie = strtolower((string) Cookie::get(self::COOKIE));
        if (in_array($fromCookie, $themes, true)) {
            return $fromCookie;
        }

        $fromConfig = strtolower((string) config('app.public_theme', self::CLASSIC));

        return in_array($fromConfig, $themes, true) ? $fromConfig : self::CLASSIC;
    }

    public static function isReference(): bool
    {
        return self::active() === self::REFERENCE;
    }

    public static function view(string $view): string
    {
        return self::isReference() ? self::VIEW_PREFIX.$view : $view;
    }
}
