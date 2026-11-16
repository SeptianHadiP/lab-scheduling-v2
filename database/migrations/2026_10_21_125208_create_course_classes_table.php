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
        Schema::create('course_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('dosen_id')->constrained('users')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('academic_year', 9); // contoh: 2026/2027
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['course_id', 'code', 'academic_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_classes');
    }
};
