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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol'); // BTC, ETH
            $table->enum('side', ['buy', 'sell']);
            $table->decimal('price', 20, 2);
            $table->decimal('amount', 20, 8); // amount of asset
            $table->tinyInteger('status')->default(1); // open=1, filled=2, cancelled=3
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
