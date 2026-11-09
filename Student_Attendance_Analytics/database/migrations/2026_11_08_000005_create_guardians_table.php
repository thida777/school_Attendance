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
        // Parents are contacts only: they receive Telegram or SMS messages but do not log in.
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_km')->nullable();
            $table->string('phone', 20)->unique();     // used for SMS when there is no Telegram
            $table->string('telegram_chat_id', 32)->nullable(); // set when the parent joins the school's Telegram bot
            $table->timestamps();
        });

        // Pivot table: one parent can have many children, one child can have many parents.
        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('relation', ['father', 'mother', 'guardian'])->default('guardian');
            $table->timestamps();

            $table->unique(['guardian_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
    }
};
