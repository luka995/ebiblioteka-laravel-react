@if (($loans ?? collect())->isEmpty())
    <p style="color:#6b7580;font-size:14px;margin:8px 0 0;">{{ __('emails.circulation.labels.none') }}</p>
@elseif (! empty($group_by_user))
    @foreach ($loans->groupBy('user_id') as $userLoans)
        <h3 style="color:#17324d;font-size:15px;margin:16px 0 4px;">
            {{ $userLoans->first()->user?->displayName() ?? '—' }}
        </h3>
        <ul style="margin:0;padding-left:18px;color:#3b4753;font-size:14px;line-height:1.7;">
            @foreach ($userLoans as $item)
                <li>
                    <strong>{{ $item->book?->name ?? '—' }}</strong>
                    — {{ __('emails.circulation.labels.barcode') }}: {{ $item->bookCopy?->barcode ?? '—' }}
                    — {{ __('emails.circulation.labels.due_at') }}: {{ $item->valid_till?->format('d.m.Y.') }}
                    @if ($item->isOverdue())
                        — <strong>{{ __('emails.circulation.labels.overdue') }}: {{ $item->calculateOverdueDays() }} {{ __('emails.circulation.labels.days') }}</strong>
                    @endif
                </li>
            @endforeach
        </ul>
    @endforeach
@else
    <ul style="margin:8px 0 0;padding-left:18px;color:#3b4753;font-size:14px;line-height:1.7;">
        @foreach ($loans as $item)
            <li>
                <strong>{{ $item->book?->name ?? '—' }}</strong>
                — {{ __('emails.circulation.labels.barcode') }}: {{ $item->bookCopy?->barcode ?? '—' }}
                — {{ __('emails.circulation.labels.due_at') }}: {{ $item->valid_till?->format('d.m.Y. H:i') }}
                @if ($item->isOverdue())
                    — <strong>{{ __('emails.circulation.labels.overdue') }}: {{ $item->calculateOverdueDays() }} {{ __('emails.circulation.labels.days') }}</strong>
                @endif
            </li>
        @endforeach
    </ul>
@endif
