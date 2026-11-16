@if (($reservations ?? collect())->isEmpty())
    <p style="color:#6b7580;font-size:14px;margin:8px 0 0;">{{ __('emails.circulation.labels.none') }}</p>
@else
    <ul style="margin:8px 0 0;padding-left:18px;color:#3b4753;font-size:14px;line-height:1.7;">
        @foreach ($reservations as $reservation)
            <li>
                <strong>{{ $reservation->user?->displayName() ?? '—' }}</strong> —
                {{ $reservation->book?->name ?? '—' }}
                — {{ __('emails.circulation.labels.barcode') }}: {{ $reservation->bookCopy?->barcode ?? '—' }}
                — {{ __('emails.circulation.labels.expires_at') }}: {{ $reservation->valid_till?->format('d.m.Y. H:i') }}
            </li>
        @endforeach
    </ul>
@endif
