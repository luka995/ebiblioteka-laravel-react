@php
    $authors = $book?->authors?->map(fn ($author) => $author->displayName())->filter()->join('; ');
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e6e8eb;border-radius:8px;margin:8px 0 0;">
    <tr>
        <td style="padding:14px 16px;">
            <p style="color:#17324d;font-size:16px;font-weight:700;margin:0 0 4px;">{{ $book?->name ?? '—' }}</p>
            @if ($authors)
                <p style="color:#6b7580;font-size:13px;margin:0 0 8px;">{{ $authors }}</p>
            @endif
            <p style="color:#3b4753;font-size:13px;line-height:1.6;margin:0;">
                {{ __('emails.circulation.labels.barcode') }}: <strong>{{ $copy?->barcode ?? '—' }}</strong><br>
                {{ __('emails.circulation.labels.inventory_number') }}: {{ $copy?->order_number ?? '—' }}<br>
                {{ __('emails.circulation.labels.library') }}: {{ $library?->name ?? '—' }}
            </p>
        </td>
    </tr>
</table>
