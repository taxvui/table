<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\VietQRPayment;
use App\Models\Payment;
use App\Services\VietQRService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class VietQRWebhookController extends Controller
{
    /**
     * Handle VietQR payment webhook
     */
    public function handlePaymentWebhook(Request $request)
    {
        try {
            Log::info('VietQR webhook received', [
                'payload' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            // Get webhook payload and signature
            $payload = $request->all();
            $signature = $request->header('X-VietQR-Signature') ?? $request->header('Signature');

            if (!$signature) {
                Log::warning('VietQR webhook: Missing signature header');
                return response()->json(['error' => 'Missing signature'], 401);
            }

            // Verify webhook signature with all possible credentials (super admin + all restaurants)
            $service = new VietQRService();
            if (!$service->verifyWebhookSignature($payload, $signature)) {
                Log::warning('VietQR webhook: Invalid signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Process the payment webhook
            $result = $this->processPaymentWebhook($payload);

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'] ?? 'Webhook processed successfully',
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'] ?? 'Failed to process webhook',
                ], 400);
            }
        } catch (Exception $e) {
            Log::error('VietQR webhook: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Process the payment webhook data
     */
    private function processPaymentWebhook(array $payload): array
    {
        try {
            return DB::transaction(function () use ($payload) {
                // Extract payment details from webhook
                $transactionId = $payload['transaction_id'] ?? $payload['transactionId'] ?? null;
                $status = $payload['status'] ?? null;
                $amount = $payload['amount'] ?? 0;
                $bankCode = $payload['bank_code'] ?? $payload['bankCode'] ?? null;
                $bankAccount = $payload['bank_account'] ?? $payload['bankAccount'] ?? null;
                $timestamp = $payload['timestamp'] ?? $payload['paid_at'] ?? null;

                if (!$transactionId) {
                    return [
                        'success' => false,
                        'message' => 'Missing transaction_id in webhook payload',
                    ];
                }

                if (!$status) {
                    return [
                        'success' => false,
                        'message' => 'Missing status in webhook payload',
                    ];
                }

                // Find the VietQR payment record
                $vietqrPayment = VietQRPayment::where('transaction_id', $transactionId)
                    ->orWhere('qr_code_id', $transactionId)
                    ->first();

                if (!$vietqrPayment) {
                    Log::warning('VietQR webhook: Payment record not found', [
                        'transaction_id' => $transactionId,
                    ]);
                    return [
                        'success' => false,
                        'message' => 'Payment record not found',
                    ];
                }

                // Update VietQR payment status
                $vietqrPayment->update([
                    'payment_status' => strtolower($status),
                    'webhook_payload' => $payload,
                    'payment_confirmed_at' => $timestamp ? \Carbon\Carbon::parse($timestamp) : now(),
                ]);

                // Handle successful payment
                if (strtolower($status) === 'success' || strtolower($status) === 'paid') {
                    return $this->handleSuccessfulPayment($vietqrPayment, $payload);
                }

                // Handle failed or pending payment
                if (strtolower($status) === 'failed' || strtolower($status) === 'cancelled') {
                    return $this->handleFailedPayment($vietqrPayment);
                }

                return [
                    'success' => true,
                    'message' => 'Webhook status updated',
                ];
            });
        } catch (Exception $e) {
            Log::error('VietQR webhook: Processing failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'message' => 'Processing error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle successful payment from webhook
     */
    private function handleSuccessfulPayment(VietQRPayment $vietqrPayment, array $payload): array
    {
        try {
            $order = $vietqrPayment->order;
            
            if (!$order) {
                Log::warning('VietQR webhook: Order not found for payment', [
                    'vietqr_payment_id' => $vietqrPayment->id,
                ]);
                return [
                    'success' => false,
                    'message' => 'Associated order not found',
                ];
            }

            // Create or update payment record
            $payment = Payment::where('order_id', $order->id)
                ->where('payment_method', 'vietqr')
                ->latest()
                ->first();

            if (!$payment) {
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'payment_method' => 'vietqr',
                    'amount' => $vietqrPayment->amount,
                    'transaction_id' => $payload['transaction_id'] ?? null,
                    'reference_number' => $payload['reference_number'] ?? null,
                ]);
            } else {
                $payment->update([
                    'amount' => $vietqrPayment->amount,
                    'transaction_id' => $payload['transaction_id'] ?? $payment->transaction_id,
                ]);
            }

            // Update VietQR payment
            $vietqrPayment->update([
                'payment_id' => $payment->id,
                'payment_status' => 'confirmed',
            ]);

            // Update order status if fully paid
            $totalPaid = Payment::where('order_id', $order->id)
                ->where('payment_method', '!=', 'due')
                ->sum('amount');

            if ($totalPaid >= $order->total) {
                $order->update([
                    'status' => 'paid',
                    'amount_paid' => $totalPaid,
                ]);
            } else {
                $order->update([
                    'amount_paid' => $totalPaid,
                    'status' => $totalPaid > 0 ? 'payment_due' : 'pending',
                ]);
            }

            Log::info('VietQR payment successful', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'amount' => $vietqrPayment->amount,
            ]);

            return [
                'success' => true,
                'message' => 'Payment confirmed and order updated',
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ];
        } catch (Exception $e) {
            Log::error('VietQR webhook: Success handling failed', [
                'error' => $e->getMessage(),
                'vietqr_payment_id' => $vietqrPayment->id,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process successful payment: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle failed payment from webhook
     */
    private function handleFailedPayment(VietQRPayment $vietqrPayment): array
    {
        try {
            $vietqrPayment->update([
                'payment_status' => 'failed',
            ]);

            Log::info('VietQR payment failed', [
                'vietqr_payment_id' => $vietqrPayment->id,
                'order_id' => $vietqrPayment->order_id,
            ]);

            return [
                'success' => true,
                'message' => 'Failed payment status recorded',
            ];
        } catch (Exception $e) {
            Log::error('VietQR webhook: Failure handling failed', [
                'error' => $e->getMessage(),
                'vietqr_payment_id' => $vietqrPayment->id,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to record payment failure',
            ];
        }
    }

    /**
     * Get VietQR payment status
     */
    public function getPaymentStatus($orderId)
    {
        try {
            $order = Order::find($orderId);
            
            if (!$order) {
                return response()->json(['error' => 'Order not found'], 404);
            }

            $vietqrPayment = VietQRPayment::where('order_id', $order->id)
                ->latest()
                ->first();

            if (!$vietqrPayment) {
                return response()->json([
                    'status' => 'no_payment',
                    'message' => 'No VietQR payment for this order',
                ]);
            }

            return response()->json([
                'status' => $vietqrPayment->payment_status,
                'amount' => $vietqrPayment->amount,
                'expires_at' => $vietqrPayment->expires_at,
                'is_expired' => $vietqrPayment->isExpired(),
                'payment_confirmed_at' => $vietqrPayment->payment_confirmed_at,
            ]);
        } catch (Exception $e) {
            Log::error('VietQR: Get payment status failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            return response()->json([
                'error' => 'Failed to retrieve payment status',
            ], 500);
        }
    }
}
