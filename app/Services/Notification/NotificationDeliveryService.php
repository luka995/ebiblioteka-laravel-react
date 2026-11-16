<?php

namespace App\Services\Notification;

use App\Enums\CirculationNotificationType;
use App\Models\Library;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Evidencija poslatih notifikacija i deduplikacija.
 *
 * Unique `dedupe_key` garantuje da se isti email ne queue-uje dva puta,
 * cak i ako se cron komanda pokrene vise puta ili vise workera paralelno.
 */
class NotificationDeliveryService
{
    public function claim(
        CirculationNotificationType $type,
        string $dedupeKey,
        ?User $recipient = null,
        ?Library $library = null,
        ?Model $related = null,
        array $meta = [],
    ): ?NotificationDelivery {
        try {
            return NotificationDelivery::create([
                'type' => $type->value,
                'channel' => 'email',
                'user_id' => $recipient?->id,
                'library_id' => $library?->id,
                'related_type' => $related !== null ? $related::class : null,
                'related_id' => $related?->getKey(),
                'dedupe_key' => $dedupeKey,
                'status' => NotificationDelivery::STATUS_QUEUED,
                'meta' => $meta === [] ? null : $meta,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    public function markSent(NotificationDelivery $delivery): void
    {
        $delivery->update([
            'status' => NotificationDelivery::STATUS_SENT,
            'sent_at' => now(),
            'error' => null,
        ]);
    }

    public function markFailed(NotificationDelivery $delivery, string $error): void
    {
        $delivery->update([
            'status' => NotificationDelivery::STATUS_FAILED,
            'failed_at' => now(),
            'error' => mb_substr($error, 0, 2000),
        ]);
    }
}
