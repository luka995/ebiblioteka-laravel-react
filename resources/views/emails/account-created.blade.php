@extends('emails.layout')

@section('content')
    <h1 style="color:#17324d;font-size:20px;font-weight:bold;margin:0 0 16px;">{{ __('emails.account.greeting') }}</h1>

    <p style="color:#52525b;font-size:16px;line-height:1.5;margin:0 0 24px;">{{ __('emails.account.intro') }}</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f5f7fa;border:1px solid #e6e8eb;border-radius:8px;margin:0 0 32px;">
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#9aa1a9;font-size:13px;">{{ __('emails.account.email_label') }}</td>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#17324d;font-size:15px;font-weight:bold;">{{ $user->email }}</td>
        </tr>
        <tr>
            <td style="padding:16px 20px;color:#9aa1a9;font-size:13px;">{{ __('emails.account.password_label') }}</td>
            <td style="padding:16px 20px;color:#17324d;font-size:15px;font-weight:bold;">{{ $plainPassword }}</td>
        </tr>
    </table>

    <p style="text-align:center;margin:32px 0;">
        <a href="{{ $loginUrl }}" style="display:inline-block;background-color:#ffd968;color:#17324d;font-size:16px;font-weight:bold;text-decoration:none;padding:14px 32px;border-radius:8px;">
            {{ __('emails.account.action') }}
        </a>
    </p>

    <p style="color:#52525b;font-size:16px;line-height:1.5;margin:0;">{{ __('emails.account.outro') }}</p>
@endsection
