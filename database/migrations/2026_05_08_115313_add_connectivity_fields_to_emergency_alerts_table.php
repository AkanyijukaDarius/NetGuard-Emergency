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
        Schema::table('emergency_alerts', function (Blueprint $table) {
            $table->string('reachability_status')->default('data')->after('status');

            $table->string('connectivity_type')->nullable()->after('reachability_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emergency_alerts', function (Blueprint $table) {
            $table->dropColumn(['reachability_status', 'connectivity_type']);
        });
    }
};
