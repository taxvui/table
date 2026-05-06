<div class="space-y-4">
    <!-- VietQR Payment Section -->
    @if ($order && $order->status !== 'paid')
        <div class="p-4 border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900 dark:border-blue-700">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M3 4.5h18v2H3V4.5zm0 6h18v2H3v-2zm0 6h18v2H3v-2z"/>
                    </svg>
                    <h3 class="font-semibold text-gray-900 dark:text-white">VietQR Payment</h3>
                </div>
            </div>

            @if ($vietqrPayment && $qrCodeImageUrl)
                <!-- QR Code Display -->
                <div class="flex flex-col items-center gap-3 py-4">
                    <img src="{{ $qrCodeImageUrl }}" alt="VietQR Code" class="h-48 w-48 object-contain rounded-lg shadow border border-gray-200 dark:border-gray-600 bg-white p-2">
                    <div class="text-center text-sm text-gray-600 dark:text-gray-300">
                        <p class="font-medium">{{ $accountName }}</p>
                        <p>{{ $bankAccount }} - {{ $bankCode }}</p>
                        <p class="text-lg font-semibold mt-1">{{ currency_format($amount, restaurant()->currency_id) }}</p>
                        @if ($expiresAt)
                            <p class="text-xs text-gray-500 dark:text-gray-400">Expires: {{ $expiresAt }}</p>
                        @endif
                    </div>
                </div>

                <div class="flex gap-2 mt-4">
                    <x-button wire:click="openQRModal" class="flex-1">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        View Full Screen
                    </x-button>
                    <x-button wire:click="generateVietQRCode" type="secondary" class="flex-1">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Regenerate
                    </x-button>
                </div>
            @else
                <!-- Generate QR Code -->
                <div class="py-4 text-center">
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">Click below to generate a VietQR code for this order</p>
                    <x-button wire:click="generateVietQRCode" class="w-full">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Generate VietQR Payment Code
                    </x-button>
                </div>
            @endif
        </div>
    @endif

    <!-- Full Screen QR Modal -->
    <x-modal wire:model.live="showQrModal" maxWidth="md">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 dark:text-white">VietQR Payment Code</h3>
            
            @if ($qrCodeImageUrl)
                <div class="flex flex-col items-center gap-4">
                    <!-- Large QR Code -->
                    <img src="{{ $qrCodeImageUrl }}" alt="VietQR Code" class="h-96 w-96 object-contain rounded-lg shadow-lg border-4 border-gray-200 dark:border-gray-600 bg-white p-4">
                    
                    <!-- Payment Details -->
                    <div class="w-full bg-gray-50 dark:bg-gray-800 rounded-lg p-4 space-y-2 text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            <strong>Account:</strong> {{ $accountName }}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            <strong>Bank Account:</strong> {{ $bankAccount }}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            <strong>Bank Code:</strong> {{ $bankCode }}
                        </p>
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-3 mt-3">
                            <p class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                {{ currency_format($amount, restaurant()->currency_id) }}
                            </p>
                            @if ($expiresAt)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Expires: {{ $expiresAt }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <!-- Print Button -->
                    <button onclick="window.print()" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4H9a2 2 0 00-2 2v2a2 2 0 002 2h6a2 2 0 002-2v-2a2 2 0 00-2-2zm6-4a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Print QR Code
                    </button>
                </div>
            @endif
        </div>
    </x-modal>
</div>
