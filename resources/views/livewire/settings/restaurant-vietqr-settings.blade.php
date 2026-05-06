<div>
    <div class="p-4 mx-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 sm:p-6 dark:bg-gray-800">
        <h3 class="mb-4 text-xl font-semibold dark:text-white">VietQR Payment Configuration</h3>
        <x-help-text class="mb-6">Configure VietQR payment settings for your restaurant. You can either use the global configuration from Super Admin or set up your own restaurant-specific configuration.</x-help-text>

        <form wire:submit="submitVietQRConfig">
            <div class="grid gap-6">

                <!-- Global vs Local Toggle -->
                <div class="p-4 border border-gray-200 rounded-lg dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                    <div class="flex items-center justify-between mb-4">
                        <label class="text-sm font-medium text-gray-900 dark:text-white">Configuration Source</label>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer" :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-900': useGlobalConfig, 'border-gray-200 dark:border-gray-700': !useGlobalConfig }">
                            <input type="radio" wire:model="useGlobalConfig" :value="true" class="w-4 h-4">
                            <div class="ml-3">
                                <p class="font-medium text-gray-900 dark:text-white">Use Global Config</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Use Super Admin configuration</p>
                            </div>
                        </label>

                        <label class="flex items-center p-3 border-2 rounded-lg cursor-pointer" :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-900': !useGlobalConfig, 'border-gray-200 dark:border-gray-700': useGlobalConfig }">
                            <input type="radio" wire:model="useGlobalConfig" :value="false" class="w-4 h-4">
                            <div class="ml-3">
                                <p class="font-medium text-gray-900 dark:text-white">Custom Config</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Use restaurant-specific config</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Global Config Display -->
                @if ($useGlobalConfig)
                    <div class="p-4 border border-green-200 rounded-lg bg-green-50 dark:bg-green-900 dark:border-green-700">
                        <h4 class="mb-3 font-medium text-green-900 dark:text-green-100">Current Global Configuration</h4>
                        @if ($globalVietqrStatus)
                            <div class="space-y-2 text-sm text-green-800 dark:text-green-200">
                                <p><strong>Status:</strong> <span class="inline-flex items-center gap-2"><span class="w-2 h-2 bg-green-500 rounded-full"></span>Enabled</span></p>
                                <p><strong>Bank Account:</strong> {{ $globalVietqrBankAccount }}</p>
                                <p><strong>Bank Code:</strong> {{ $globalVietqrBankCode }}</p>
                                <p><strong>Account Name:</strong> {{ $globalVietqrAccountName }}</p>
                            </div>
                        @else
                            <p class="text-sm text-green-800 dark:text-green-200">VietQR is not enabled in global configuration. Contact Super Admin to enable it.</p>
                        @endif
                    </div>
                @else
                    <!-- Custom Configuration Form -->
                    <div class="my-3">
                        <x-label for="vietqrStatus">
                            <div class="flex items-center cursor-pointer">
                                <x-checkbox name="vietqrStatus" id="vietqrStatus" wire:model.live='vietqrStatus'/>
                                <div class="ms-2">
                                    Enable Custom VietQR Configuration
                                </div>
                            </div>
                        </x-label>
                    </div>

                    @if ($vietqrStatus)
                        <div>
                            <x-label for="vietqrApiKey" :value="__('VietQR API Key')" required/>
                            <x-input id="vietqrApiKey" class="block w-full mt-1" type="password" wire:model='vietqrApiKey' placeholder="Enter your VietQR API Key"/>
                            <x-input-error for="vietqrApiKey" class="mt-2"/>
                        </div>

                        <div>
                            <x-label for="vietqrBankAccount" :value="__('Bank Account Number')" required/>
                            <x-input id="vietqrBankAccount" class="block w-full mt-1" type="text" wire:model='vietqrBankAccount' placeholder="e.g., 1234567890"/>
                            <x-input-error for="vietqrBankAccount" class="mt-2"/>
                        </div>

                        <div>
                            <x-label for="vietqrBankCode" :value="__('Bank Code')" required/>
                            <x-select id="vietqrBankCode" class="block w-full mt-1" wire:model='vietqrBankCode'>
                                <option value="">--Select Bank--</option>
                                <option value="BIDV">BIDV</option>
                                <option value="VCB">Vietcombank (VCB)</option>
                                <option value="VIB">VIB</option>
                                <option value="MB">MB Bank</option>
                                <option value="TCB">TechcomBank</option>
                                <option value="ACB">ACB</option>
                                <option value="EXB">Exim Bank</option>
                                <option value="MSB">MSB</option>
                                <option value="TPB">TPBank</option>
                                <option value="OJB">OJBank</option>
                                <option value="SACOMBANK">SACOMBANK</option>
                                <option value="SHB">SHB</option>
                                <option value="VPB">VPBank</option>
                                <option value="AGRIBANK">Agribank</option>
                                <option value="NABANK">NABANK</option>
                                <option value="OceanBank">OceanBank</option>
                            </x-select>
                            <x-input-error for="vietqrBankCode" class="mt-2"/>
                        </div>

                        <div>
                            <x-label for="vietqrAccountName" :value="__('Account Holder Name')" required/>
                            <x-input id="vietqrAccountName" class="block w-full mt-1" type="text" wire:model='vietqrAccountName' placeholder="Enter account holder name"/>
                            <x-input-error for="vietqrAccountName" class="mt-2"/>
                        </div>

                        <div>
                            <x-label for="vietqrWebhookSecret" :value="__('Webhook Secret Key')"/>
                            <x-input id="vietqrWebhookSecret" class="block w-full mt-1" type="password" wire:model='vietqrWebhookSecret' placeholder="Enter webhook secret key for verification"/>
                            <x-input-error for="vietqrWebhookSecret" class="mt-2"/>
                        </div>
                    @endif
                @endif

                <div>
                    <x-button>@lang('app.save')</x-button>
                </div>
            </div>
        </form>
    </div>
</div>
