<?php

namespace App\Livewire\Settings;

use App\Models\PaymentGatewayCredential;
use App\Models\SuperadminPaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;

class RestaurantVietQRSettings extends Component
{
    use LivewireAlert;

    // Restaurant-specific VietQR properties
    public $useGlobalConfig = true; // Toggle between global and local config
    public $vietqrStatus = false;
    public $vietqrApiKey;
    public $vietqrBankAccount;
    public $vietqrBankCode;
    public $vietqrAccountName;
    public $vietqrWebhookSecret;

    // Global config display
    public $globalVietqrStatus = false;
    public $globalVietqrBankAccount;
    public $globalVietqrBankCode;
    public $globalVietqrAccountName;

    // Reference to credentials
    private $credentials;

    public function mount()
    {
        $restaurantId = auth()->user()->restaurant_id;
        
        // Load global config
        $this->loadGlobalConfig();
        
        // Load restaurant-specific credentials if they exist
        $this->loadRestaurantConfig($restaurantId);
    }

    private function loadGlobalConfig()
    {
        $superAdmin = SuperadminPaymentGateway::first();
        
        if ($superAdmin) {
            $this->globalVietqrStatus = (bool)($superAdmin->vietqr_status ?? false);
            $this->globalVietqrBankAccount = $superAdmin->vietqr_bank_account ?? null;
            $this->globalVietqrBankCode = $superAdmin->vietqr_bank_code ?? null;
            $this->globalVietqrAccountName = $superAdmin->vietqr_account_name ?? null;
        }
    }

    private function loadRestaurantConfig($restaurantId)
    {
        $creds = PaymentGatewayCredential::where('restaurant_id', $restaurantId)->first();
        
        if ($creds && $creds->vietqr_status) {
            // Restaurant has its own config
            $this->useGlobalConfig = false;
            $this->vietqrStatus = (bool)$creds->vietqr_status;
            $this->vietqrApiKey = $creds->vietqr_api_key;
            $this->vietqrBankAccount = $creds->vietqr_bank_account;
            $this->vietqrBankCode = $creds->vietqr_bank_code;
            $this->vietqrAccountName = $creds->vietqr_account_name;
            $this->vietqrWebhookSecret = $creds->vietqr_webhook_secret;
        } else {
            // Use global config
            $this->useGlobalConfig = true;
            $this->vietqrStatus = $this->globalVietqrStatus;
            $this->vietqrApiKey = null;
            $this->vietqrBankAccount = $this->globalVietqrBankAccount;
            $this->vietqrBankCode = $this->globalVietqrBankCode;
            $this->vietqrAccountName = $this->globalVietqrAccountName;
        }
        
        $this->credentials = $creds;
    }

    public function updatedUseGlobalConfig()
    {
        if ($this->useGlobalConfig) {
            // Switch to global config
            $this->vietqrStatus = $this->globalVietqrStatus;
            $this->vietqrApiKey = null;
            $this->vietqrBankAccount = $this->globalVietqrBankAccount;
            $this->vietqrBankCode = $this->globalVietqrBankCode;
            $this->vietqrAccountName = $this->globalVietqrAccountName;
            $this->vietqrWebhookSecret = null;
            
            // Delete restaurant-specific credentials
            $restaurantId = auth()->user()->restaurant_id;
            PaymentGatewayCredential::where('restaurant_id', $restaurantId)->delete();
        }
    }

    public function submitVietQRConfig()
    {
        if ($this->useGlobalConfig) {
            $this->alert('info', 'Using global VietQR configuration from Super Admin.', [
                'toast' => true,
                'position' => 'top-end',
            ]);
            return;
        }

        $this->validate([
            'vietqrApiKey' => Rule::requiredIf($this->vietqrStatus == true),
            'vietqrBankAccount' => Rule::requiredIf($this->vietqrStatus == true),
            'vietqrBankCode' => Rule::requiredIf($this->vietqrStatus == true),
            'vietqrAccountName' => Rule::requiredIf($this->vietqrStatus == true),
        ]);

        $configError = 0;
        $restaurantId = auth()->user()->restaurant_id;

        // Test VietQR connection if enabled
        if ($this->vietqrStatus) {
            try {
                // Temporarily update to test
                $this->getOrCreateCredentials($restaurantId)->update([
                    'vietqr_api_key' => $this->vietqrApiKey,
                    'vietqr_bank_account' => $this->vietqrBankAccount,
                    'vietqr_bank_code' => $this->vietqrBankCode,
                    'vietqr_account_name' => $this->vietqrAccountName,
                    'vietqr_webhook_secret' => $this->vietqrWebhookSecret,
                ]);

                $service = new \App\Services\VietQRService($restaurantId);
                $testResult = $service->testConnection();

                if (!$testResult['success']) {
                    $configError = 1;
                    $this->addError('vietqrApiKey', 'Failed to connect to VietQR API: ' . ($testResult['message'] ?? 'Unknown error'));
                }
            } catch (\Exception $e) {
                $configError = 1;
                $this->addError('vietqrApiKey', 'VietQR connection error: ' . $e->getMessage());
            }
        }

        // Save if no errors
        if ($configError == 0) {
            $creds = $this->getOrCreateCredentials($restaurantId);
            $creds->update([
                'vietqr_status' => $this->vietqrStatus,
                'vietqr_api_key' => $this->vietqrApiKey,
                'vietqr_bank_account' => $this->vietqrBankAccount,
                'vietqr_bank_code' => $this->vietqrBankCode,
                'vietqr_account_name' => $this->vietqrAccountName,
                'vietqr_webhook_secret' => $this->vietqrWebhookSecret,
            ]);

            $this->alert('success', 'VietQR configuration saved successfully!', [
                'toast' => true,
                'position' => 'top-end',
            ]);
        }
    }

    private function getOrCreateCredentials($restaurantId)
    {
        return PaymentGatewayCredential::firstOrCreate(
            ['restaurant_id' => $restaurantId],
            ['restaurant_id' => $restaurantId]
        );
    }

    public function render()
    {
        return view('livewire.settings.restaurant-vietqr-settings');
    }
}
