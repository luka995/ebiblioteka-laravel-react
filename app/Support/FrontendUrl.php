<?php

namespace App\Support;

class FrontendUrl
{
    public static function url(): string
    {
        return self::isPublicRequest()
            ? (string) config('app.frontend_url_public', 'https://dashboard.ebiblioteka.rs')
            : (string) config('app.frontend_url', 'http://localhost:3001');
    }

    public static function isPublicRequest(): bool
    {
        $host = (string) request()->host();

        if (str_ends_with($host, '.local')) {
            return false;
        }

        $localHosts = [
            'localhost',
            '127.0.0.1',
            '::1',
            'laravel.test',
            'ebiblioteka-new',
            'ebiblioteka-new.local',
            'react-frontend',
            'frontend',
            'nginx',
            'php',
        ];

        if (in_array($host, $localHosts, true)) {
            return false;
        }

        return true;
    }
}
