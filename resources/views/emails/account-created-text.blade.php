{{ __('emails.account.greeting') }}

{{ __('emails.account.intro') }}

{{ __('emails.account.email_label') }}: {{ $user->email }}
{{ __('emails.account.password_label') }}: {{ $plainPassword }}

{{ __('emails.account.action') }}: {{ $loginUrl }}

{{ __('emails.account.outro') }}
