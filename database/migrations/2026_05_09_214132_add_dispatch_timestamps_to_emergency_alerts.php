<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('emergency_alerts', function (Blueprint $table) {
        if (!Schema::hasColumn('emergency_alerts', 'dispatched_at')) {
            $table->timestamp('dispatched_at')->nullable();
        }
        if (!Schema::hasColumn('emergency_alerts', 'resolved_at')) {
            $table->timestamp('resolved_at')->nullable();
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emergency_alerts', function (Blueprint $table) {
            $table->dropColumn('dispatched_at');
            $table->dropColumn('resolved_at');

        });
    }
};
