<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabby and Tamara (buy now, pay later) join card / Apple Pay on the Payments sheet.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('method', ['card', 'apple_pay', 'mada', 'stc_pay', 'tabby', 'tamara'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('method', ['card', 'apple_pay', 'mada', 'stc_pay'])->change();
        });
    }
};
