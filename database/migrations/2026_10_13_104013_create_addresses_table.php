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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')->constrained()->restrictOnDelete();

            $table->string('label', 100)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('phone', 30)->nullable();

            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 100);
            $table->string('state_or_region', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2)->default('SA');

            $table->boolean('is_shipping')->default(true);
            $table->boolean('is_billing')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
