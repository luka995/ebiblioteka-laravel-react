@php
    $book = $data['book'] ?? null;
    $copy = $data['copy'] ?? null;
    $library = $data['library'] ?? null;
    $user = $data['user'] ?? null;
    $loan = $data['loan'] ?? null;
    $reservation = $data['reservation'] ?? null;
    $dashboard = rtrim(\App\Support\FrontendUrl::url(), '/');
@endphp
{{ __($type->subjectKey(), $data) }}
{{ __('emails.brand.name') }} — {{ __('emails.brand.tagline') }}
============================================================

@switch($type->value)
    @case('reservation_created_user')
{{ __('emails.circulation.reservation_created_user.intro', ['name' => $user?->displayName()]) }}

{{ $book?->name ?? '—' }}
{{ __('emails.circulation.labels.barcode') }}: {{ $copy?->barcode ?? '—' }}
{{ __('emails.circulation.labels.library') }}: {{ $library?->name ?? '—' }}
{{ __('emails.circulation.labels.pickup_until') }}: {{ $reservation?->valid_till?->format('d.m.Y. H:i') }}

{{ $dashboard }}/reservations
        @break

    @case('reservation_created_staff')
{{ __('emails.circulation.reservation_created_staff.intro', ['name' => $user?->displayName()]) }}

{{ $book?->name ?? '—' }}
{{ __('emails.circulation.labels.barcode') }}: {{ $copy?->barcode ?? '—' }}
{{ __('emails.circulation.labels.pickup_until') }}: {{ $reservation?->valid_till?->format('d.m.Y. H:i') }}

{{ $dashboard }}/reservations/library
        @break

    @case('reservation_cancelled_user')
{{ __('emails.circulation.reservation_cancelled_user.intro') }}

{{ $book?->name ?? '—' }}
{{ __('emails.circulation.labels.barcode') }}: {{ $copy?->barcode ?? '—' }}
        @break

    @case('reservation_cancelled_staff')
{{ __('emails.circulation.reservation_cancelled_staff.intro', ['name' => $user?->displayName()]) }}

{{ $book?->name ?? '—' }}
{{ __('emails.circulation.labels.barcode') }}: {{ $copy?->barcode ?? '—' }}
        @break

    @case('reservation_expiring_user')
{{ __('emails.circulation.reservation_expiring_user.intro', ['hours' => $library?->settings?->reservationReminderHours() ?? 24]) }}

{{ $book?->name ?? '—' }}
{{ __('emails.circulation.labels.barcode') }}: {{ $copy?->barcode ?? '—' }}
{{ __('emails.circulation.labels.pickup_until') }}: {{ $reservation?->valid_till?->format('d.m.Y. H:i') }}
        @break

    @case('reservation_expired_user')
{{ __('emails.circulation.reservation_expired_user.intro') }}

{{ $book?->name ?? '—' }}
{{ __('emails.circulation.labels.barcode') }}: {{ $copy?->barcode ?? '—' }}
        @break

    @case('loan_issued_user')
{{ __('emails.circulation.loan_issued_user.intro', ['name' => $user?->displayName()]) }}

{{ $book?->name ?? '—' }}
{{ __('emails.circulation.labels.barcode') }}: {{ $copy?->barcode ?? '—' }}
{{ __('emails.circulation.labels.issued_at') }}: {{ $loan?->from_date?->format('d.m.Y. H:i') ?? '—' }}
{{ __('emails.circulation.labels.due_at') }}: {{ $loan?->valid_till?->format('d.m.Y. H:i') ?? '—' }}

{{ $dashboard }}/borrows
        @break

    @case('loan_issued_staff')
{{ __('emails.circulation.loan_issued_staff.intro', ['name' => $user?->displayName()]) }}

{{ $book?->name ?? '—' }}
{{ __('emails.circulation.labels.barcode') }}: {{ $copy?->barcode ?? '—' }}
{{ __('emails.circulation.labels.due_at') }}: {{ $loan?->valid_till?->format('d.m.Y. H:i') ?? '—' }}
        @break

    @case('loan_returned_user')
{{ __('emails.circulation.loan_returned_user.intro') }}

{{ $book?->name ?? '—' }}
{{ __('emails.circulation.labels.returned_at') }}: {{ $loan?->return_date?->format('d.m.Y. H:i') ?? '—' }}
@if (($loan?->overdue_days ?? 0) > 0)
{{ __('emails.circulation.labels.overdue') }}: {{ $loan->overdue_days }} {{ __('emails.circulation.labels.days') }} / {{ number_format((float) $loan->overdue_amount, 2, ',', '.') }} RSD
@endif
        @break

    @case('loan_returned_staff')
{{ __('emails.circulation.loan_returned_staff.intro', ['name' => $user?->displayName()]) }}

{{ $book?->name ?? '—' }}
{{ __('emails.circulation.labels.returned_at') }}: {{ $loan?->return_date?->format('d.m.Y. H:i') ?? '—' }}
        @break

    @case('loan_due_soon_user')
    @case('loan_due_today_user')
    @case('loan_overdue_user')
{{ __('emails.circulation.'.$type->value.'.intro') }}

@foreach (($data['loans'] ?? collect()) as $item)
- {{ $item->book?->name ?? '—' }} | {{ __('emails.circulation.labels.barcode') }}: {{ $item->bookCopy?->barcode ?? '—' }} | {{ __('emails.circulation.labels.due_at') }}: {{ $item->valid_till?->format('d.m.Y.') }}
@endforeach

{{ $dashboard }}/borrows
        @break

    @case('staff_daily_digest')
{{ __('emails.circulation.staff_daily_digest.intro', ['library' => $library?->name]) }}

{{ __('emails.circulation.staff_daily_digest.due_title') }}
@foreach (($data['loans_due'] ?? collect())->groupBy('user_id') as $userLoans)
{{ $userLoans->first()->user?->displayName() ?? '—' }}
@foreach ($userLoans as $item)
  - {{ $item->book?->name ?? '—' }} | {{ __('emails.circulation.labels.barcode') }}: {{ $item->bookCopy?->barcode ?? '—' }} | {{ $item->valid_till?->format('d.m.Y.') }}
@endforeach
@endforeach

{{ __('emails.circulation.staff_daily_digest.overdue_title') }}
@forelse (($data['loans_overdue'] ?? collect())->groupBy('user_id') as $userLoans)
{{ $userLoans->first()->user?->displayName() ?? '—' }}
@foreach ($userLoans as $item)
  - {{ $item->book?->name ?? '—' }} | {{ $item->valid_till?->format('d.m.Y.') }} | {{ $item->calculateOverdueDays() }} {{ __('emails.circulation.labels.days') }}
@endforeach
@empty
-
@endforelse

{{ __('emails.circulation.staff_daily_digest.reservations_title') }}
@forelse (($data['reservations_expiring'] ?? collect()) as $item)
- {{ $item->user?->displayName() ?? '—' }} | {{ $item->book?->name ?? '—' }} | {{ $item->valid_till?->format('d.m.Y. H:i') }}
@empty
-
@endforelse
        @break
@endswitch
