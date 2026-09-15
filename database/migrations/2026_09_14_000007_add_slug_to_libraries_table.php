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
        Schema::table('libraries', function (Blueprint $table): void {
            $table->string('slug')->nullable();
        });

        $used = [];

        DB::table('libraries')->select('id', 'name')->orderBy('id')->each(function ($library) use (&$used): void {
            $base = Str::slug(Text::lat($library->name)) ?: 'biblioteka';
            $slug = $base;
            $suffix = 2;

            while (isset($used[$slug])) {
                $slug = $base.'-'.$suffix++;
            }

            $used[$slug] = true;

            DB::table('libraries')->where('id', $library->id)->update(['slug' => $slug]);
        });

        Schema::table('libraries', function (Blueprint $table): void {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('libraries', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
