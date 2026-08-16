<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->string('verification_status')->default('pending')->after('is_available');
            $table->string('profile_photo_path')->nullable()->after('verification_status');
            $table->unsignedSmallInteger('years_of_experience')->nullable()->after('profile_photo_path');
            $table->json('specialties')->nullable()->after('years_of_experience');
            $table->index('verification_status');
        });

        DB::table('artisan_profiles')->update([
            'verification_status' => DB::raw("CASE WHEN is_verified = true THEN 'verified' ELSE 'pending' END"),
        ]);

        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->dropColumn('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false)->after('is_available');
        });

        DB::table('artisan_profiles')->update([
            'is_verified' => DB::raw("CASE WHEN verification_status = 'verified' THEN true ELSE false END"),
        ]);

        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'profile_photo_path', 'years_of_experience', 'specialties']);
        });
    }
};
