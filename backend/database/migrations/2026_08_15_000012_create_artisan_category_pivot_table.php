<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artisan_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artisan_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['artisan_id', 'category_id']);
        });

        $existing = DB::table('artisan_profiles')
            ->whereNotNull('category_id')
            ->select('user_id', 'category_id')
            ->get();

        foreach ($existing as $profile) {
            $hasPivot = DB::table('artisan_category')
                ->where('artisan_id', $profile->user_id)
                ->where('category_id', $profile->category_id)
                ->exists();

            if (! $hasPivot) {
                DB::table('artisan_category')->insert([
                    'artisan_id' => $profile->user_id,
                    'category_id' => $profile->category_id,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('artisan_category');
    }
};
