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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10); // schedule | booking
            $table->foreignId('lab_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('booked_for_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('external_name', 150)->nullable();
            $table->string('external_contact', 150)->nullable();
            $table->string('title');
            $table->foreignId('course_class_id')->nullable()->constrained('course_classes')->restrictOnDelete();
            $table->text('tools_software')->nullable();
            $table->boolean('grading_enabled')->default(false);
            $table->boolean('certificate_enabled')->default(false);
            $table->string('certificate_title')->nullable();
            $table->foreignId('grade_owner_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->foreignId('revision_of_id')->nullable()->constrained('reservations')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['lab_id', 'status']);
            $table->index('status');
        });

        Schema::create('reservation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence_no');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('module_path')->nullable();
            $table->timestamps();

            $table->unique(['reservation_id', 'sequence_no']);
            $table->index('date');
        });

        Schema::create('lab_time_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained()->restrictOnDelete();
            $table->foreignId('reservation_session_id')->constrained('reservation_sessions')->cascadeOnDelete();
            $table->dateTime('block_start');

            // Pengaman utama anti double-booking.
            $table->unique(['lab_id', 'block_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_time_blocks');
        Schema::dropIfExists('reservation_sessions');
        Schema::dropIfExists('reservations');
    }
};
