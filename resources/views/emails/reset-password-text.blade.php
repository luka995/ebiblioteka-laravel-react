{{ __('emails.reset.greeting') }}

{{ __('emails.reset.intro') }}

{{ __('emails.reset.action') }}: {{ $resetUrl }}

{{ __('emails.reset.outro') }}

{{ __('emails.reset.expiry', ['count' => config('auth.passwords.users.expire', 60)]) }}
