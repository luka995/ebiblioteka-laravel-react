<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_history', function (Blueprint $table): void {
            $table->dropForeign('classroom_history_previous_class_library_year_fk');
        });

        Schema::table('classroom_history', function (Blueprint $table): void {
            $table->foreignId('previous_school_year_id')->nullable();
        });

        // Postojeci redovi su nastali u okviru iste skolske godine.
        DB::statement(
            'UPDATE classroom_history
             SET previous_school_year_id = school_year_id
             WHERE previous_class_id IS NOT NULL'
        );

        Schema::table('classroom_history', function (Blueprint $table): void {
            $table->index('previous_school_year_id', 'classroom_history_previous_school_year_index');

            $table->foreign('previous_school_year_id', 'classroom_history_previous_school_year_fk')
                ->references('id')
                ->on('school_years')
                ->restrictOnDelete();

            // Dozvoljava da prethodno odeljenje pripada prethodnoj skolskoj
            // godini, ali i dalje zahteva istu biblioteku.
            $table->foreign(
                ['previous_class_id', 'library_id', 'previous_school_year_id'],
                'classroom_history_previous_class_library_previous_year_fk'
            )
                ->references(['id', 'library_id', 'school_year_id'])
                ->on('classes')
                ->restrictOnDelete();
        });

        // PostgreSQL composite FK koristi MATCH SIMPLE: ako je bilo koja
        // kolona NULL, FK se ne proverava. Zato par prethodnih kolona mora
        // biti kompletan ili potpuno prazan.
        DB::statement(
            'ALTER TABLE classroom_history
             ADD CONSTRAINT classroom_history_previous_pair_check
             CHECK ((previous_class_id IS NULL) = (previous_school_year_id IS NULL))'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE classroom_history
             DROP CONSTRAINT classroom_history_previous_pair_check'
        );

        Schema::table('classroom_history', function (Blueprint $table): void {
            $table->dropForeign('classroom_history_previous_class_library_previous_year_fk');
            $table->dropForeign('classroom_history_previous_school_year_fk');
            $table->dropIndex('classroom_history_previous_school_year_index');
        });

        // Stari FK ne dozvoljava prethodno odeljenje iz druge godine.
        DB::statement(
            'UPDATE classroom_history
             SET previous_class_id = NULL
             WHERE previous_school_year_id IS NOT NULL
               AND previous_school_year_id <> school_year_id'
        );

        Schema::table('classroom_history', function (Blueprint $table): void {
            $table->dropColumn('previous_school_year_id');
        });

        Schema::table('classroom_history', function (Blueprint $table): void {
            $table->foreign(
                ['previous_class_id', 'library_id', 'school_year_id'],
                'classroom_history_previous_class_library_year_fk'
            )
                ->references(['id', 'library_id', 'school_year_id'])
                ->on('classes')
                ->restrictOnDelete();
        });
    }
};
