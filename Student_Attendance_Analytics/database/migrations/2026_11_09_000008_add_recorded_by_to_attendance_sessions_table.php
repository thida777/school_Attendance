<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who pressed Submit. Usually the subject teacher (teacher_id), but the admin may record a lesson
 * for a teacher. The audit log shows this person on its "Submitted" lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->after('teacher_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
        });
    }
};
