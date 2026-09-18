<?php
namespace App\Services\WhatsApp;

use App\Models\Customer;

class WhatsAppMessagingPolicyService {
    public function canSendFreeform(Customer $customer): bool {
        return $customer->last_inbound_at !== null && $customer->last_inbound_at->gte(now()->subHours(24));
    }

    public function requiresTemplate(Customer $customer): bool {
        return !$this->canSendFreeform($customer);
    }

    public function selectTemplate(string $purpose,string $language='en_US'): ?string {
        return match($purpose){
            'payment_confirmation'=>'payment_confirmation',
            'order_confirmation'=>'order_confirmation',
            'order_ready'=>'order_ready',
            'abandoned_cart'=>'abandoned_cart',
            default=>null,
        };
    }
}
