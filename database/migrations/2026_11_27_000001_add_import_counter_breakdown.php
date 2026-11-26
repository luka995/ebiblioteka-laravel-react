<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_imports', function (Blueprint $table): void {
            $table->unsignedInteger('pending_rows')->default(0)->after('total_rows');
            $table->unsignedInteger('processing_rows')->default(0)->after('pending_rows');
            $table->unsignedInteger('preparation_failed_rows')->default(0)->after('processing_rows');
            $table->unsignedInteger('import_failed_rows')->default(0)->after('preparation_failed_rows');
        });

        Schema::table('book_import_rows', function (Blueprint $table): void {
            $table->string('failure_stage', 20)->nullable()->after('status');
            $table->index(['book_import_id', 'failure_stage']);
        });

        DB::table('book_import_rows')
            ->where('status', 'failed')
            ->where('attempts', 0)
            ->where('completed_attempts', 0)
            ->update(['failure_stage' => 'preparation']);

        DB::table('book_import_rows')
            ->where('status', 'failed')
            ->where(function ($query): void {
                $query->where('attempts', '>', 0)->orWhere('completed_attempts', '>', 0);
            })
            ->update(['failure_stage' => 'processing']);

        DB::statement(
            <<<'SQL'
            UPDATE book_imports AS bi
            SET pending_rows = counts.pending_rows,
                processing_rows = counts.processing_rows,
                imported_rows = counts.imported_rows,
                preparation_failed_rows = counts.preparation_failed_rows,
                import_failed_rows = counts.import_failed_rows,
                failed_rows = counts.preparation_failed_rows + counts.import_failed_rows,
                processed_rows = counts.imported_rows + counts.import_failed_rows,
                completed_attempts = counts.completed_attempts
            FROM (
                SELECT book_import_id,
                    COUNT(*) FILTER (WHERE status = 'pending') AS pending_rows,
                    COUNT(*) FILTER (WHERE status = 'processing') AS processing_rows,
                    COUNT(*) FILTER (WHERE status = 'imported') AS imported_rows,
                    COUNT(*) FILTER (WHERE status = 'failed' AND failure_stage = 'preparation') AS preparation_failed_rows,
                    COUNT(*) FILTER (WHERE status = 'failed' AND failure_stage = 'processing') AS import_failed_rows,
                    COALESCE(SUM(completed_attempts), 0) AS completed_attempts
                FROM book_import_rows
                GROUP BY book_import_id
            ) AS counts
            WHERE bi.id = counts.book_import_id
            SQL
        );
    }

    public function down(): void
    {
        Schema::table('book_import_rows', function (Blueprint $table): void {
            $table->dropIndex(['book_import_id', 'failure_stage']);
            $table->dropColumn('failure_stage');
        });

        Schema::table('book_imports', function (Blueprint $table): void {
            $table->dropColumn([
                'pending_rows',
                'processing_rows',
                'preparation_failed_rows',
                'import_failed_rows',
            ]);
        });
    }
};
