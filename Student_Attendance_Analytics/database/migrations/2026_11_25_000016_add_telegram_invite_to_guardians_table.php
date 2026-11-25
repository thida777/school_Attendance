<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time Telegram invite of a parent: the code in the link https://t.me/<bot>?start=<code>.
 * When the parent opens it and presses Start, the bot saves their chat ID and the code is cleared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->string('telegram_invite', 40)->nullable()->unique()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->dropUnique(['telegram_invite']);
            $table->dropColumn('telegram_invite');
        });
    }
};
