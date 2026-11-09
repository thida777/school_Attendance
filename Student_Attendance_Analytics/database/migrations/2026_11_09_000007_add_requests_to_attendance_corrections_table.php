<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrections become the audit log and the request inbox:
 *  applied  = the change was made directly (teacher within 24 hours, or the admin)
 *  pending  = a teacher asked for a change after 24 hours; the admin decides
 *  approved = the admin accepted the request, the record was changed
 *  rejected = the admin refused the request, the record stayed as it was
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->enum('status', ['applied', 'pending', 'approved', 'rejected'])->default('applied')->after('new_status');
            $table->string('reason')->nullable()->change(); // optional for a teacher's fix within 24 hours
            $table->foreignId('decided_by')->nullable()->after('reason')->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->after('decided_by');
            $table->string('decision_note')->nullable()->after('decided_at');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['status', 'decided_at', 'decision_note']);
        });
    }
};
