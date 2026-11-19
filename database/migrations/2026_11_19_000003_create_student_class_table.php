<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trenutno odeljenje ucenika po biblioteci i skolskoj godini.
        // Istorija promena se cuva u classroom_history; ovde nema `active`
        // kolone ni parcijalnog indeksa.
        Schema::create('student_class', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('library_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignId('class_id');
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['user_id', 'library_id', 'school_year_id'],
                'student_class_user_library_year_unique'
            );

            $table->index('class_id');
            $table->index(['library_id', 'school_year_id'], 'student_class_library_year_index');

            // Sprecava da current red pokazuje na odeljenje iz druge
            // biblioteke ili druge skolske godine.
            $table->foreign(['class_id', 'library_id', 'school_year_id'], 'student_class_class_library_year_fk')
                ->references(['id', 'library_id', 'school_year_id'])
                ->on('classes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_class');
    }
};
