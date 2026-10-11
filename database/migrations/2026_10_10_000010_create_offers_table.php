<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Provider bids on a service request (Send Offer)
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('SAR');
            $table->text('description')->nullable();

            // "Agree with Customer's Time" or "Propose a Different Date & Time"
            $table->enum('schedule_type', ['customer_time', 'proposed'])->default('customer_time');
            $table->date('proposed_date')->nullable();
            $table->time('proposed_time')->nullable();

            $table->enum('status', ['pending', 'accepted', 'rejected', 'withdrawn'])->default('pending')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['service_request_id', 'provider_id']);
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreign('accepted_offer_id')->references('id')->on('offers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign(['accepted_offer_id']);
        });

        Schema::dropIfExists('offers');
    }
};
