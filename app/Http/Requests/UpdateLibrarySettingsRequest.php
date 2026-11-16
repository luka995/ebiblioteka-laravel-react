<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLibrarySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'reservation_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'reservation_reminder_hours' => ['sometimes', 'required', 'integer', 'min:1', 'max:720'],
            'lend_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'loan_reminder_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'overdue_reminder_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'overdue_price_per_day' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100000'],
            'notify_staff_on_reservation' => ['sometimes', 'boolean'],
            'notify_staff_on_issue' => ['sometimes', 'boolean'],
            'notify_staff_on_return' => ['sometimes', 'boolean'],
            'notify_staff_on_cancellation' => ['sometimes', 'boolean'],
            'notify_staff_daily_digest' => ['sometimes', 'boolean'],
            'notify_user_reservation_expiry' => ['sometimes', 'boolean'],
            'notify_user_loan_due' => ['sometimes', 'boolean'],
        ];
    }
}
