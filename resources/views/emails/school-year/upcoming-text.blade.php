@php
    $next = $data['next_year'] ?? null;
    $active = $data['active_year'] ?? null;
    $days = $data['days_until'] ?? 7;
    $dashboard = rtrim(\App\Support\FrontendUrl::url(), '/');
@endphp
{{ __('emails.school_year.upcoming.title', ['name' => $next?->name]) }}
{{ __('emails.brand.name') }} — {{ __('emails.brand.tagline') }}
============================================================

{{ __('emails.school_year.upcoming.intro', ['name' => $next?->name, 'days' => $days]) }}

{{ __('emails.school_year.upcoming.active_year', ['name' => $active?->name]) }}
{{ __('emails.school_year.upcoming.starts_on', ['date' => $next?->starts_on?->format('d.m.Y.')]) }}

{{ __('emails.school_year.upcoming.prepare') }}

{{ $dashboard }}
