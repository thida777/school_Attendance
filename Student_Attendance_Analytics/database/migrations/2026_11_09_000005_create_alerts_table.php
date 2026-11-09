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
        // Early warnings for a teacher: a student absent 3+ days in a row, a risk level that went up,
        // or a lesson in the timetable that was not recorded on time.
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete(); // who should see it
            $table->enum('type', ['absent_streak', 'risk_up', 'not_recorded']);
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();         // for student alerts
            $table->foreignId('timetable_slot_id')->nullable()->constrained()->cascadeOnDelete();  // for "not recorded"
            $table->date('date');
            $table->string('message');                // e.g. "Absent 3 school days in a row"
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['teacher_id', 'handled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
