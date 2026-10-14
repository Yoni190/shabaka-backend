<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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
            $table->string('order_number')->unique();

            $table->foreignId('business_id')->constrained()->restrictOnDelete();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('shipping_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->foreignId('billing_address_id')->nullable()->constrained('addresses')->nullOnDelete();

            $table->json('shipping_address_snapshot')->nullable();
            $table->json('billing_address_snapshot')->nullable();

            $table->string('status', 20)->default(OrderStatus::PENDING->value);
            $table->string('payment_status', 20)->default(PaymentStatus::UNPAID->value);

            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);

            $table->char('currency', 3)->default('USD');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('payment_status');
            $table->index(['business_id', 'created_at']);
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
