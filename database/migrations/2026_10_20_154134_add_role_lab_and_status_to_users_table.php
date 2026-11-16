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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('dosen')->after('password');
            $table->foreignId('lab_id')->nullable()->after('role')
                ->constrained('labs')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('lab_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lab_id');
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
