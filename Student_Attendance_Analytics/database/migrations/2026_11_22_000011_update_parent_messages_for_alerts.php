<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parent messages, Part 5.6:
     *  - type is now free text: absence_alert (automatic), manual (written by the admin), monthly_report (later)
     *  - simulated = saved but not really delivered (demo mode, or SMS with no SMS provider)
     */
    public function up(): void
    {
        Schema::table('parent_messages', function (Blueprint $table) {
            $table->string('type', 30)->change();
            $table->boolean('simulated')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('parent_messages', function (Blueprint $table) {
            $table->dropColumn('simulated');
        });
    }
};
