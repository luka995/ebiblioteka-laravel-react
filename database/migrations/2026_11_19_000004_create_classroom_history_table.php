<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only istorija promena odeljenja. `class_id` je novo
        // odeljenje (null znaci da je ucenik uklonjen), a `previous_class_id`
        // je staro odeljenje (null znaci prvo dodeljivanje).
        Schema::create('classroom_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('library_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignId('class_id')->nullable();
            $table->foreignId('previous_class_id')->nullable();
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['user_id', 'changed_at'], 'classroom_history_user_changed_index');
            $table->index(['library_id', 'school_year_id'], 'classroom_history_library_year_index');
            $table->index('class_id');
            $table->index('previous_class_id');

            $table->foreign(['class_id', 'library_id', 'school_year_id'], 'classroom_history_class_library_year_fk')
                ->references(['id', 'library_id', 'school_year_id'])
                ->on('classes')
                ->restrictOnDelete();

            $table->foreign(['previous_class_id', 'library_id', 'school_year_id'], 'classroom_history_previous_class_library_year_fk')
                ->references(['id', 'library_id', 'school_year_id'])
                ->on('classes')
                ->restrictOnDelete();
        });

        DB::statement(
            'ALTER TABLE classroom_history ADD CONSTRAINT classroom_history_change_check
             CHECK (class_id IS DISTINCT FROM previous_class_id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_history');
    }
};
