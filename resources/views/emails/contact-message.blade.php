@extends('emails.layout')

@section('content')
    <h1 style="color:#17324d;font-size:20px;font-weight:bold;margin:0 0 16px;">{{ __('emails.contact.greeting') }}</h1>

    <p style="color:#52525b;font-size:16px;line-height:1.5;margin:0 0 24px;">{{ __('emails.contact.intro') }}</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f5f7fa;border:1px solid #e6e8eb;border-radius:8px;">
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#9aa1a9;font-size:13px;white-space:nowrap;">{{ __('emails.contact.name_label') }}</td>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#17324d;font-size:15px;font-weight:bold;">{{ $data['name'] }}</td>
        </tr>
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#9aa1a9;font-size:13px;white-space:nowrap;">{{ __('emails.contact.email_label') }}</td>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#17324d;font-size:15px;font-weight:bold;">{{ $data['email'] }}</td>
        </tr>
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#9aa1a9;font-size:13px;white-space:nowrap;">{{ __('emails.contact.organization_label') }}</td>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#17324d;font-size:15px;font-weight:bold;">{{ $data['organization'] ?: __('emails.contact.organization_none') }}</td>
        </tr>
        <tr>
            <td style="padding:16px 20px;color:#9aa1a9;font-size:13px;white-space:nowrap;vertical-align:top;">{{ __('emails.contact.message_label') }}</td>
            <td style="padding:16px 20px;color:#17324d;font-size:15px;line-height:1.5;">{!! nl2br(e($data['message'])) !!}</td>
        </tr>
    </table>
@endsection
