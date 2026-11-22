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
        $table->string('location');
        $table->string('payment_info')->nullable();
        $table->enum('status', ['preparing', 'sent', 'received'])->default('preparing');
        $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
        $table->decimal('total_price', 10, 2)->default(0);
        $table->boolean('stock_deducted')->default(false); // لمنع خصم المخزون مرتين
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
