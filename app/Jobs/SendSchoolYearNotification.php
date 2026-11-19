<?php

namespace App\Jobs;

use App\Mail\SchoolYearNotificationMail;
use App\Models\NotificationDelivery;
use App\Services\Mail\TransactionalMailService;
use App\Services\Notification\NotificationDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class SendSchoolYearNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, int>  $deliveryIds
     */
    public function __construct(
        public readonly array $deliveryIds,
        public readonly SchoolYearNotificationMail $mail,
    ) {
        $this->onQueue('emails');
    }

    public function handle(
        TransactionalMailService $mailer,
        NotificationDeliveryService $deliveries,
    ): void {
        $deliveryRecords = NotificationDelivery::query()
            ->whereKey($this->deliveryIds)
            ->get();

        if ($deliveryRecords->count() !== count($this->deliveryIds)) {
            throw new RuntimeException('A school year notification delivery record is missing.');
        }

        // The job is already queued; send directly instead of queueing again.
        $mailer->sendNowFor($this->mail->recipient, $this->mail);

        foreach ($deliveryRecords as $delivery) {
            $deliveries->markSent($delivery);
        }
    }

    public function failed(Throwable $exception): void
    {
        $deliveries = app(NotificationDeliveryService::class);

        NotificationDelivery::query()
            ->whereKey($this->deliveryIds)
            ->get()
            ->each(fn (NotificationDelivery $delivery) => $deliveries->markFailed($delivery, $exception->getMessage()));
    }
}
