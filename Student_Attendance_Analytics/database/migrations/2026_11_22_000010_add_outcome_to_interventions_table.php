<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** What happened when the follow-up was closed, e.g. "Parent came to school, student is back". */
    public function up(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->text('outcome')->nullable()->after('rate_after');
        });
    }

    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->dropColumn('outcome');
        });
    }
};
