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
        // Every message sent to a parent, so the school can prove what was sent and when.
        Schema::create('parent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete(); // empty = sent by the system
            $table->enum('type', ['absence_alert', 'monthly_report']);
            $table->enum('channel', ['telegram', 'sms']);
            $table->text('body');                     // Khmer first, then English
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->string('error')->nullable();      // why sending failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_messages');
    }
};
