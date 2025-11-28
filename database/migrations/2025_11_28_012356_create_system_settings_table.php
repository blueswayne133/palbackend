<?php
// database/migrations/2024_01_01_create_system_settings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSystemSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default clearance fee
        DB::table('system_settings')->insert([
            [
                'key' => 'withdrawal_clearance_fee',
                'value' => '0.00',
                'description' => 'Fixed clearance fee for all withdrawals',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('system_settings');
    }
}