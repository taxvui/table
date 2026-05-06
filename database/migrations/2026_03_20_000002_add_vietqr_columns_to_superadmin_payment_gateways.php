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
        Schema::table('superadmin_payment_gateways', function (Blueprint $table) {
            // VietQR Configuration
            $table->boolean('vietqr_status')->default(false)->after('tap_public_key');
            $table->text('vietqr_api_key')->nullable()->after('vietqr_status');
            $table->string('vietqr_bank_account')->nullable()->after('vietqr_api_key');
            $table->string('vietqr_bank_code')->nullable()->after('vietqr_bank_account');
            $table->string('vietqr_account_name')->nullable()->after('vietqr_bank_code');
            $table->text('vietqr_webhook_secret')->nullable()->after('vietqr_account_name');
        });

        // Add enable_vietqr to global_settings
        Schema::table('global_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('global_settings', 'enable_vietqr')) {
                $table->boolean('enable_vietqr')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('superadmin_payment_gateways', function (Blueprint $table) {
            $cols = [
                'vietqr_status',
                'vietqr_api_key',
                'vietqr_bank_account',
                'vietqr_bank_code',
                'vietqr_account_name',
                'vietqr_webhook_secret',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('superadmin_payment_gateways', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('global_settings', function (Blueprint $table) {
            if (Schema::hasColumn('global_settings', 'enable_vietqr')) {
                $table->dropColumn('enable_vietqr');
            }
        });
    }
};
