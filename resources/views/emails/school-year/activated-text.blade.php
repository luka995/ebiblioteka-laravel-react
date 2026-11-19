@php
    $active = $data['active_year'] ?? null;
    $previous = $data['previous_year'] ?? null;
    $dashboard = rtrim(\App\Support\FrontendUrl::url(), '/');
@endphp
{{ __('emails.school_year.activated.title', ['name' => $active?->name]) }}
{{ __('emails.brand.name') }} — {{ __('emails.brand.tagline') }}
============================================================

{{ __('emails.school_year.activated.intro', ['name' => $active?->name]) }}

@if ($previous !== null)
{{ __('emails.school_year.activated.previous_year', ['name' => $previous->name]) }}
@endif
{{ __('emails.school_year.activated.starts_on', ['date' => $active?->starts_on?->format('d.m.Y.')]) }}

{{ $dashboard }}
