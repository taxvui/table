<?php

namespace App\Livewire;

use App\Services\VietQRService;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CustomerDisplay extends Component
{
    public $orderItems = [];
    public $subTotal = 0;
    public $total = 0;
    public $discount = 0;
    public $orderNumber = null;
    public $taxes = [];
    public $extraCharges = [];
    public $tip = 0;
    public $deliveryFee = 0;
    public $orderType = null;
    public $status = 'idle';
    public $cashDue = null;
    public $qrCodeImageUrl = null;
    public $formattedOrderNumber = null;
    public $refreshKey = 0; // Used to force component refresh
    public $orderId = null; // Added to support VietQR generation

    // Poll every 1 second
    public function render()
    {
        $userId = auth()->id();
        $cacheKey = 'customer_display_cart_user_' . $userId;
        $cart = Cache::get($cacheKey);

        if ($cart) {
            $this->orderNumber = $cart['order_number'];
            $this->formattedOrderNumber = $cart['formatted_order_number'];
            $this->subTotal = $cart['sub_total'];
            $this->total = $cart['total'];
            $this->discount = $cart['discount'] ?? 0;
            $this->orderItems = $cart['items'] ?? [];
            $this->taxes = $cart['taxes'] ?? [];
            $this->extraCharges = $cart['extra_charges'] ?? [];
            $this->tip = $cart['tip'] ?? 0;
            $this->deliveryFee = $cart['delivery_fee'] ?? 0;
            $this->orderType = $cart['order_type'] ?? null;
            $this->status = $cart['status'] ?? 'idle';
            $this->cashDue = $cart['cash_due'] ?? null;
            $this->qrCodeImageUrl = $cart['qr_code_image_url'] ?? null;
            $this->orderId = $cart['order_id'] ?? null;
            
            // Generate VietQR code if cash_due and status is 'billed'
            if ($this->status === 'billed' && $this->cashDue > 0 && !$this->qrCodeImageUrl && $this->orderId) {
                $this->generateVietQRForCustomerDisplay();
            }
        } else {
            $this->orderNumber = null;
            $this->formattedOrderNumber = null;
            $this->subTotal = 0;
            $this->total = 0;
            $this->discount = 0;
            $this->orderItems = [];
            $this->taxes = [];
            $this->extraCharges = [];
            $this->tip = 0;
            $this->deliveryFee = 0;
            $this->orderType = null;
            $this->status = 'idle';
            $this->cashDue = null;
            $this->qrCodeImageUrl = null;
            $this->orderId = null;
        }

        return view('livewire.customer-display');
    }

    private function generateVietQRForCustomerDisplay()
    {
        try {
            $restaurantId = auth()->user()->restaurant_id ?? auth()->user()->restaurant?->id;
            if (!$restaurantId) {
                return;
            }

            $service = new VietQRService($restaurantId);
            if (!$service->isConfigured()) {
                return;
            }

            $description = sprintf(
                'Order #%s',
                $this->formattedOrderNumber ?? $this->orderNumber
            );

            $result = $service->generateQRCode(
                amount: $this->cashDue,
                description: $description,
                restaurantId: $restaurantId,
                orderId: $this->orderId
            );

            if ($result['success'] && isset($result['qr_code_url'])) {
                $this->qrCodeImageUrl = $result['qr_code_url'];
                
                // Update cache with QR code URL
                $cacheKey = 'customer_display_cart_user_' . auth()->id();
                $cart = Cache::get($cacheKey);
                if ($cart) {
                    $cart['qr_code_image_url'] = $result['qr_code_url'];
                    Cache::put($cacheKey, $cart, 3600);
                }
            }
        } catch (\Exception $e) {
            Log::error('CustomerDisplay: Failed to generate VietQR code', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function refreshCustomerDisplay()
    {
        // This method will be called by Pusher to refresh the display
        // Increment refreshKey to force Livewire to detect a change and re-render
        $this->refreshKey++;
    }
}
