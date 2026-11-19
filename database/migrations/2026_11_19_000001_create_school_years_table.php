<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skolske godine su globalne za platformu. Aktivna godina se ne cuva
        // ovde, vec u settings tabeli (active_school_year_id).
        Schema::create('school_years', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('starts_on');
            $table->index('ends_on');
        });

        DB::statement(
            'ALTER TABLE school_years ADD CONSTRAINT school_years_dates_check CHECK (starts_on < ends_on)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('school_years');
    }
};
