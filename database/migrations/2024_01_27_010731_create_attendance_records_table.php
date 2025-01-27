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
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->date('attendance_date');
            $table->time('check_in_time');
            $table->string('event_type')->default('sunday_service'); // sunday_service, bible_study, etc.
            $table->text('notes')->nullable();
            $table->float('similarity_score')->nullable(); // Face recognition confidence score
            $table->timestamps();
            
            // Prevent duplicate attendance records for the same member on the same day
            $table->unique(['member_id', 'attendance_date', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
