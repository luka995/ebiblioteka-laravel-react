@include('emails.circulation.partials.book', ['book' => $book, 'copy' => $copy, 'library' => $library])

<p style="color:#3b4753;font-size:14px;line-height:1.7;margin:12px 0 0;">
    {{ __('emails.circulation.labels.issued_at') }}: <strong>{{ $loan?->from_date?->format('d.m.Y. H:i') ?? '—' }}</strong><br>
    {{ __('emails.circulation.labels.due_at') }}: <strong>{{ $loan?->valid_till?->format('d.m.Y. H:i') ?? '—' }}</strong>
    @if ($loan?->return_date)
        <br>{{ __('emails.circulation.labels.returned_at') }}: <strong>{{ $loan->return_date->format('d.m.Y. H:i') }}</strong>
    @endif
    @if (($loan?->overdue_days ?? 0) > 0)
        <br>{{ __('emails.circulation.labels.overdue') }}: <strong>{{ $loan->overdue_days }} {{ __('emails.circulation.labels.days') }}</strong>
        ({{ number_format((float) $loan->overdue_amount, 2, ',', '.') }} RSD)
    @endif
</p>
