{{ __('emails.library_request.greeting') }}

{{ __('emails.library_request.intro') }}

{{ __('emails.library_request.school_label') }}: {{ $libraryRequest->school_name }}
{{ __('emails.library_request.type_label') }}: {{ $libraryRequest->schoolTypeLabel() }}
{{ __('emails.library_request.city_label') }}: {{ $libraryRequest->city }}
{{ __('emails.library_request.address_label') }}: {{ $libraryRequest->address }}
{{ __('emails.library_request.contact_label') }}: {{ $libraryRequest->contact_name }}
{{ __('emails.library_request.email_label') }}: {{ $libraryRequest->contact_email }}
{{ __('emails.library_request.phone_label') }}: {{ $libraryRequest->contact_phone }}
{{ __('emails.library_request.students_label') }}: {{ $libraryRequest->student_count ?? __('emails.library_request.none') }}
{{ __('emails.library_request.employees_label') }}: {{ $libraryRequest->employee_count ?? __('emails.library_request.none') }}

{{ __('emails.library_request.message_label') }}:
{{ $libraryRequest->message ?: __('emails.library_request.none') }}
