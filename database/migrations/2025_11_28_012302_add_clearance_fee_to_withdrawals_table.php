<?php
// database/migrations/2024_01_01_add_clearance_fee_to_withdrawals_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddClearanceFeeToWithdrawalsTable extends Migration
{
    public function up()
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->decimal('clearance_fee', 10, 2)->default(0)->after('fee');
        });
    }

    public function down()
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn('clearance_fee');
        });
    }
}