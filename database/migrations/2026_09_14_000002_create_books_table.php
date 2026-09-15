<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->foreignId('category_primary_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('category_secondary_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('cover_url')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['library_id', 'slug']);
            $table->index(['library_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
