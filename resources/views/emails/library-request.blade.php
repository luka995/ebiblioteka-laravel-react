@extends('emails.layout')

@section('content')
    <h1 style="color:#17324d;font-size:20px;font-weight:bold;margin:0 0 16px;">{{ __('emails.library_request.greeting') }}</h1>

    <p style="color:#52525b;font-size:16px;line-height:1.5;margin:0 0 24px;">{{ __('emails.library_request.intro') }}</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f5f7fa;border:1px solid #e6e8eb;border-radius:8px;">
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#9aa1a9;font-size:13px;white-space:nowrap;">{{ __('emails.library_request.school_label') }}</td>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#17324d;font-size:15px;font-weight:bold;">{{ $libraryRequest->school_name }}</td>
        </tr>
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#9aa1a9;font-size:13px;white-space:nowrap;">{{ __('emails.library_request.type_label') }}</td>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#17324d;font-size:15px;font-weight:bold;">{{ $libraryRequest->schoolTypeLabel() }}</td>
        </tr>
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#9aa1a9;font-size:13px;white-space:nowrap;">{{ __('emails.library_request.city_label') }}</td>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#17324d;font-size:15px;font-weight:bold;">{{ $libraryRequest->city }}</td>
        </tr>
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#9aa1a9;font-size:13px;white-space:nowrap;">{{ __('emails.library_request.address_label') }}</td>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#17324d;font-size:15px;font-weight:bold;">{{ $libraryRequest->address }}</td>
        </tr>
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#9aa1a9;font-size:13px;white-space:nowrap;">{{ __('emails.library_request.contact_label') }}</td>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#17324d;font-size:15px;font-weight:bold;">{{ $libraryRequest->contact_name }}</td>
        </tr>
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#9aa1a9;font-size:13px;white-space:nowrap;">{{ __('emails.library_request.email_label') }}</td>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#17324d;font-size:15px;font-weight:bold;">{{ $libraryRequest->contact_email }}</td>
        </tr>
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#9aa1a9;font-size:13px;white-space:nowrap;">{{ __('emails.library_request.phone_label') }}</td>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#17324d;font-size:15px;font-weight:bold;">{{ $libraryRequest->contact_phone }}</td>
        </tr>
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#9aa1a9;font-size:13px;white-space:nowrap;">{{ __('emails.library_request.students_label') }}</td>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#17324d;font-size:15px;font-weight:bold;">{{ $libraryRequest->student_count ?? __('emails.library_request.none') }}</td>
        </tr>
        <tr>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#9aa1a9;font-size:13px;white-space:nowrap;">{{ __('emails.library_request.employees_label') }}</td>
            <td style="padding:16px 20px;border-bottom:1px solid #e6e8eb;color:#17324d;font-size:15px;font-weight:bold;">{{ $libraryRequest->employee_count ?? __('emails.library_request.none') }}</td>
        </tr>
        <tr>
            <td style="padding:16px 20px;color:#9aa1a9;font-size:13px;white-space:nowrap;vertical-align:top;">{{ __('emails.library_request.message_label') }}</td>
            <td style="padding:16px 20px;color:#17324d;font-size:15px;line-height:1.5;">{!! $libraryRequest->message ? nl2br(e($libraryRequest->message)) : e(__('emails.library_request.none')) !!}</td>
        </tr>
    </table>
@endsection
