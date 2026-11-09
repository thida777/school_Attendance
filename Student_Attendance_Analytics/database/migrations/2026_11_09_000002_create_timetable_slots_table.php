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
        // The weekly timetable: which teacher teaches which subject to which class, and when.
        // It gives the teacher "today's schedule" and lets the system find lessons not recorded yet.
        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');   // 1 = Monday ... 6 = Saturday
            $table->unsignedTinyInteger('period');    // 1 to 8
            $table->time('start_time');               // e.g. 07:00
            $table->time('end_time');                 // e.g. 08:00
            $table->timestamps();

            $table->unique(['school_class_id', 'weekday', 'period']); // a class has one lesson at a time
            $table->unique(['teacher_id', 'weekday', 'period']);      // a teacher is in one room at a time
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
