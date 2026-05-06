<?php

namespace App\Livewire\Checkout;

use App\Models\Order;
use App\Models\VietQRPayment;
use App\Services\VietQRService;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

/**
 * VietQR Payment Component for Checkout Pages
 * Displays and manages VietQR payment for online ordering/checkout
 */
class VietQRPayment extends Component
{
    use LivewireAlert;

    public ?Order $order = null;
    public ?VietQRPayment $vietqrPayment = null;
    public ?string $qrCodeImageUrl = null;
    public ?string $qrCodeData = null;
    public bool $isGenerating = false;
    public float $amount = 0;
    public string $accountName = '';
    public string $bankAccount = '';
    public string $bankCode = '';
    public ?string $expiresAt = null;

    public function mount(?Order $order = null)
    {
        if ($order) {
            $this->order = $order;
            $this->loadVietQRData();
        }
    }

    private function loadVietQRData()
    {
        try {
            if (!$this->order) {
                return;
            }

            // Get the most recent non-expired VietQR payment
            $this->vietqrPayment = VietQRPayment::where('order_id', $this->order->id)
                ->where('payment_status', 'pending')
                ->latest()
                ->first();

            if ($this->vietqrPayment && !$this->vietqrPayment->isExpired()) {
                $this->qrCodeImageUrl = $this->vietqrPayment->qr_code_image_url;
                $this->qrCodeData = $this->vietqrPayment->qr_code_data;
                $this->accountName = $this->vietqrPayment->account_name;
                $this->bankAccount = $this->vietqrPayment->bank_account;
                $this->bankCode = $this->vietqrPayment->bank_code;
                $this->amount = $this->vietqrPayment->amount;
                $this->expiresAt = $this->vietqrPayment->expires_at?->format('H:i');
            } else {
                $this->vietqrPayment = null;
            }
        } catch (\Exception $e) {
            Log::error('Checkout VietQR: Failed to load data', [
                'order_id' => $this->order?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function generateVietQR()
    {
        try {
            if (!$this->order) {
                return;
            }

            $this->isGenerating = true;

            $restaurantId = $this->order->restaurant_id ?? auth()->user()?->restaurant_id;
            if (!$restaurantId) {
                $this->alert('error', 'Restaurant information not found.', [
                    'toast' => true,
                    'position' => 'top-end',
                ]);
                $this->isGenerating = false;
                return;
            }

            $service = new VietQRService($restaurantId);

            if (!$service->isConfigured()) {
                $this->alert('error', 'VietQR payment is not configured for this restaurant.', [
                    'toast' => true,
                    'position' => 'top-end',
                ]);
                $this->isGenerating = false;
                return;
            }

            // Calculate amount due
            $amountDue = $this->order->total - ($this->order->amount_paid ?? 0);
            
            if ($amountDue <= 0) {
                $this->alert('info', 'Order has already been paid.', [
                    'toast' => true,
                    'position' => 'top-end',
                ]);
                $this->isGenerating = false;
                return;
            }

            $description = sprintf(
                'Order #%s',
                $this->order->show_formatted_order_number ?? $this->order->id
            );

            $result = $service->generateQRCode(
                amount: $amountDue,
                description: $description,
                restaurantId: $restaurantId,
                orderId: $this->order->id
            );

            if ($result['success']) {
                $this->loadVietQRData();
                $this->alert('success', 'VietQR code generated successfully!', [
                    'toast' => true,
                    'position' => 'top-end',
                ]);
                $this->dispatch('vietqr-generated');
            } else {
                $this->alert('error', 'Failed to generate QR: ' . ($result['message'] ?? 'Unknown error'), [
                    'toast' => true,
                    'position' => 'top-end',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Checkout VietQR: Generation failed', [
                'order_id' => $this->order?->id,
                'error' => $e->getMessage(),
            ]);
            
            $this->alert('error', 'Error: ' . $e->getMessage(), [
                'toast' => true,
                'position' => 'top-end',
            ]);
        } finally {
            $this->isGenerating = false;
        }
    }

    public function render()
    {
        return view('livewire.checkout.vietqr-payment');
    }
}
