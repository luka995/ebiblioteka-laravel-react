<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_authors', function (Blueprint $table): void {
            $table->dropForeign(['author_id']);
            $table->foreign('author_id')->references('id')->on('authors')->restrictOnDelete();
        });

        Schema::table('books', function (Blueprint $table): void {
            $table->dropForeign(['category_primary_id']);
            $table->foreign('category_primary_id')->references('id')->on('categories')->restrictOnDelete();

            $table->dropForeign(['category_secondary_id']);
            $table->foreign('category_secondary_id')->references('id')->on('categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropForeign(['category_secondary_id']);
            $table->foreign('category_secondary_id')->references('id')->on('categories')->nullOnDelete();

            $table->dropForeign(['category_primary_id']);
            $table->foreign('category_primary_id')->references('id')->on('categories')->nullOnDelete();
        });

        Schema::table('book_authors', function (Blueprint $table): void {
            $table->dropForeign(['author_id']);
            $table->foreign('author_id')->references('id')->on('authors')->cascadeOnDelete();
        });
    }
};
