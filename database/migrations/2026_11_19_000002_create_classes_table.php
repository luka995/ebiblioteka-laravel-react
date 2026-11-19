<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Odeljenje pripada tacno jednoj biblioteci i jednoj skolskoj godini.
        // Naziv je jedinstven u okviru biblioteke i godine.
        Schema::create('classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->unsignedTinyInteger('grade');
            $table->string('name', 50);
            $table->string('teacher_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['library_id', 'school_year_id', 'name'], 'classes_library_year_name_unique');

            // Composite FK target za student_class i classroom_history.
            // Ukljucuje id jer FK mora da referencira unique kombinaciju.
            $table->unique(['id', 'library_id', 'school_year_id'], 'classes_fk_target_unique');

            $table->index(['library_id', 'school_year_id', 'is_active'], 'classes_library_year_active_index');
        });

        // Razred je ogranicen na 1-8.
        DB::statement(
            'ALTER TABLE classes ADD CONSTRAINT classes_grade_check CHECK (grade BETWEEN 1 AND 8)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
