<?php
namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGatewayInterface {
    public function initiatePayment(Payment $payment): array;
    public function queryPayment(Payment $payment): array;
    public function verifyCallback(string $rawPayload,?string $signature): bool;
    public function refund(Payment $payment,float $amount,?string $reason=null): array;
}
