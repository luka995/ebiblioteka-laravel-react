<?php

namespace App\Console\Commands;

use App\Enums\LoanReminderWindow;
use App\Models\Borrow;
use App\Services\Notification\CirculationNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class NotifyUserLoansDue extends Command
{
    protected $signature = 'notifications:user-loans-due';

    protected $description = 'Posalji korisnicima podsetnike za rok vracanja i prekoracenja';

    public function handle(CirculationNotificationService $notifications): int
    {
        $today = now()->startOfDay();
        $processed = 0;

        Borrow::query()
            ->with(['user', 'book.authors', 'bookCopy', 'library.settings'])
            ->where('returned', false)
            ->where('valid_till', '<=', now()->addDays(30))
            ->orderBy('user_id')
            ->orderBy('valid_till')
            ->chunk(200, function (Collection $loans) use ($notifications, $today, &$processed): void {
                foreach ($loans->groupBy('user_id') as $userLoans) {
                    $user = $userLoans->first()->user;

                    if ($user === null) {
                        continue;
                    }

                    $soon = collect();
                    $dueToday = collect();
                    $overdue = collect();

                    foreach ($userLoans as $loan) {
                        $settings = $loan->library?->settings;
                        $due = $loan->valid_till->copy()->startOfDay();

                        if ($due->lt($today)) {
                            $overdue->push($loan);
                        } elseif ($due->equalTo($today)) {
                            $dueToday->push($loan);
                        } elseif ($today->diffInDays($due) <= ($settings?->loanReminderDays() ?? 3)) {
                            $soon->push($loan);
                        }
                    }

                    foreach ([
                        [LoanReminderWindow::Soon, $soon],
                        [LoanReminderWindow::Today, $dueToday],
                        [LoanReminderWindow::Overdue, $overdue],
                    ] as [$window, $windowLoans]) {
                        if ($windowLoans->isEmpty()) {
                            continue;
                        }

                        $notifications->sendUserLoanReminder($user, $windowLoans, $window);
                        $processed++;
                    }
                }
            });

        $this->info("Obradjeno korisnickih podsetnika: {$processed}");

        return self::SUCCESS;
    }
}
