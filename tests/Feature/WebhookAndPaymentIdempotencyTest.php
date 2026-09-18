<?php
namespace Tests\Feature;

use App\Models\{Conversation,Customer,Merchant,Order,Payment,PaymentTransaction,WebhookEvent,WhatsappAccount};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookAndPaymentIdempotencyTest extends TestCase {
    use RefreshDatabase;

    public function test_whatsapp_webhook_stores_event_idempotently_before_processing(): void {
        Queue::fake();
        config([
            'app.key'=>'base64:'.base64_encode(str_repeat('c',32)),
            'services.whatsapp.app_secret'=>'secret',
        ]);
        $merchant=Merchant::create(['name'=>'Demo','slug'=>'demo']);
        WhatsappAccount::create(['merchant_id'=>$merchant->id,'waba_id'=>'waba','phone_number_id'=>'phone-1','access_token'=>str_repeat('x',60)]);
        $payload=[
            'object'=>'whatsapp_business_account',
            'entry'=>[[
                'changes'=>[[
                    'value'=>[
                        'metadata'=>['phone_number_id'=>'phone-1'],
                        'contacts'=>[['profile'=>['name'=>'Customer']]],
                        'messages'=>[['from'=>'255700000001','id'=>'wamid.test.1','type'=>'text','text'=>['body'=>'Hello']]],
                    ],
                ]],
            ]],
        ];
        $raw=json_encode($payload);
        $signature='sha256='.hash_hmac('sha256',$raw,'secret');

        $this->postJson('/api/webhooks/whatsapp',$payload,['X-Hub-Signature-256'=>$signature])->assertOk();
        $this->postJson('/api/webhooks/whatsapp',$payload,['X-Hub-Signature-256'=>$signature])->assertOk();

        $this->assertSame(1,WebhookEvent::where('event_key','message:wamid.test.1')->count());
    }

    public function test_payment_callback_is_verified_and_idempotent(): void {
        Queue::fake();
        config(['services.payments.webhook_secret'=>'pay-secret','services.payments.provider'=>'generic']);
        $merchant=Merchant::create(['name'=>'Demo','slug'=>'demo','currency'=>'TZS']);
        $customer=Customer::create(['merchant_id'=>$merchant->id,'phone'=>'255700000001','last_inbound_at'=>now()]);
        $account=WhatsappAccount::create(['merchant_id'=>$merchant->id,'waba_id'=>'waba','phone_number_id'=>'phone-1','access_token'=>str_repeat('x',60)]);
        Conversation::create(['merchant_id'=>$merchant->id,'customer_id'=>$customer->id,'whatsapp_account_id'=>$account->id,'status'=>'OPEN','mode'=>'AI','last_message_at'=>now()]);
        $order=Order::create(['merchant_id'=>$merchant->id,'customer_id'=>$customer->id,'number'=>'ORD-1','order_number'=>'ORD-1','status'=>'PENDING_PAYMENT','payment_status'=>'UNPAID','currency'=>'TZS','total'=>1000]);
        Payment::create(['merchant_id'=>$merchant->id,'order_id'=>$order->id,'provider'=>'generic','reference'=>'local-ref','provider_reference'=>'provider-ref','status'=>'PENDING','amount'=>1000,'currency'=>'TZS','msisdn'=>'255700000001']);

        $payload=['id'=>'provider-ref','status'=>'PAID','amount'=>1000,'currency'=>'TZS'];
        $raw=json_encode($payload);
        $signature=hash_hmac('sha256',$raw,'pay-secret');

        $this->postJson('/api/webhooks/payments/generic',$payload,['X-Signature'=>$signature])->assertOk();
        $this->postJson('/api/webhooks/payments/generic',$payload,['X-Signature'=>$signature])->assertOk()->assertJsonPath('duplicate',true);

        $this->assertSame('PAID',Payment::firstOrFail()->status);
        $this->assertSame('PAID',$order->fresh()->payment_status);
        $this->assertSame('CONFIRMED',$order->fresh()->status);
        $this->assertSame(1,PaymentTransaction::count());
    }
}
