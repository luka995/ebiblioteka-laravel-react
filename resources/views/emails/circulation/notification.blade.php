@extends('emails.layout')

@section('content')
    @php
        $book = $data['book'] ?? null;
        $copy = $data['copy'] ?? null;
        $library = $data['library'] ?? null;
        $user = $data['user'] ?? null;
        $loan = $data['loan'] ?? null;
        $reservation = $data['reservation'] ?? null;
        $dashboard = rtrim(\App\Support\FrontendUrl::url(), '/');
        $heading = __($type->subjectKey(), $data);
    @endphp

    <h1 style="color:#17324d;font-size:22px;line-height:1.3;margin:0 0 16px;">{{ $heading }}</h1>

    @switch($type->value)
        @case('reservation_created_user')
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
                {{ __('emails.circulation.reservation_created_user.intro', ['name' => $user?->displayName()]) }}
            </p>
            @include('emails.circulation.partials.book', ['book' => $book, 'copy' => $copy, 'library' => $library])
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:16px 0 0;">
                {{ __('emails.circulation.labels.pickup_until') }}:
                <strong>{{ $reservation?->valid_till?->format('d.m.Y. H:i') }}</strong>
            </p>
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:16px 0 0;">
                <a href="{{ $dashboard }}/reservations" style="color:#17324d;font-weight:700;">{{ __('emails.circulation.labels.my_reservations') }}</a>
            </p>
            @break

        @case('reservation_created_staff')
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
                {{ __('emails.circulation.reservation_created_staff.intro', ['name' => $user?->displayName()]) }}
            </p>
            @include('emails.circulation.partials.book', ['book' => $book, 'copy' => $copy, 'library' => $library])
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:16px 0 0;">
                {{ __('emails.circulation.labels.pickup_until') }}:
                <strong>{{ $reservation?->valid_till?->format('d.m.Y. H:i') }}</strong>
            </p>
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:16px 0 0;">
                <a href="{{ $dashboard }}/reservations/library" style="color:#17324d;font-weight:700;">{{ __('emails.circulation.labels.library_reservations') }}</a>
            </p>
            @break

        @case('reservation_cancelled_user')
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
                {{ __('emails.circulation.reservation_cancelled_user.intro') }}
            </p>
            @include('emails.circulation.partials.book', ['book' => $book, 'copy' => $copy, 'library' => $library])
            @break

        @case('reservation_cancelled_staff')
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
                {{ __('emails.circulation.reservation_cancelled_staff.intro', ['name' => $user?->displayName()]) }}
            </p>
            @include('emails.circulation.partials.book', ['book' => $book, 'copy' => $copy, 'library' => $library])
            @break

        @case('reservation_expiring_user')
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
                {{ __('emails.circulation.reservation_expiring_user.intro', [
                    'hours' => $library?->settings?->reservationReminderHours() ?? 24,
                ]) }}
            </p>
            @include('emails.circulation.partials.book', ['book' => $book, 'copy' => $copy, 'library' => $library])
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:16px 0 0;">
                {{ __('emails.circulation.labels.pickup_until') }}:
                <strong>{{ $reservation?->valid_till?->format('d.m.Y. H:i') }}</strong>
            </p>
            @break

        @case('reservation_expired_user')
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
                {{ __('emails.circulation.reservation_expired_user.intro') }}
            </p>
            @include('emails.circulation.partials.book', ['book' => $book, 'copy' => $copy, 'library' => $library])
            @break

        @case('loan_issued_user')
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
                {{ __('emails.circulation.loan_issued_user.intro', ['name' => $user?->displayName()]) }}
            </p>
            @include('emails.circulation.partials.loan', ['loan' => $loan, 'book' => $book, 'copy' => $copy, 'library' => $library])
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:16px 0 0;">
                <a href="{{ $dashboard }}/borrows" style="color:#17324d;font-weight:700;">{{ __('emails.circulation.labels.my_loans') }}</a>
            </p>
            @break

        @case('loan_issued_staff')
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
                {{ __('emails.circulation.loan_issued_staff.intro', ['name' => $user?->displayName()]) }}
            </p>
            @include('emails.circulation.partials.loan', ['loan' => $loan, 'book' => $book, 'copy' => $copy, 'library' => $library])
            @break

        @case('loan_returned_user')
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
                {{ __('emails.circulation.loan_returned_user.intro') }}
            </p>
            @include('emails.circulation.partials.loan', ['loan' => $loan, 'book' => $book, 'copy' => $copy, 'library' => $library])
            @break

        @case('loan_returned_staff')
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
                {{ __('emails.circulation.loan_returned_staff.intro', ['name' => $user?->displayName()]) }}
            </p>
            @include('emails.circulation.partials.loan', ['loan' => $loan, 'book' => $book, 'copy' => $copy, 'library' => $library])
            @break

        @case('loan_due_soon_user')
        @case('loan_due_today_user')
        @case('loan_overdue_user')
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 16px;">
                {{ __('emails.circulation.'.$type->value.'.intro') }}
            </p>
            @include('emails.circulation.partials.loan-list', ['loans' => $data['loans'] ?? collect()])
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:16px 0 0;">
                <a href="{{ $dashboard }}/borrows" style="color:#17324d;font-weight:700;">{{ __('emails.circulation.labels.my_loans') }}</a>
            </p>
            @break

        @case('staff_daily_digest')
            <p style="color:#3b4753;font-size:15px;line-height:1.6;margin:0 0 20px;">
                {{ __('emails.circulation.staff_daily_digest.intro', ['library' => $library?->name]) }}
            </p>

            <h2 style="color:#17324d;font-size:17px;margin:24px 0 8px;">{{ __('emails.circulation.staff_daily_digest.due_title') }}</h2>
            @include('emails.circulation.partials.loan-list', ['loans' => $data['loans_due'] ?? collect(), 'group_by_user' => true])

            <h2 style="color:#17324d;font-size:17px;margin:24px 0 8px;">{{ __('emails.circulation.staff_daily_digest.overdue_title') }}</h2>
            @include('emails.circulation.partials.loan-list', ['loans' => $data['loans_overdue'] ?? collect(), 'group_by_user' => true])

            <h2 style="color:#17324d;font-size:17px;margin:24px 0 8px;">{{ __('emails.circulation.staff_daily_digest.reservations_title') }}</h2>
            @include('emails.circulation.partials.reservation-list', ['reservations' => $data['reservations_expiring'] ?? collect()])
            @break
    @endswitch
@endsection
