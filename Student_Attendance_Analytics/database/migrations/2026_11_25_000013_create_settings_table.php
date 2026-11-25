<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * School-wide settings the admin can change on the "School settings" page,
     * e.g. the school name shown on the website, the app, reports and Telegram messages.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Start with the name used so far, so nothing changes until the admin edits it
        DB::table('settings')->insert([
            ['key' => 'school_name_en', 'value' => 'Demo High School', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'school_name_km', 'value' => 'វិទ្យាល័យសាកល្បង', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
