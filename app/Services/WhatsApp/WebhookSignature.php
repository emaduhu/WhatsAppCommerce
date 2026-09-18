<?php
namespace App\Services\WhatsApp;
class WebhookSignature {
    public function valid(string $body,?string $header): bool {
        if(!config('services.whatsapp.enforce_signature')) return true;
        $secret=(string)config('services.whatsapp.app_secret');
        if($secret===''||!$header||!str_starts_with($header,'sha256=')) return false;
        return hash_equals('sha256='.hash_hmac('sha256',$body,$secret),$header);
    }
}
