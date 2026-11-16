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
        Schema::create('lab_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit', 30)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['lab_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_facilities');
    }
};
