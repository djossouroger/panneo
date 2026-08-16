<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_requests', function (Blueprint $table) {
            $table->foreignId('accepted_artisan_id')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('accepted_at')->nullable()->after('accepted_artisan_id');
            $table->index('category_id', 'repair_requests_category_idx');
            $table->index('accepted_artisan_id', 'repair_requests_accepted_artisan_idx');
        });

        Schema::create('repair_request_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->constrained('repair_requests')->cascadeOnDelete();
            $table->foreignId('artisan_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['repair_request_id', 'artisan_id'], 'repair_request_offers_request_artisan_unique');
            $table->index('repair_request_id', 'repair_request_offers_request_idx');
            $table->index('artisan_id', 'repair_request_offers_artisan_idx');
            $table->index('status', 'repair_request_offers_status_idx');
            $table->index(['repair_request_id', 'status'], 'repair_request_offers_request_status_idx');
            $table->index(['artisan_id', 'status'], 'repair_request_offers_artisan_status_idx');
        });

        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->index(['category_id', 'city', 'is_available'], 'artisan_profiles_matching_idx');
            $table->index('city', 'artisan_profiles_city_idx');
            $table->index('is_available', 'artisan_profiles_available_idx');
        });
    }

    public function down(): void
    {
        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->dropIndex('artisan_profiles_matching_idx');
            $table->dropIndex('artisan_profiles_city_idx');
            $table->dropIndex('artisan_profiles_available_idx');
        });

        Schema::dropIfExists('repair_request_offers');

        Schema::table('repair_requests', function (Blueprint $table) {
            $table->dropForeign(['accepted_artisan_id']);
            $table->dropIndex('repair_requests_category_idx');
            $table->dropIndex('repair_requests_accepted_artisan_idx');
            $table->dropColumn(['accepted_artisan_id', 'accepted_at']);
        });
    }
};
