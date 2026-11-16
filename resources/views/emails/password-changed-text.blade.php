{{ __('emails.password_changed.greeting') }}

{{ __('emails.password_changed.intro') }}

{{ __('emails.password_changed.email_label') }}: {{ $user->email }}
{{ __('emails.password_changed.password_label') }}: {{ $plainPassword }}

{{ __('emails.password_changed.action') }}: {{ $loginUrl }}

{{ __('emails.password_changed.outro') }}
