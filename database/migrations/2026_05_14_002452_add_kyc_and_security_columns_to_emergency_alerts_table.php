<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('emergency_alerts', function (Blueprint $table) {

            $table->boolean('kyc_verified')->default(false)->after('sim_swap_flagged');
            $table->json('kyc_result')->nullable()->after('kyc_verified');

            if (!Schema::hasColumn('emergency_alerts', 'sim_swap_flagged')) {
                $table->boolean('sim_swap_flagged')->default(false)->after('reachability_status');
            }

            $table->index('kyc_verified');
            $table->index('sim_swap_flagged');
        });
    }

    public function down()
    {
        Schema::table('emergency_alerts', function (Blueprint $table) {
            $table->dropColumn([
                'kyc_verified',
                'kyc_result',
                'sim_swap_flagged',
            ]);


        });
    }
};
