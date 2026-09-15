<?php

use App\Support\Text;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('slug')->nullable();
        });

        $used = [];

        DB::table('categories')->select('id', 'library_id', 'name')->orderBy('id')->each(function ($category) use (&$used): void {
            $base = Str::slug(Text::lat($category->name)) ?: 'kategorija';
            $slug = $base;
            $suffix = 2;

            while (isset($used[$category->library_id][$slug])) {
                $slug = $base.'-'.$suffix++;
            }

            $used[$category->library_id][$slug] = true;

            DB::table('categories')->where('id', $category->id)->update(['slug' => $slug]);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->unique(['library_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique(['library_id', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
