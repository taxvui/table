<div class="space-y-4">
    <div class="p-4 mx-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 sm:p-6 dark:bg-gray-800">
        <h3 class="mb-4 text-xl font-semibold dark:text-white">VietQR Payment Management</h3>
        <x-help-text class="mb-6">Monitor and manage all VietQR payments for your restaurant orders.</x-help-text>

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div>
                <x-label for="search" :value="__('Search')" />
                <x-input id="search" type="text" wire:model.live="search" placeholder="Order ID or Transaction ID" class="w-full" />
            </div>
            
            <div>
                <x-label for="status" :value="__('Payment Status')" />
                <x-select id="status" wire:model.live="status" class="w-full">
                    <option value="">-- All Statuses --</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="failed">Failed</option>
                    <option value="cancelled">Cancelled</option>
                </x-select>
            </div>

            <div>
                <x-label for="perPage" :value="__('Per Page')" />
                <x-select id="perPage" wire:model.live="perPage" class="w-full">
                    <option value="10">10</option>
                    <option value="15" selected>15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </x-select>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs font-semibold text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-200">
                    <tr>
                        <th class="px-6 py-3 cursor-pointer" wire:click="sort('qr_code_id')">
                            QR Code ID
                            @if($sortBy === 'qr_code_id')
                                <svg class="w-4 h-4 inline" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="{{ $sortDirection === 'asc' ? 'M3 3a1 1 0 000 2h11a1 1 0 100-2H3zm0 4a1 1 0 000 2h5a1 1 0 000-2H3zm0 4a1 1 0 000 2h4a1 1 0 000-2H3z' : 'M17 3a1 1 0 000 2H6v11a1 1 0 100 2h11V5h2a1 1 0 000-2h-2z' }}" />
                                </svg>
                            @endif
                        </th>
                        <th class="px-6 py-3 cursor-pointer" wire:click="sort('order_id')">Order</th>
                        <th class="px-6 py-3 cursor-pointer" wire:click="sort('amount')">Amount</th>
                        <th class="px-6 py-3 cursor-pointer" wire:click="sort('payment_status')">Status</th>
                        <th class="px-6 py-3 cursor-pointer" wire:click="sort('qr_generated_at')">Generated</th>
                        <th class="px-6 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($payments as $payment)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 font-mono text-xs">{{ substr($payment->qr_code_id, 0, 12) }}...</td>
                            <td class="px-6 py-4">
                                @if($payment->order)
                                    <a href="#" class="text-blue-600 hover:underline">{{ $payment->order->show_formatted_order_number ?? $payment->order->id }}</a>
                                @else
                                    <span class="text-gray-400">--</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 font-semibold">{{ currency_format($payment->amount, restaurant()->currency_id) }}</td>
                            <td class="px-6 py-4">
                                <span @class([
                                    'px-3 py-1 rounded-full text-xs font-medium',
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' => $payment->payment_status === 'pending',
                                    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $payment->payment_status === 'confirmed',
                                    'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' => $payment->payment_status === 'failed',
                                    'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' => $payment->payment_status === 'cancelled',
                                ])>
                                    {{ ucfirst($payment->payment_status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs">{{ $payment->qr_generated_at?->format('M d, H:i') }}</td>
                            <td class="px-6 py-4">
                                <div class="flex gap-2">
                                    <button wire:click="viewDetails({{ $payment->id }})" class="text-blue-600 hover:text-blue-800 dark:hover:text-blue-400 text-sm">
                                        View
                                    </button>
                                    @if($payment->payment_status === 'pending' || $payment->isExpired())
                                        <button wire:click="retryPayment({{ $payment->id }})" class="text-green-600 hover:text-green-800 dark:hover:text-green-400 text-sm">
                                            Retry
                                        </button>
                                    @endif
                                    @if($payment->payment_status !== 'confirmed')
                                        <button wire:click="cancelPayment({{ $payment->id }})" class="text-red-600 hover:text-red-800 dark:hover:text-red-400 text-sm">
                                            Cancel
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No VietQR payments found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($payments->hasPages())
            <div class="mt-6">
                {{ $payments->links() }}
            </div>
        @endif
    </div>

    <!-- Payment Details Modal -->
    @if($showDetails && $selectedPayment)
        <x-dialog-modal wire:model.live="showDetails" maxWidth="md">
            <x-slot name="title">Payment Details</x-slot>
            <x-slot name="content">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">QR Code ID</p>
                            <p class="font-mono">{{ substr($selectedPayment->qr_code_id, 0, 20) }}...</p>
                        </div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Transaction ID</p>
                            <p class="font-mono">{{ $selectedPayment->transaction_id ?? '--' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Amount</p>
                            <p class="text-lg font-semibold">{{ currency_format($selectedPayment->amount, restaurant()->currency_id) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Status</p>
                            <span @class([
                                'px-2 py-1 rounded text-xs font-medium inline-block',
                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' => $selectedPayment->payment_status === 'pending',
                                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $selectedPayment->payment_status === 'confirmed',
                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' => $selectedPayment->payment_status === 'failed',
                            ])>
                                {{ ucfirst($selectedPayment->payment_status) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Bank Account</p>
                            <p>{{ $selectedPayment->bank_account }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Bank Code</p>
                            <p>{{ $selectedPayment->bank_code }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Generated At</p>
                            <p>{{ $selectedPayment->qr_generated_at?->format('M d, Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Expires At</p>
                            <p>{{ $selectedPayment->expires_at?->format('M d, Y H:i') }}</p>
                        </div>
                    </div>

                    @if($selectedPayment->payment_confirmed_at)
                        <div class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded">
                            <p class="text-sm text-green-800 dark:text-green-200">
                                <strong>Confirmed at:</strong> {{ $selectedPayment->payment_confirmed_at?->format('M d, Y H:i') }}
                            </p>
                        </div>
                    @endif

                    @if($selectedPayment->qr_code_image_url)
                        <div class="flex justify-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <img src="{{ $selectedPayment->qr_code_image_url }}" alt="QR Code" class="h-48 w-48">
                        </div>
                    @endif
                </div>
            </x-slot>
            <x-slot name="footer">
                <x-button-cancel wire:click="closeDetails">Close</x-button-cancel>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
