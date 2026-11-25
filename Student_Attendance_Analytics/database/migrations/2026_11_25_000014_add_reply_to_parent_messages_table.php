<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The parent's answer to an absence alert, given with a button under the Telegram message:
 * sick, family_matter or call_me. One answer per message (the first tap counts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_messages', function (Blueprint $table) {
            $table->string('reply', 20)->nullable()->after('error');
            $table->timestamp('replied_at')->nullable()->after('reply');
        });
    }

    public function down(): void
    {
        Schema::table('parent_messages', function (Blueprint $table) {
            $table->dropColumn(['reply', 'replied_at']);
        });
    }
};
