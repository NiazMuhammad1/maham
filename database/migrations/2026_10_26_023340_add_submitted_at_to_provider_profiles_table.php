<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Become a Service Provider" is saved step by step, so the profile row exists before the user taps
     * "Submit details". `submitted_at` tells a draft (null) apart from an application waiting for admin review.
     *
     * The business logo is stored with Spatie Media Library ("logo" collection), so the old path column is dropped.
     * It was never filled by the app.
     */
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->dropColumn('logo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('website');
            $table->dropColumn('submitted_at');
        });
    }
};
