<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->onDelete('cascade');
            $table->string('phone')->index();

            // --- ADDED KYC FIELDS ---
            $table->string('idDocument')->nullable();
            $table->string('givenName')->nullable();
            $table->string('familyName')->nullable();

            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->json('network_location')->nullable();
            $table->text('symptoms')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('responder_id')->nullable()->constrained('users');
            $table->integer('response_time_minutes')->nullable();
            $table->string('session_token')->nullable();
            $table->boolean('is_anonymous')->default(true);

            // --- ADDED FOR SOFT DELETES ---
            $table->softDeletes();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_alerts');
    }
};
