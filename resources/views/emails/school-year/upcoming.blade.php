@extends('emails.layout')

@section('content')
    @php
        $next = $data['next_year'] ?? null;
        $active = $data['active_year'] ?? null;
        $days = $data['days_until'] ?? 7;
        $dashboard = rtrim(\App\Support\FrontendUrl::url(), '/');
    @endphp

    <h1 style="color:#17324d;font-size:22px;line-height:1.3;margin:0 0 16px;">
        {{ __('emails.school_year.upcoming.title', ['name' => $next?->name]) }}
    </h1>

    <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
        {{ __('emails.school_year.upcoming.intro', ['name' => $next?->name, 'days' => $days]) }}
    </p>

    <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 8px;">
        {{ __('emails.school_year.upcoming.active_year', ['name' => $active?->name]) }}
    </p>

    <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
        {{ __('emails.school_year.upcoming.starts_on', ['date' => $next?->starts_on?->format('d.m.Y.')]) }}
    </p>

    <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
        {{ __('emails.school_year.upcoming.prepare') }}
    </p>

    <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:16px 0 0;">
        <a href="{{ $dashboard }}" style="color:#17324d;font-weight:700;">
            {{ __('emails.school_year.upcoming.action') }}
        </a>
    </p>
@endsection
