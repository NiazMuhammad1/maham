<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('swift_code', 11)->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Payout Settings
        Schema::create('provider_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_holder_name');
            $table->string('iban', 34); // SA IBAN is 24 chars, 34 is the ISO max
            $table->string('swift_code', 11)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_payout_accounts');
        Schema::dropIfExists('banks');
    }
};
