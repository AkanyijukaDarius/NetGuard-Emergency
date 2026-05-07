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
            $table->string('phone')->unique()->after('id');
            $table->string('given_name')->nullable();
            $table->string('family_name')->nullable();
            $table->string('id_document')->nullable();
            $table->boolean('is_kyc_verified')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'given_name', 'family_name', 'id_document', 'is_kyc_verified']);
        });
    }
};
