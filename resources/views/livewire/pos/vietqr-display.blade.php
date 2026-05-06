<div class="relative">
    @if ($order && $order->status !== 'paid')
        <!-- Compact QR Display for POS -->
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30 rounded-lg p-4 border border-blue-200 dark:border-blue-700">
            @if ($qrCodeImageUrl && $vietqrPayment && !$vietqrPayment->isExpired())
                <!-- QR Code Display -->
                <div class="flex flex-col items-center gap-2">
                    <div class="inline-block p-2 bg-white rounded-lg shadow-md">
                        <img src="{{ $qrCodeImageUrl }}" alt="VietQR Code" class="h-32 w-32 object-contain">
                    </div>
                    
                    <div class="text-center text-xs space-y-1">
                        <p class="font-bold text-gray-900 dark:text-white">{{ currency_format($amount, restaurant()->currency_id) }}</p>
                        <p class="text-gray-600 dark:text-gray-300">{{ $accountName }}</p>
                        <button wire:click="toggleFullScreen" class="text-blue-600 dark:text-blue-400 hover:underline text-xs font-medium">
                            Full Screen
                        </button>
                    </div>
                </div>
            @else
                <!-- Generate QR Button -->
                <div class="text-center py-3">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">VietQR Payment Available</p>
                    <button wire:click="generateAndDisplayQR" class="w-full px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded transition-colors">
                        Generate QR Code
                    </button>
                </div>
            @endif
        </div>

        <!-- Full Screen QR Modal -->
        <div x-data="{ fullScreen: @entangle('showFullScreenQR').live }" x-show="fullScreen" x-cloak class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 backdrop-blur-sm">
            <div class="relative bg-white dark:bg-gray-900 rounded-2xl p-8 max-w-2xl w-full mx-4 shadow-2xl">
                <!-- Close Button -->
                <button @click="fullScreen = false" class="absolute top-4 right-4 p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <div class="space-y-6">
                    <!-- Title -->
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">VietQR Payment</h3>
                        @if($order)
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Order {{ $order->show_formatted_order_number ?? $order->id }}</p>
                        @endif
                    </div>

                    @if ($qrCodeImageUrl && $vietqrPayment)
                        <!-- Large QR Code -->
                        <div class="flex justify-center">
                            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl">
                                <img src="{{ $qrCodeImageUrl }}" alt="VietQR Code" class="h-96 w-96 object-contain">
                            </div>
                        </div>

                        <!-- Payment Details -->
                        <div class="space-y-3 text-center">
                            <div class="text-4xl font-bold text-blue-600 dark:text-blue-400">
                                {{ currency_format($amount, restaurant()->currency_id) }}
                            </div>
                            
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <p><strong>Account:</strong> {{ $accountName }}</p>
                                <p><strong>Account Number:</strong> {{ $bankAccount }}</p>
                                <p><strong>Bank:</strong> {{ $bankCode }}</p>
                            </div>

                            <!-- Print Instructions -->
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-3 text-xs">
                                <p class="font-medium text-blue-900 dark:text-blue-100">Scan the QR code with your banking app to pay</p>
                            </div>
                        </div>

                        <!-- Print Button -->
                        <div class="flex gap-3">
                            <button onclick="window.print()" class="flex-1 px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4H9a2 2 0 00-2 2v2a2 2 0 002 2h6a2 2 0 002-2v-2a2 2 0 00-2-2zm6-4a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                Print QR
                            </button>
                            <button @click="fullScreen = false" class="flex-1 px-4 py-3 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-900 dark:text-white rounded-lg font-medium transition-colors">
                                Close
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Print Styles -->
<style media="print">
    @media print {
        body * {
            display: none;
        }
        
        [x-show="fullScreen"] * {
            display: block;
        }
        
        img[alt="VietQR Code"] {
            display: block;
            margin: 2cm auto;
            max-width: 100%;
        }
    }
</style>
