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
        // Follow-up actions for students at risk, e.g. "talked to the parent, check again in 10 days".
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('action');                 // e.g. "Parent message (Telegram)", "Meeting with parent"
            $table->text('notes')->nullable();
            $table->date('action_date');
            $table->date('follow_up_date')->nullable();
            $table->decimal('rate_before', 4, 1)->nullable(); // attendance rate when the action started
            $table->decimal('rate_after', 4, 1)->nullable();  // attendance rate when it was closed
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'follow_up_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
