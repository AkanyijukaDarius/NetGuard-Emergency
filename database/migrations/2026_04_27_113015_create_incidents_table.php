<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code')->unique();
            $table->string('type')->nullable();                    // boda_accident, maternal, trauma, etc.
            $table->string('severity')->default('medium');         // low, medium, high, critical
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->json('ai_triage')->nullable();                 // Store AI result
            $table->json('kyc_result')->nullable();                // From KYC Match
            $table->string('qod_session_id')->nullable();          // From QoD
            $table->string('status')->default('open');             // open, resolved, closed
            $table->foreignId('primary_responder_id')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->integer('total_response_time_minutes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
