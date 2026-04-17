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
            if (!Schema::hasColumn('superadmin_payment_gateways', 'payos_status')) {
                $table->boolean('payos_status')->default(false);
            }
            if (!Schema::hasColumn('superadmin_payment_gateways', 'payos_mode')) {
                $table->enum('payos_mode', ['test', 'live'])->default('test');
            }
            if (!Schema::hasColumn('superadmin_payment_gateways', 'test_payos_client_id')) {
                $table->text('test_payos_client_id')->nullable();
            }
            if (!Schema::hasColumn('superadmin_payment_gateways', 'test_payos_api_key')) {
                $table->text('test_payos_api_key')->nullable();
            }
            if (!Schema::hasColumn('superadmin_payment_gateways', 'test_payos_checksum_key')) {
                $table->text('test_payos_checksum_key')->nullable();
            }
            if (!Schema::hasColumn('superadmin_payment_gateways', 'live_payos_client_id')) {
                $table->text('live_payos_client_id')->nullable();
            }
            if (!Schema::hasColumn('superadmin_payment_gateways', 'live_payos_api_key')) {
                $table->text('live_payos_api_key')->nullable();
            }
            if (!Schema::hasColumn('superadmin_payment_gateways', 'live_payos_checksum_key')) {
                $table->text('live_payos_checksum_key')->nullable();
            }
        });

        Schema::table('restaurant_payments', function (Blueprint $table) {
           if (!Schema::hasColumn('restaurant_payments', 'payos_order_code')) {
            $table->string('payos_order_code')->nullable();
           }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('superadmin_payment_gateways', function (Blueprint $table) {
            $columns = [
                'payos_status',
                'payos_mode',
                'test_payos_client_id',
                'test_payos_api_key',
                'test_payos_checksum_key',
                'live_payos_client_id',
                'live_payos_api_key',
                'live_payos_checksum_key',
            ];
            $table->dropColumn($columns);
        });

        Schema::table('restaurant_payments', function (Blueprint $table) {
            if (Schema::hasColumn('restaurant_payments', 'payos_order_code')) {
                $table->dropColumn('payos_order_code');
            }
        });
    }
};