@extends('emails.layout')

@section('content')
    <h1 style="color:#17324d;font-size:20px;font-weight:bold;margin:0 0 16px;">{{ __('emails.reset.greeting') }}</h1>

    <p style="color:#52525b;font-size:16px;line-height:1.5;margin:0 0 24px;">{{ __('emails.reset.intro') }}</p>

    <p style="text-align:center;margin:32px 0;">
        <a href="{{ $resetUrl }}" style="display:inline-block;background-color:#ffd968;color:#17324d;font-size:16px;font-weight:bold;text-decoration:none;padding:14px 32px;border-radius:8px;">
            {{ __('emails.reset.action') }}
        </a>
    </p>

    <p style="color:#52525b;font-size:16px;line-height:1.5;margin:0 0 16px;">{{ __('emails.reset.outro') }}</p>

    <p style="color:#9aa1a9;font-size:12px;line-height:1.5;margin:0 0 24px;">{{ __('emails.reset.expiry', ['count' => config('auth.passwords.users.expire', 60)]) }}</p>

    <hr style="border:none;border-top:1px solid #e6e8eb;margin:24px 0;">

    <p style="color:#9aa1a9;font-size:12px;line-height:1.5;margin:0 0 8px;">{{ __('emails.reset.subcopy_intro', ['actionText' => __('emails.reset.action')]) }}</p>
    <p style="color:#52525b;font-size:12px;line-height:1.5;margin:0;word-break:break-all;">
        <a href="{{ $resetUrl }}" style="color:#17324d;">{{ $resetUrl }}</a>
    </p>
@endsection
