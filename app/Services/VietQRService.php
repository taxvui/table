<?php

namespace App\Services;

use App\Models\VietQRPayment;
use App\Models\SuperadminPaymentGateway;
use App\Models\PaymentGatewayCredential;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VietQRService
{
    const VIETQR_API_BASE = 'https://api.vietqr.io/v2';
    const WEBHOOK_TIMEOUT = 300; // 5 minutes
    const QR_EXPIRATION_TIME = 3600; // 1 hour in seconds

    private ?string $apiKey = null;
    private ?array $credentials = null;

    /**
     * Initialize service with credentials
     */
    public function __construct(private ?int $restaurantId = null)
    {
        $this->loadCredentials();
    }

    /**
     * Load credentials from database
     */
    private function loadCredentials(): void
    {
        try {
            // Load restaurant-specific credentials if available
            if ($this->restaurantId) {
                $restaurantCreds = PaymentGatewayCredential::where('restaurant_id', $this->restaurantId)->first();
                if ($restaurantCreds && $restaurantCreds->vietqr_status) {
                    $this->credentials = [
                        'api_key' => $restaurantCreds->vietqr_api_key,
                        'bank_account' => $restaurantCreds->vietqr_bank_account,
                        'bank_code' => $restaurantCreds->vietqr_bank_code,
                        'account_name' => $restaurantCreds->vietqr_account_name,
                        'webhook_secret' => $restaurantCreds->vietqr_webhook_secret,
                    ];
                    $this->apiKey = $restaurantCreds->vietqr_api_key;
                    return;
                }
            }

            // Load Super Admin global credentials
            $superAdminCreds = SuperadminPaymentGateway::first();
            if ($superAdminCreds && $superAdminCreds->vietqr_status) {
                $this->credentials = [
                    'api_key' => $superAdminCreds->vietqr_api_key,
                    'bank_account' => $superAdminCreds->vietqr_bank_account,
                    'bank_code' => $superAdminCreds->vietqr_bank_code,
                    'account_name' => $superAdminCreds->vietqr_account_name,
                    'webhook_secret' => $superAdminCreds->vietqr_webhook_secret,
                ];
                $this->apiKey = $superAdminCreds->vietqr_api_key;
            }
        } catch (Exception $e) {
            Log::error('VietQR Service: Failed to load credentials', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check if VietQR is configured
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== null && !empty($this->credentials);
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'VietQR API key is not configured',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(10)->get(self::VIETQR_API_BASE . '/qr/info');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'VietQR connection successful',
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => 'VietQR API returned an error: ' . $response->body(),
            ];
        } catch (Exception $e) {
            Log::error('VietQR Service: Connection test failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate QR code for payment
     */
    public function generateQRCode(
        float $amount,
        string $description = '',
        ?int $restaurantId = null,
        ?int $orderId = null,
        ?int $paymentId = null
    ): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'VietQR is not configured',
                'message' => 'VietQR API key not found',
            ];
        }

        try {
            $payload = [
                'bank_account' => $this->credentials['bank_account'],
                'bank_code' => $this->credentials['bank_code'],
                'amount' => (int)$amount,
                'description' => $description ?: 'Payment',
                'account_name' => $this->credentials['account_name'],
            ];

            Log::info('VietQR Service: Generating QR code', ['payload' => $payload]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(15)->post(self::VIETQR_API_BASE . '/qr/generate', $payload);

            if ($response->failed()) {
                Log::error('VietQR API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'error' => 'API Error',
                    'message' => $response->json('message', 'Failed to generate QR code'),
                ];
            }

            $data = $response->json();

            // Save to database
            $vietqrPayment = VietQRPayment::create([
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'restaurant_id' => $restaurantId,
                'qr_code_id' => $data['qr_code_id'] ?? Str::uuid(),
                'transaction_id' => $data['transaction_id'] ?? null,
                'amount' => $amount,
                'bank_account' => $this->credentials['bank_account'],
                'bank_code' => $this->credentials['bank_code'],
                'account_name' => $this->credentials['account_name'],
                'payment_status' => 'pending',
                'qr_code_data' => $data['qr_code_image'] ?? null,
                'qr_code_image_url' => $data['qr_code_url'] ?? null,
                'api_response' => $data,
                'qr_generated_at' => now(),
                'expires_at' => now()->addSeconds(self::QR_EXPIRATION_TIME),
            ]);

            return [
                'success' => true,
                'qr_code_id' => $vietqrPayment->qr_code_id,
                'transaction_id' => $vietqrPayment->transaction_id,
                'qr_code_url' => $vietqrPayment->qr_code_image_url,
                'qr_code_image' => $vietqrPayment->qr_code_data,
                'amount' => $amount,
                'bank_account' => $this->credentials['bank_account'],
                'account_name' => $this->credentials['account_name'],
                'expires_at' => $vietqrPayment->expires_at,
                'message' => 'QR code generated successfully',
            ];
        } catch (Exception $e) {
            Log::error('VietQR Service: Failed to generate QR code', [
                'error' => $e->getMessage(),
                'amount' => $amount,
            ]);

            return [
                'success' => false,
                'error' => 'Generation Failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        if (!$this->isConfigured() || !$this->credentials['webhook_secret']) {
            Log::warning('VietQR Service: Webhook secret not configured');
            return false;
        }

        try {
            $expectedSignature = hash_hmac(
                'sha256',
                json_encode($payload),
                $this->credentials['webhook_secret'],
                false
            );

            return hash_equals($expectedSignature, $signature);
        } catch (Exception $e) {
            Log::error('VietQR Service: Webhook verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Handle payment webhook
     */
    public function handlePaymentWebhook(array $payload): array
    {
        try {
            $transactionId = $payload['transaction_id'] ?? null;
            $status = $payload['status'] ?? null;

            if (!$transactionId) {
                return [
                    'success' => false,
                    'message' => 'Invalid webhook payload: missing transaction_id',
                ];
            }

            $vietqrPayment = VietQRPayment::where('transaction_id', $transactionId)->first();

            if (!$vietqrPayment) {
                return [
                    'success' => false,
                    'message' => 'VietQR payment record not found',
                ];
            }

            // Update payment status
            if ($status === 'completed' || $payload['amount_received'] ?? 0 >= $vietqrPayment->amount) {
                $vietqrPayment->markAsCompleted();

                // Update related Payment record if exists
                if ($vietqrPayment->payment) {
                    $vietqrPayment->payment->update([
                        'status' => 'completed',
                        'transaction_id' => $transactionId,
                    ]);
                }

                Log::info('VietQR Payment completed', ['transaction_id' => $transactionId]);

                return [
                    'success' => true,
                    'message' => 'Payment confirmed',
                ];
            } elseif ($status === 'failed' || $status === 'expired') {
                $vietqrPayment->markAsFailed([
                    'reason' => $payload['reason'] ?? 'Unknown',
                    'status' => $status,
                ]);

                Log::warning('VietQR Payment failed', [
                    'transaction_id' => $transactionId,
                    'status' => $status,
                ]);

                return [
                    'success' => true,
                    'message' => 'Payment marked as failed',
                ];
            }

            // Update webhook payload for reference
            $vietqrPayment->update([
                'webhook_payload' => $payload,
                'status_description' => $payload['description'] ?? null,
            ]);

            return [
                'success' => true,
                'message' => 'Webhook processed',
            ];
        } catch (Exception $e) {
            Log::error('VietQR Service: Webhook handling failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'message' => 'Webhook processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check transaction status
     */
    public function checkTransactionStatus(string $transactionId): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'VietQR is not configured',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(10)->get(self::VIETQR_API_BASE . '/qr/status/' . $transactionId);

            if ($response->successful()) {
                $data = $response->json();

                // Update local record if found
                $vietqrPayment = VietQRPayment::where('transaction_id', $transactionId)->first();
                if ($vietqrPayment) {
                    $vietqrPayment->update([
                        'api_response' => $data,
                    ]);
                }

                return [
                    'success' => true,
                    'status' => $data['status'] ?? 'unknown',
                    'amount_received' => $data['amount_received'] ?? 0,
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to check transaction status',
            ];
        } catch (Exception $e) {
            Log::error('VietQR Service: Status check failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);

            return [
                'success' => false,
                'message' => 'Status check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get credentials for frontend (without sensitive data)
     */
    public function getPublicCredentials(): array
    {
        return [
            'bank_account' => $this->credentials['bank_account'] ?? null,
            'bank_code' => $this->credentials['bank_code'] ?? null,
            'account_name' => $this->credentials['account_name'] ?? null,
            'is_configured' => $this->isConfigured(),
        ];
    }
}
