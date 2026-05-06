<?php

namespace App\Http\Controllers;

use App\Models\VietQRPayment;
use App\Models\Payment;
use App\Models\Order;
use App\Services\VietQRService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VietQRPaymentController extends Controller
{
    private VietQRService $vietqrService;

    public function __construct()
    {
        $this->vietqrService = new VietQRService(auth()->user()?->restaurant_id);
    }

    /**
     * Generate QR code for payment
     */
    public function generateQRCode(Request $request): JsonResponse
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:1000',
                'order_id' => 'nullable|exists:orders,id',
                'payment_id' => 'nullable|exists:payments,id',
                'description' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $amount = $request->input('amount');
            $orderId = $request->input('order_id');
            $paymentId = $request->input('payment_id');
            $description = $request->input('description', 'Payment');
            $restaurantId = auth()->user()?->restaurant_id;

            // Generate QR code
            $result = $this->vietqrService->generateQRCode(
                $amount,
                $description,
                $restaurantId,
                $orderId,
                $paymentId
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('VietQR: Generate QR code error', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate QR code',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get QR code details
     */
    public function getQRCode(string $qrCodeId): JsonResponse
    {
        try {
            $vietqrPayment = VietQRPayment::where('qr_code_id', $qrCodeId)->firstOrFail();

            // Check if user has access
            if ($vietqrPayment->restaurant_id && $vietqrPayment->restaurant_id !== auth()->user()?->restaurant_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'qr_code_id' => $vietqrPayment->qr_code_id,
                    'transaction_id' => $vietqrPayment->transaction_id,
                    'amount' => $vietqrPayment->amount,
                    'currency' => $vietqrPayment->currency,
                    'status' => $vietqrPayment->payment_status,
                    'qr_code_url' => $vietqrPayment->qr_code_image_url,
                    'bank_account' => $vietqrPayment->bank_account,
                    'account_name' => $vietqrPayment->account_name,
                    'expires_at' => $vietqrPayment->expires_at,
                    'created_at' => $vietqrPayment->created_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('VietQR: Get QR code error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'QR code not found',
            ], 404);
        }
    }

    /**
     * Check payment status
     */
    public function checkStatus(string $transactionId): JsonResponse
    {
        try {
            $result = $this->vietqrService->checkTransactionStatus($transactionId);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('VietQR: Check status error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status',
            ], 500);
        }
    }

    /**
     * Handle webhook from VietQR API
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();
            $signature = $request->header('X-Signature') ?? $request->input('signature');

            Log::info('VietQR Webhook received', [
                'payload' => $payload,
                'has_signature' => !empty($signature),
            ]);

            // Verify webhook signature
            if (!$this->vietqrService->verifyWebhookSignature($payload, $signature)) {
                Log::warning('VietQR Webhook: Invalid signature');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid webhook signature',
                ], 401);
            }

            // Handle payment webhook
            $result = $this->vietqrService->handlePaymentWebhook($payload);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('VietQR Webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Test VietQR connection (for admin)
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->vietqrService->testConnection();

            if ($result['success']) {
                return response()->json($result);
            }

            return response()->json($result, 400);
        } catch (\Exception $e) {
            Log::error('VietQR: Test connection error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed',
            ], 500);
        }
    }

    /**
     * Get public credentials for frontend
     */
    public function getPublicInfo(): JsonResponse
    {
        try {
            $credentials = $this->vietqrService->getPublicCredentials();

            return response()->json([
                'success' => true,
                'data' => $credentials,
            ]);
        } catch (\Exception $e) {
            Log::error('VietQR: Get public info error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get VietQR info',
            ], 500);
        }
    }

    /**
     * List VietQR payments (for admin/restaurant)
     */
    public function listPayments(Request $request): JsonResponse
    {
        try {
            $query = VietQRPayment::query();

            // Filter by restaurant if user is restaurant staff
            if ($restaurantId = auth()->user()?->restaurant_id) {
                $query->where('restaurant_id', $restaurantId);
            }

            // Filter by status if provided
            if ($status = $request->input('status')) {
                $query->where('payment_status', $status);
            }

            // Filter by date range if provided
            if ($startDate = $request->input('start_date')) {
                $query->where('created_at', '>=', $startDate);
            }
            if ($endDate = $request->input('end_date')) {
                $query->where('created_at', '<=', $endDate);
            }

            $payments = $query->orderByDesc('created_at')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $payments,
            ]);
        } catch (\Exception $e) {
            Log::error('VietQR: List payments error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payments',
            ], 500);
        }
    }

    /**
     * Get payment details
     */
    public function getPaymentDetails(int $id): JsonResponse
    {
        try {
            $payment = VietQRPayment::findOrFail($id);

            // Check access
            if ($payment->restaurant_id && $payment->restaurant_id !== auth()->user()?->restaurant_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $payment,
            ]);
        } catch (\Exception $e) {
            Log::error('VietQR: Get payment details error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }
    }
}
