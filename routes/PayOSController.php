<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PayOS\PayOS;
use App\Models\SuperadminPaymentGateway;
use App\Models\RestaurantPayment;
use App\Models\Package;
use App\Models\Restaurant;
use App\Models\GlobalSubscription;
use App\Models\GlobalInvoice;
use App\Models\EmailSetting;
use App\Models\User;
use App\Notifications\RestaurantUpdatedPlan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB; // Added for database transactions
use Illuminate\Support\Facades\Log; // Added for logging
use Carbon\Carbon;

class PayOSController extends Controller
{
    protected $payOS;
    protected $paymentGateway;

    public function __construct()
    {
        $this->paymentGateway = SuperadminPaymentGateway::first();

        if (
            !$this->paymentGateway ||
            !$this->paymentGateway->payos_status ||
            !$this->getSetting('client_id') ||
            !$this->getSetting('api_key') ||
            !$this->getSetting('checksum_key')
        ) {
            Log::warning('PayOS payment gateway is not fully configured.');
            // Don't initialize if not configured, but allow methods to handle this case
            return;
        }

        $this->payOS = new PayOS(
            $this->getSetting('client_id'),
            $this->getSetting('api_key'),
            $this->getSetting('checksum_key')
        );
    }

    private function getSetting($key)
    {
        $mode = $this->paymentGateway->payos_mode; // 'test' or 'live'
        return $this->paymentGateway->{"{$mode}_payos_{$key}"};
    }

    public function initiatePayment(Request $request)
    {
        if (!$this->payOS) {
            return back()->with('error', 'PayOS is not configured.');
        }

        $request->validate([
            'license_payment' => 'required|exists:restaurant_payments,id',
            'package_id' => 'required|exists:packages,id',
        ]);

        $restaurantPayment = RestaurantPayment::findOrFail($request->license_payment);
        $package = Package::findOrFail($request->package_id);
        $restaurant = Restaurant::findOrFail($restaurantPayment->restaurant_id);

        // Create orderCode for PayOS
        // Using the restaurant payment ID as the order code for uniqueness and traceability.
        $orderCode = $restaurantPayment->id;

        // Update restaurant payment with the order code
        $restaurantPayment->update(['payos_order_code' => $orderCode]);

        $data = [
            "orderCode" => $orderCode,
            "amount" => intval($restaurantPayment->amount),
            "description" => "Thanh toan goi " . $package->name . " cho nha hang " . $restaurant->name,
            "returnUrl" => route('superadmin.payos.plan.success'),
            "cancelUrl" => route('superadmin.payos.plan.cancel'),
            "buyerName" => $restaurant->name,
            "buyerEmail" => $restaurant->email,
        ];

        try {
            $response = $this->payOS->createPaymentLink($data);
            Log::info("PayOS payment link created for orderCode: {$orderCode}", ['response' => $response]);
            return redirect($response['checkoutUrl']);
        } catch (\Throwable $th) {
            return back()->with('error', 'Lỗi tạo giao dịch PayOS: ' . $th->getMessage());
        }
    }

    public function paymentSuccess(Request $request)
    {
        // Webhook is the source of truth, this is just a redirect page.
        // We can try to find the payment and show a pending/success message.
        $orderCode = $request->input('orderCode');
        Log::info("PayOS payment success redirect received for orderCode: {$orderCode}");
        $payment = RestaurantPayment::where('payos_order_code', $orderCode)->first();

        if ($payment && $payment->status == 'paid') {
            session()->flash('flash.banner', __('messages.planUpgraded'));
            session()->flash('flash.bannerStyle', 'success');
        } else {
            // Payment might still be processing or failed, webhook will update it.
            session()->flash('flash.banner', __('messages.paymentProcessing'));
            session()->flash('flash.bannerStyle', 'info');
        }

        return redirect()->route('dashboard');
    }

    public function paymentCancel(Request $request)
    {
        $orderCode = $request->input('orderCode');
        Log::info("PayOS payment cancel redirect received for orderCode: {$orderCode}");
        if ($orderCode) {
            $payment = RestaurantPayment::where('payos_order_code', $orderCode)->first();
            if ($payment && $payment->status != 'paid') {
                $payment->status = 'failed';
                $payment->payment_date_time = now()->toDateTimeString(); // Record cancellation time
                $payment->save();
            }
        }

        session()->flash('flash.banner', __('messages.paymentCancelled'));
        session()->flash('flash.bannerStyle', 'danger');

        return redirect()->route('dashboard');
    }

    public function handleWebhook(Request $request)
    {
        if (!$this->payOS) {
            Log::error('PayOS webhook received but gateway is not configured.');
            return response()->json(["error" => 1, "message" => "PayOS not configured"], 400);
        }

        $body = $request->getContent();
        $signature = $request->header('payos-signature');
        Log::info('PayOS webhook received', ['body' => $body, 'signature' => $signature]);

        try {
            $webhookData = $this->payOS->verifyPaymentWebhookData($body, $signature);
            Log::info('PayOS webhook data verified successfully', ['webhookData' => $webhookData]);

            DB::transaction(function () use ($webhookData) {
                if ($webhookData['code'] === '00' && $webhookData['data']['status'] === 'PAID') {
                    $orderCode = $webhookData['data']['orderCode'];
                    // Find the payment that is not yet marked as 'paid' to prevent reprocessing
                    $payment = RestaurantPayment::where('payos_order_code', $orderCode)
                                                ->where('status', '!=', 'paid')
                                                ->first();

                    if ($payment) {
                        $payment->status = 'paid';
                        $payment->payment_date_time = now()->toDateTimeString();
                        $payment->transaction_id = $webhookData['data']['paymentLinkId'];
                        $payment->save();
                        Log::info("RestaurantPayment updated to 'paid' for orderCode: {$orderCode}");

                        $restaurant = Restaurant::find($payment->restaurant_id);
                        $restaurant->package_id = $payment->package_id;
                        $restaurant->package_type = $payment->package_type;
                        $restaurant->trial_ends_at = null;
                        $restaurant->is_active = true;
                        $restaurant->status = 'active';

                        $package = Package::find($payment->package_id);
                        if ($package->package_type->value === 'lifetime') {
                            $restaurant->license_expire_on = null;
                        } else {
                            $planType = $payment->package_type;
                            $restaurant->license_expire_on = $planType === 'annual'
                                ? now()->addYear()
                                : now()->addMonth();
                        }
                        $restaurant->save();
                        clearRestaurantModulesCache($restaurant->id);
                        Log::info("Restaurant plan updated for restaurant ID: {$restaurant->id}");

                        // Create subscription and invoice records
                        $this->createSubscriptionAndInvoice($payment, $restaurant);

                        // Send notifications
                        $this->sendNotifications($restaurant, $payment->package_id);
                    } else {
                        Log::warning("PayOS webhook: Payment with orderCode {$orderCode} not found or already paid.");
                    }
                }
            });

            return response()->json(["error" => 0, "message" => "Ok", "data" => $webhookData]);
        } catch (\Throwable $th) {
            Log::error('PayOS webhook processing failed', ['error' => $th->getMessage(), 'trace' => $th->getTraceAsString()]);
            return response()->json(["error" => 1, "message" => $th->getMessage()], 400);
        }
    }

    private function createSubscriptionAndInvoice(RestaurantPayment $payment, Restaurant $restaurant)
    {
        GlobalSubscription::where('restaurant_id', $restaurant->id)
            ->where('subscription_status', 'active')
            ->update(['subscription_status' => 'inactive']);

        $subscription = new GlobalSubscription();
        $subscription->transaction_id = $payment->transaction_id;
        $subscription->restaurant_id = $restaurant->id;
        $subscription->package_type = $restaurant->package_type;
        $subscription->currency_id = $payment->currency_id;
        $subscription->quantity = 1;
        $subscription->package_id = $restaurant->package_id;
        $subscription->gateway_name = 'payos';
        $subscription->subscription_status = 'active';
        $subscription->ends_at = $restaurant->license_expire_on;
        $subscription->subscribed_on_date = now();
        $subscription->save();

        GlobalInvoice::updateOrCreate(
            ['transaction_id' => $subscription->transaction_id],
            [
                'restaurant_id' => $restaurant->id,
                'currency_id' => $subscription->currency_id,
                'package_id' => $subscription->package_id,
                'global_subscription_id' => $subscription->id,
                'package_type' => $subscription->package_type,
                'total' => $payment->amount,
                'gateway_name' => 'payos',
                'pay_date' => now(),
                'next_pay_date' => $restaurant->license_expire_on,
            ]
        );
    }

    private function sendNotifications(Restaurant $restaurant, int $packageId)
    {
        $emailSetting = EmailSetting::first();
        if ($emailSetting->mail_driver === 'smtp' && $emailSetting->verified) {
            $generatedBy = User::withoutGlobalScopes()->whereNull('branch_id')->whereNull('restaurant_id')->first();
            if ($generatedBy) {
                Notification::send($generatedBy, new RestaurantUpdatedPlan($restaurant, $packageId));
            }

            $restaurantAdmin = $restaurant->restaurantAdmin($restaurant);
            if ($restaurantAdmin) {
                Notification::send($restaurantAdmin, new RestaurantUpdatedPlan($restaurant, $packageId));
            }
        }
    }
}