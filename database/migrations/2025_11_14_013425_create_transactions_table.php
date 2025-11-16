<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // NEW FIELD
            $table->foreignId('user_id')->constrained('users');

            $table->foreignId('sender_id')->constrained('users');
            $table->foreignId('receiver_id')->constrained('users');

            $table->decimal('amount', 10, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('type')->default('payment'); 
            $table->string('status')->default('pending'); 
            $table->string('description')->nullable();
            $table->string('reference_id')->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);  
            $table->index(['sender_id', 'status']);
            $table->index(['receiver_id', 'status']);
            $table->index('reference_id');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
