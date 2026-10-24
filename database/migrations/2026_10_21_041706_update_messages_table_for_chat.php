<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chat updates:
     * - new message type "system": the safety warning added to the thread when someone
     *   writes words like "cash" or "my number" (see App\Services\ChatFilter).
     * - chat photos are stored with Spatie Media Library ("image" collection), so the
     *   old `attachment` path column is no longer needed.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->enum('type', ['text', 'image', 'system'])->default('text')->change();
            $table->dropColumn('attachment');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->enum('type', ['text', 'image'])->default('text')->change();
            $table->string('attachment')->nullable();
        });
    }
};
