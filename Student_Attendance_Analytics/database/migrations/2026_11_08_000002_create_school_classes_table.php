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
        // Named school_classes because "class" is a reserved word in PHP.
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 10);               // e.g. 10A
            $table->unsignedTinyInteger('grade');     // 10, 11 or 12
            $table->string('room', 20)->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'name']); // no two "10A" in the same year
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
