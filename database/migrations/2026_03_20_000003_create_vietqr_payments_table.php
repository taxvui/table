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
        Schema::create('vietqr_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            
            $table->unsignedBigInteger('restaurant_id')->nullable();
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
            
            $table->string('qr_code_id')->nullable()->unique();
            $table->string('transaction_id')->nullable()->unique();
            
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('VND');
            
            $table->string('bank_account')->nullable();
            $table->string('bank_code')->nullable();
            $table->string('account_name')->nullable();
            
            $table->enum('payment_status', ['pending', 'processing', 'completed', 'failed', 'expired'])->default('pending');
            $table->string('status_description')->nullable();
            
            $table->longText('qr_code_data')->nullable(); // Base64 encoded QR image
            $table->string('qr_code_image_url')->nullable();
            
            $table->json('webhook_payload')->nullable();
            $table->json('api_response')->nullable();
            $table->json('error_details')->nullable();
            
            $table->timestamp('qr_generated_at')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            $table->timestamps();
            
            $table->index('transaction_id');
            $table->index('order_id');
            $table->index('restaurant_id');
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vietqr_payments');
    }
};
