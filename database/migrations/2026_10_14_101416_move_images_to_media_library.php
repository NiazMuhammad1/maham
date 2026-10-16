<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * All images are now stored with Spatie Media Library (the `media` table), so the old
     * path columns and the `service_request_images` table are no longer needed.
     */
    public function up(): void
    {
        // Keep the avatars users already uploaded: copy each file into the "avatar" media collection.
        User::withTrashed()->whereNotNull('avatar')->each(function (User $user) {
            if (Storage::disk('public')->exists($user->avatar)) {
                $user->addMediaFromDisk($user->avatar, 'public')->toMediaCollection('avatar');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['icon', 'image']);
        });

        Schema::table('onboarding_slides', function (Blueprint $table) {
            $table->dropColumn('image');
        });

        // Request photos now live in the "images" media collection of ServiceRequest.
        Schema::dropIfExists('service_request_images');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('icon')->nullable();
            $table->string('image')->nullable();
        });

        Schema::table('onboarding_slides', function (Blueprint $table) {
            $table->string('image')->nullable();
        });

        Schema::create('service_request_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
