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
        // One student in one lesson. All analytics are counted from this table.
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['present', 'absent'])->default('present');
            // Only for absent students; the teacher may leave it empty
            $table->enum('absence_reason', ['not_given', 'sick', 'family_matter', 'school_activity', 'other'])->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['attendance_session_id', 'student_id']); // one status per student per lesson
            $table->index(['student_id', 'status']);                 // fast per-student reports
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
