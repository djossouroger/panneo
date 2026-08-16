<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artisan_service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artisan_id')->constrained('users')->cascadeOnDelete();
            $table->string('city');
            $table->string('district')->nullable();
            $table->timestamps();
        });

        $existing = DB::table('artisan_profiles')
            ->whereNotNull('city')
            ->select('user_id', 'city', 'district')
            ->get();

        foreach ($existing as $profile) {
            $hasArea = DB::table('artisan_service_areas')
                ->where('artisan_id', $profile->user_id)
                ->where('city', $profile->city)
                ->where('district', $profile->district)
                ->exists();

            if (! $hasArea) {
                DB::table('artisan_service_areas')->insert([
                    'artisan_id' => $profile->user_id,
                    'city' => $profile->city,
                    'district' => $profile->district,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('artisan_service_areas');
    }
};
