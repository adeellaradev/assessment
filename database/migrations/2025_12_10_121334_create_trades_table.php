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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buy_order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('sell_order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->string('symbol', 10)->index();
            $table->decimal('price', 20, 8);
            $table->decimal('amount', 20, 8);
            $table->timestamp('executed_at')->useCurrent();
            $table->timestamps();

            $table->index(['symbol', 'executed_at']);
            $table->index(['buyer_id', 'executed_at']);
            $table->index(['seller_id', 'executed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
