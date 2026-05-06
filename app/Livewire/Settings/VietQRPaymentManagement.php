<?php

namespace App\Livewire\Settings;

use App\Models\VietQRPayment;
use Illuminate\Pagination\Paginator;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log;

class VietQRPaymentManagement extends Component
{
    use LivewireAlert, WithPagination;

    public $search = '';
    public $status = '';
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public $perPage = 15;
    public $selectedPayment = null;
    public $showDetails = false;

    protected $queryString = ['search', 'status', 'sortBy', 'sortDirection'];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function sort($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function viewDetails($paymentId)
    {
        $this->selectedPayment = VietQRPayment::find($paymentId);
        $this->showDetails = true;
    }

    public function closeDetails()
    {
        $this->showDetails = false;
        $this->selectedPayment = null;
    }

    public function retryPayment($paymentId)
    {
        try {
            $vietqrPayment = VietQRPayment::find($paymentId);
            
            if (!$vietqrPayment) {
                $this->alert('error', 'Payment not found.', ['toast' => true]);
                return;
            }

            // Only retry if payment is expired or pending
            if ($vietqrPayment->payment_status === 'confirmed') {
                $this->alert('warning', 'This payment has already been confirmed.', ['toast' => true]);
                return;
            }

            // Regenerate QR code
            $service = new \App\Services\VietQRService($vietqrPayment->restaurant_id);
            
            if (!$service->isConfigured()) {
                $this->alert('error', 'VietQR is not configured for this restaurant.', ['toast' => true]);
                return;
            }

            $result = $service->generateQRCode(
                amount: $vietqrPayment->amount,
                description: sprintf('Retry - Order #%s', $vietqrPayment->order?->id),
                restaurantId: $vietqrPayment->restaurant_id,
                orderId: $vietqrPayment->order_id
            );

            if ($result['success']) {
                $this->alert('success', 'QR code regenerated successfully!', ['toast' => true]);
                $this->dispatch('refreshPayments');
            } else {
                $this->alert('error', 'Failed to regenerate QR code.', ['toast' => true]);
            }
        } catch (\Exception $e) {
            Log::error('VietQR Payment Management: Retry failed', ['error' => $e->getMessage()]);
            $this->alert('error', 'Error: ' . $e->getMessage(), ['toast' => true]);
        }
    }

    public function cancelPayment($paymentId)
    {
        try {
            $vietqrPayment = VietQRPayment::find($paymentId);
            
            if (!$vietqrPayment) {
                $this->alert('error', 'Payment not found.', ['toast' => true]);
                return;
            }

            $vietqrPayment->update([
                'payment_status' => 'cancelled',
            ]);

            $this->alert('success', 'Payment cancelled successfully.', ['toast' => true]);
            $this->dispatch('refreshPayments');
        } catch (\Exception $e) {
            Log::error('VietQR Payment Management: Cancel failed', ['error' => $e->getMessage()]);
            $this->alert('error', 'Error: ' . $e->getMessage(), ['toast' => true]);
        }
    }

    public function getPayments()
    {
        $restaurantId = auth()->user()->restaurant_id;
        
        $query = VietQRPayment::where('restaurant_id', $restaurantId)
            ->with('order', 'payment');

        // Filter by status
        if ($this->status) {
            $query->where('payment_status', $this->status);
        }

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('order', function ($subQ) {
                    $subQ->where('id', 'like', '%' . $this->search . '%');
                })->orWhere('transaction_id', 'like', '%' . $this->search . '%')
                  ->orWhere('qr_code_id', 'like', '%' . $this->search . '%');
            });
        }

        // Sort
        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.settings.vietqr-payment-management', [
            'payments' => $this->getPayments(),
        ]);
    }
}
