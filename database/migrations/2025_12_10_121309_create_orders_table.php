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
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('symbol', 10)->index();
            $table->enum('side', ['buy', 'sell'])->index();
            $table->decimal('price', 20, 8);
            $table->decimal('amount', 20, 8);
            $table->decimal('filled_amount', 20, 8)->default(0);
            $table->tinyInteger('status')->default(1)->index();
            $table->timestamps();

            $table->index(['symbol', 'side', 'status']);
            $table->index(['user_id', 'status']);
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
