<?php
namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GenericPaymentGateway implements PaymentGatewayInterface {
    public function initiate(Payment $payment): array { return $this->initiatePayment($payment); }
    public function signatureValid(string $raw,?string $signature): bool { return $this->verifyCallback($raw,$signature); }

    public function initiatePayment(Payment $payment): array {
        $base=rtrim((string)config('services.payments.base_url'),'/' );
        if(!$base) throw new RuntimeException('PAYMENT_BASE_URL is not configured.');
        $payload=[
            'reference'=>(string)$payment->id,
            'amount'=>(float)$payment->amount,
            'currency'=>$payment->currency,
            'msisdn'=>$payment->msisdn ?: $payment->phone_number,
            'callback_url'=>config('services.payments.callback_url'),
        ];
        $r=Http::baseUrl($base)->withToken(config('services.payments.api_key'))->acceptJson()->asJson()->timeout(20)->retry(3,500,throw:false)->post('/payments',$payload);
        if($r->failed()) throw new RuntimeException('Payment provider error '.$r->status().': '.$r->body());
        $json=$r->json() ?: [];
        $providerReference=data_get($json,'id') ?? data_get($json,'reference');
        $payment->update([
            'provider_reference'=>$providerReference,
            'provider_transaction_id'=>$providerReference,
            'reference'=>(string)$payment->id,
            'phone_number'=>$payment->phone_number ?: $payment->msisdn,
            'raw_request'=>$payload,
            'raw_response'=>$json,
            'provider_response'=>$json,
        ]);
        return $json;
    }

    public function queryPayment(Payment $payment): array {
        $base=rtrim((string)config('services.payments.base_url'),'/' );
        if(!$base) throw new RuntimeException('PAYMENT_BASE_URL is not configured.');
        $reference=$payment->provider_reference ?: $payment->id;
        $r=Http::baseUrl($base)->withToken(config('services.payments.api_key'))->acceptJson()->timeout(20)->retry(2,500,throw:false)->get('/payments/'.$reference);
        if($r->failed()) throw new RuntimeException('Payment provider query error '.$r->status().': '.$r->body());
        return $r->json() ?: [];
    }

    public function verifyCallback(string $rawPayload,?string $signature): bool {
        $secret=(string)config('services.payments.webhook_secret');
        return $secret!=='' && $signature && hash_equals(hash_hmac('sha256',$rawPayload,$secret),$signature);
    }

    public function refund(Payment $payment,float $amount,?string $reason=null): array {
        $base=rtrim((string)config('services.payments.base_url'),'/' );
        if(!$base) throw new RuntimeException('PAYMENT_BASE_URL is not configured.');
        $r=Http::baseUrl($base)->withToken(config('services.payments.api_key'))->acceptJson()->asJson()->timeout(20)->post('/payments/'.($payment->provider_reference ?: $payment->id).'/refunds',[
            'amount'=>$amount,
            'currency'=>$payment->currency,
            'reason'=>$reason,
        ]);
        if($r->failed()) throw new RuntimeException('Payment refund error '.$r->status().': '.$r->body());
        return $r->json() ?: [];
    }
}
