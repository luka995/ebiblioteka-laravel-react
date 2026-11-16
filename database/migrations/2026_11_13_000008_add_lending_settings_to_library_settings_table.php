<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_settings', function (Blueprint $table): void {
            // Rok pozajmice i cena prekoracenja po danu (legacy
            // library_settings.lendTime / overDuePrice).
            $table->unsignedSmallInteger('lend_days')->default(30)->after('reservation_hours');
            $table->decimal('overdue_price_per_day', 10, 2)->default(0)->after('lend_days');
        });
    }

    public function down(): void
    {
        Schema::table('library_settings', function (Blueprint $table): void {
            $table->dropColumn(['lend_days', 'overdue_price_per_day']);
        });
    }
};
