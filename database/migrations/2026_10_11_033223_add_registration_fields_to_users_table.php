<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The account is created right after the phone is verified, before the name step.
            $table->string('name')->nullable()->change();
            $table->timestamp('registration_completed_at')->nullable()->after('is_guest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('registration_completed_at');
            $table->string('name')->nullable(false)->change();
        });
    }
};
