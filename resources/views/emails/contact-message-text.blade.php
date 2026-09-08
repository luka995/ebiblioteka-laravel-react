{{ __('emails.contact.greeting') }}

{{ __('emails.contact.intro') }}

{{ __('emails.contact.name_label') }}: {{ $data['name'] }}
{{ __('emails.contact.email_label') }}: {{ $data['email'] }}
{{ __('emails.contact.organization_label') }}: {{ $data['organization'] ?: __('emails.contact.organization_none') }}

{{ __('emails.contact.message_label') }}:
{{ $data['message'] }}
