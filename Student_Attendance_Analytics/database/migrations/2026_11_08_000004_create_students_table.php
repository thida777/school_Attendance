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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            // Students do not log in; they are records managed by the admin.
            $table->foreignId('school_class_id')->constrained()->restrictOnDelete();
            $table->string('student_code', 20)->unique(); // e.g. S-10A-001
            $table->string('name_km');
            $table->string('name_latin');
            $table->enum('gender', ['male', 'female']);
            $table->date('date_of_birth')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active'); // inactive = left the school
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
