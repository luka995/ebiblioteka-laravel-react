<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>{{ __('emails.brand.name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f7fa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f5f7fa;width:100%;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:600px;max-width:600px;margin:0 auto;">

                    <tr>
                        <td align="center" style="background-color:#17324d;border-radius:12px 12px 0 0;padding:24px 32px;">
                            <img src="{{ $message->embed(public_path('img/ebiblioteka-logo.png')) }}" width="30" height="30" alt="{{ __('emails.brand.name') }}" style="display:inline-block;vertical-align:middle;border:0;border-radius:7px;">
                            <span style="display:inline-block;vertical-align:middle;color:#ffffff;font-size:22px;font-weight:900;letter-spacing:0.5px;margin-left:12px;">{{ __('emails.brand.name') }}</span>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#ffffff;padding:40px 32px;border-left:1px solid #e6e8eb;border-right:1px solid #e6e8eb;">
                            @yield('content')
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#ffffff;padding:0 32px 32px;border-radius:0 0 12px 12px;border-left:1px solid #e6e8eb;border-right:1px solid #e6e8eb;border-bottom:1px solid #e6e8eb;">
                            <p style="color:#9aa1a9;font-size:12px;line-height:1.5;margin:0;text-align:center;">{{ __('emails.brand.name') }} &mdash; {{ __('emails.brand.tagline') }}</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
