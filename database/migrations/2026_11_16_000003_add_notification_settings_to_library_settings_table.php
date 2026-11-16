<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('reservation_reminder_hours')->default(24)->after('reservation_hours');
            $table->unsignedSmallInteger('loan_reminder_days')->default(3)->after('lend_days');
            $table->unsignedSmallInteger('overdue_reminder_days')->default(3)->after('loan_reminder_days');

            $table->boolean('notify_staff_on_reservation')->default(true);
            $table->boolean('notify_staff_on_issue')->default(false);
            $table->boolean('notify_staff_on_return')->default(false);
            $table->boolean('notify_staff_on_cancellation')->default(true);
            $table->boolean('notify_staff_daily_digest')->default(true);

            $table->boolean('notify_user_reservation_expiry')->default(true);
            $table->boolean('notify_user_loan_due')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('library_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'reservation_reminder_hours',
                'loan_reminder_days',
                'overdue_reminder_days',
                'notify_staff_on_reservation',
                'notify_staff_on_issue',
                'notify_staff_on_return',
                'notify_staff_on_cancellation',
                'notify_staff_daily_digest',
                'notify_user_reservation_expiry',
                'notify_user_loan_due',
            ]);
        });
    }
};
