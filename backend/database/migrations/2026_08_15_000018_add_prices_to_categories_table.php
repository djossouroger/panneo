<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('indicative_min_price')->nullable()->after('is_active');
            $table->unsignedBigInteger('indicative_max_price')->nullable()->after('indicative_min_price');
            $table->string('callout_fee_label')->nullable()->after('indicative_max_price');
            $table->unsignedBigInteger('callout_fee')->nullable()->after('callout_fee_label');
            $table->string('currency')->default('XOF')->after('callout_fee');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['indicative_min_price', 'indicative_max_price', 'callout_fee_label', 'callout_fee', 'currency']);
        });
    }
};
