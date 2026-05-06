<?php

namespace App\Livewire\POS;

use App\Models\Order;
use App\Models\VietQRPayment;
use App\Services\VietQRService;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

/**
 * VietQR Display Component for POS Terminal
 * Displays QR code for payment on the customer-facing POS display
 */
class VietQRDisplay extends Component
{
    use LivewireAlert;

    public ?Order $order = null;
    public ?VietQRPayment $vietqrPayment = null;
    public ?string $qrCodeImageUrl = null;
    public bool $showFullScreenQR = false;
    public float $amount = 0;
    public string $accountName = '';
    public string $bankAccount = '';
    public string $bankCode = '';

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

            // Check if VietQR payment already exists for this order
            $this->vietqrPayment = VietQRPayment::where('order_id', $this->order->id)
                ->latest()
                ->first();

            if ($this->vietqrPayment && !$this->vietqrPayment->isExpired()) {
                $this->qrCodeImageUrl = $this->vietqrPayment->qr_code_image_url;
                $this->accountName = $this->vietqrPayment->account_name;
                $this->bankAccount = $this->vietqrPayment->bank_account;
                $this->bankCode = $this->vietqrPayment->bank_code;
                $this->amount = $this->vietqrPayment->amount;
            }
        } catch (\Exception $e) {
            Log::error('VietQR POS Display: Failed to load data', [
                'order_id' => $this->order?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function generateAndDisplayQR()
    {
        try {
            if (!$this->order) {
                return;
            }

            $restaurantId = $this->order->restaurant_id ?? auth()->user()->restaurant_id;
            $service = new VietQRService($restaurantId);

            if (!$service->isConfigured()) {
                return;
            }

            // Calculate amount due
            $amountDue = $this->order->total - ($this->order->amount_paid ?? 0);
            
            if ($amountDue <= 0) {
                return;
            }

            $description = sprintf(
                'Order #%s - %s',
                $this->order->show_formatted_order_number ?? $this->order->id,
                $this->order->customer?->name ?? 'Walk-in'
            );

            $result = $service->generateQRCode(
                amount: $amountDue,
                description: $description,
                restaurantId: $restaurantId,
                orderId: $this->order->id
            );

            if ($result['success']) {
                $this->loadVietQRData();
            }
        } catch (\Exception $e) {
            Log::error('VietQR POS Display: Failed to generate QR', [
                'order_id' => $this->order?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function toggleFullScreen()
    {
        $this->showFullScreenQR = !$this->showFullScreenQR;
    }

    public function render()
    {
        return view('livewire.pos.vietqr-display');
    }
}
