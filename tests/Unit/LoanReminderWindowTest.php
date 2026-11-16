<?php

use App\Enums\LoanReminderWindow;
use Tests\TestCase;

uses(TestCase::class);

test('loan reminder windows expose translated labels', function () {
    expect(LoanReminderWindow::Soon->getLabel())->toBe(__('emails.circulation.labels.due_soon'))
        ->and(LoanReminderWindow::Today->getLabel())->toBe(__('emails.circulation.labels.due_today'))
        ->and(LoanReminderWindow::Overdue->getLabel())->toBe(__('emails.circulation.labels.overdue'));
});
