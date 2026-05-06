<div class="space-y-4">
    @if ($order && $order->total > $order->amount_paid)
        <div class="p-6 border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700">
            <div class="flex items-center gap-3 mb-4">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">VietQR Payment</h3>
            </div>

            @if ($vietqrPayment && $qrCodeImageUrl && !$vietqrPayment->isExpired())
                <!-- QR Code Display -->
                <div class="space-y-4">
                    <div class="flex justify-center p-4 bg-white dark:bg-gray-800 rounded-lg">
                        <img src="{{ $qrCodeImageUrl }}" alt="VietQR Payment Code" class="h-64 w-64 object-contain">
                    </div>

                    <!-- Amount & Details -->
                    <div class="space-y-2 text-center">
                        <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                            {{ currency_format($amount, restaurant()->currency_id) }}
                        </p>
                        <div class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                            <p><strong>Account:</strong> {{ $accountName }}</p>
                            <p><strong>Number:</strong> {{ $bankAccount }}</p>
                            <p><strong>Bank:</strong> {{ $bankCode }}</p>
                            @if($expiresAt)
                                <p class="text-xs text-orange-600 dark:text-orange-400 pt-1">
                                    Expires at {{ $expiresAt }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div class="bg-blue-100 dark:bg-blue-800/30 border border-blue-300 dark:border-blue-700 rounded-lg p-3">
                        <p class="text-sm text-blue-900 dark:text-blue-100">
                            <strong>How to pay:</strong> Open your banking app and scan this QR code to complete the payment instantly.
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <button onclick="window.print()" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4H9a2 2 0 00-2 2v2a2 2 0 002 2h6a2 2 0 002-2v-2a2 2 0 00-2-2zm6-4a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Print
                        </button>
                        <button wire:click="generateVietQR" wire:loading.attr="disabled" class="flex-1 px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-900 dark:text-white rounded-lg font-medium transition-colors">
                            <span wire:loading.remove wire:target="generateVietQR">Refresh Code</span>
                            <span wire:loading wire:target="generateVietQR">Generating...</span>
                        </button>
                    </div>
                </div>
            @else
                <!-- Generate QR Code -->
                <div class="text-center py-6">
                    <p class="text-gray-600 dark:text-gray-300 mb-4">
                        Scan a QR code to pay instantly with VietQR
                    </p>
                    <button wire:click="generateVietQR" wire:loading.attr="disabled" wire:loading.class="opacity-50 cursor-not-allowed" class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition-colors inline-flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="generateVietQR">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Generate VietQR Payment Code
                        </span>
                        <span wire:loading wire:target="generateVietQR">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Generating...
                        </span>
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>

<!-- Print Styles -->
<style media="print">
    @media print {
        body > :not([id="print-vietqr"]) {
            display: none;
        }
        
        .no-print {
            display: none;
        }
        
        img[alt="VietQR Payment Code"] {
            max-width: 400px;
            height: auto;
            display: block;
            margin: 1cm auto;
        }
    }
</style>
