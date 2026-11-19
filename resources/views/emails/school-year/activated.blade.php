@extends('emails.layout')

@section('content')
    @php
        $active = $data['active_year'] ?? null;
        $previous = $data['previous_year'] ?? null;
        $dashboard = rtrim(\App\Support\FrontendUrl::url(), '/');
    @endphp

    <h1 style="color:#17324d;font-size:22px;line-height:1.3;margin:0 0 16px;">
        {{ __('emails.school_year.activated.title', ['name' => $active?->name]) }}
    </h1>

    <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
        {{ __('emails.school_year.activated.intro', ['name' => $active?->name]) }}
    </p>

    @if ($previous !== null)
        <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 8px;">
            {{ __('emails.school_year.activated.previous_year', ['name' => $previous->name]) }}
        </p>
    @endif

    <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
        {{ __('emails.school_year.activated.starts_on', ['date' => $active?->starts_on?->format('d.m.Y.')]) }}
    </p>

    <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:16px 0 0;">
        <a href="{{ $dashboard }}" style="color:#17324d;font-weight:700;">
            {{ __('emails.school_year.activated.action') }}
        </a>
    </p>
@endsection
