<?php

namespace App\Livewire\Order;

use App\Models\Order;
use App\Models\VietQRPayment;
use App\Services\VietQRService;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class VietQRReceipt extends Component
{
    use LivewireAlert;

    public ?Order $order = null;
    public ?VietQRPayment $vietqrPayment = null;
    public ?string $qrCodeImageUrl = null;
    public ?string $qrCodeImage = null;
    public bool $showQrModal = false;
    public string $accountName = '';
    public string $bankAccount = '';
    public string $bankCode = '';
    public float $amount = 0;
    public ?string $expiresAt = null;

    public function mount(Order $order)
    {
        $this->order = $order;
        $this->loadVietQRData();
    }

    private function loadVietQRData()
    {
        try {
            // Check if VietQR payment already exists for this order
            $this->vietqrPayment = VietQRPayment::where('order_id', $this->order->id)->first();

            if ($this->vietqrPayment) {
                $this->qrCodeImageUrl = $this->vietqrPayment->qr_code_image_url;
                $this->qrCodeImage = $this->vietqrPayment->qr_code_data;
                $this->accountName = $this->vietqrPayment->account_name;
                $this->bankAccount = $this->vietqrPayment->bank_account;
                $this->bankCode = $this->vietqrPayment->bank_code;
                $this->amount = $this->vietqrPayment->amount;
                $this->expiresAt = $this->vietqrPayment->expires_at?->format('Y-m-d H:i:s');
            }
        } catch (\Exception $e) {
            Log::error('VietQR Receipt: Failed to load VietQR data', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function generateVietQRCode()
    {
        try {
            $restaurantId = $this->order->restaurant_id ?? auth()->user()->restaurant_id;
            $service = new VietQRService($restaurantId);

            if (!$service->isConfigured()) {
                $this->alert('warning', 'VietQR is not configured for this restaurant.', [
                    'toast' => true,
                    'position' => 'top-end',
                ]);
                return;
            }

            // Generate QR code for the order's remaining balance
            $amount = $this->order->total - ($this->order->amount_paid ?? 0);
            
            if ($amount <= 0) {
                $this->alert('info', 'Order has already been paid in full.', [
                    'toast' => true,
                    'position' => 'top-end',
                ]);
                return;
            }

            $description = sprintf(
                'Order #%s - %s',
                $this->order->show_formatted_order_number ?? $this->order->id,
                $this->order->customer?->name ?? 'Walk-in Customer'
            );

            $result = $service->generateQRCode(
                amount: $amount,
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
            } else {
                $this->alert('error', 'Failed to generate VietQR code: ' . ($result['message'] ?? 'Unknown error'), [
                    'toast' => true,
                    'position' => 'top-end',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('VietQR Receipt: Failed to generate QR code', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);
            
            $this->alert('error', 'Error: ' . $e->getMessage(), [
                'toast' => true,
                'position' => 'top-end',
            ]);
        }
    }

    public function openQRModal()
    {
        if ($this->vietqrPayment) {
            $this->showQrModal = true;
        } else {
            $this->alert('info', 'Generate VietQR code first.', [
                'toast' => true,
                'position' => 'top-end',
            ]);
        }
    }

    public function render()
    {
        return view('livewire.order.vietqr-receipt');
    }
}
