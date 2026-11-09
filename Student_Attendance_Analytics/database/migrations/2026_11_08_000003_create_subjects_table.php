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
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();                                         // primary key
            $table->string('code', 10)->unique();                 // e.g. MAT, cannot repeat
            $table->string('name_en');                            // English name
            $table->string('name_km');                            // Khmer name
            $table->unsignedTinyInteger('hours_per_week')->default(1);
            $table->timestamps();                                 // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
