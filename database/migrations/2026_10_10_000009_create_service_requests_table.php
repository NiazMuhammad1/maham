<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer job posting (My Requests)
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // REQ-2041
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();

            $table->text('description');
            $table->decimal('budget', 10, 2)->nullable();
            $table->string('currency', 3)->default('SAR');

            $table->date('preferred_date');
            $table->time('preferred_time')->nullable();
            $table->enum('flexibility', ['fixed', 'flexible', 'very_flexible'])->default('flexible');

            // Work address snapshot (kept even if the user later edits their saved address)
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('street_address');
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // open = awaiting offers / offers received; closed tab = accepted | cancelled | expired
            $table->enum('status', ['open', 'accepted', 'cancelled', 'expired'])->default('open')->index();
            $table->unsignedInteger('offers_count')->default(0);
            $table->unsignedBigInteger('accepted_offer_id')->nullable(); // FK added in offers migration
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_id', 'status']);
        });

        Schema::create('service_request_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_images');
        Schema::dropIfExists('service_requests');
    }
};
