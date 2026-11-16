<?php

namespace App\Enums;

/**
 * Tipovi email notifikacija za cirkulaciju.
 */
enum CirculationNotificationType: string
{
    case ReservationCreatedUser = 'reservation_created_user';
    case ReservationCreatedStaff = 'reservation_created_staff';
    case ReservationCancelledUser = 'reservation_cancelled_user';
    case ReservationCancelledStaff = 'reservation_cancelled_staff';
    case ReservationExpiringUser = 'reservation_expiring_user';
    case ReservationExpiredUser = 'reservation_expired_user';

    case LoanIssuedUser = 'loan_issued_user';
    case LoanIssuedStaff = 'loan_issued_staff';
    case LoanReturnedUser = 'loan_returned_user';
    case LoanReturnedStaff = 'loan_returned_staff';

    case LoanDueSoonUser = 'loan_due_soon_user';
    case LoanDueTodayUser = 'loan_due_today_user';
    case LoanOverdueUser = 'loan_overdue_user';

    case StaffDailyDigest = 'staff_daily_digest';

    public function subjectKey(): string
    {
        return 'emails.circulation.'.$this->value.'.subject';
    }

    public function view(): string
    {
        return 'emails.circulation.notification';
    }

    public function textView(): string
    {
        return 'emails.circulation.notification-text';
    }
}
