<?php

namespace App\Models;

use App\Traits\HasRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\BaseModel;

class VietQRPayment extends BaseModel
{
    use HasFactory, HasRestaurant;

    protected $table = 'vietqr_payments';
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'webhook_payload' => 'json',
        'api_response' => 'json',
        'error_details' => 'json',
        'qr_generated_at' => 'datetime',
        'payment_confirmed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the payment associated with this VietQR payment
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the order associated with this VietQR payment
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Check if payment is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && now()->isAfter($this->expires_at);
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->payment_status === 'completed';
    }

    /**
     * Check if payment is failed
     */
    public function isFailed(): bool
    {
        return $this->payment_status === 'failed';
    }

    /**
     * Update payment status to completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'payment_status' => 'completed',
            'payment_confirmed_at' => now(),
        ]);
    }

    /**
     * Update payment status to failed
     */
    public function markAsFailed(array $errorDetails = []): void
    {
        $this->update([
            'payment_status' => 'failed',
            'error_details' => $errorDetails,
        ]);
    }

    /**
     * Update payment status to expired
     */
    public function markAsExpired(): void
    {
        $this->update([
            'payment_status' => 'expired',
        ]);
    }
}
