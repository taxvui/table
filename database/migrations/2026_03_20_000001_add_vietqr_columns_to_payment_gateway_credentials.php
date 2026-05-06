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
        Schema::table('payment_gateway_credentials', function (Blueprint $table) {
            // VietQR Configuration
            $table->boolean('vietqr_status')->default(false)->after('tap_status');
            $table->text('vietqr_api_key')->nullable()->after('vietqr_status');
            $table->string('vietqr_bank_account')->nullable()->after('vietqr_api_key');
            $table->string('vietqr_bank_code')->nullable()->after('vietqr_bank_account');
            $table->string('vietqr_account_name')->nullable()->after('vietqr_bank_code');
            $table->text('vietqr_webhook_secret')->nullable()->after('vietqr_account_name');
        });

        // Add casts for encrypted columns in the model
        // app/Models/PaymentGatewayCredential.php will be updated separately
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateway_credentials', function (Blueprint $table) {
            $cols = [
                'vietqr_status',
                'vietqr_api_key',
                'vietqr_bank_account',
                'vietqr_bank_code',
                'vietqr_account_name',
                'vietqr_webhook_secret',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('payment_gateway_credentials', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
