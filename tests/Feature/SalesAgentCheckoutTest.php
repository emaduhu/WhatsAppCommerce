<?php
namespace Tests\Feature;

use App\Jobs\SendWhatsAppText;
use App\Models\{Cart,Conversation,Customer,Merchant,Order,Payment,Product,WhatsappAccount};
use App\Services\WhatsApp\SalesAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SalesAgentCheckoutTest extends TestCase {
    use RefreshDatabase;

    public function test_customer_message_creates_cart_and_checkout_order(): void {
        Queue::fake();
        config(['app.key'=>'base64:'.base64_encode(str_repeat('a',32)),'services.payments.base_url'=>null]);

        $merchant=Merchant::create(['name'=>'Vigour Store','slug'=>'vigour-store','currency'=>'TZS']);
        $account=WhatsappAccount::create(['merchant_id'=>$merchant->id,'waba_id'=>'waba','phone_number_id'=>'phone-id','display_phone_number'=>'+255700000000','verified_name'=>'Vigour Store','access_token'=>str_repeat('x',60)]);
        $customer=Customer::create(['merchant_id'=>$merchant->id,'wa_id'=>'255712345678','phone'=>'255712345678','name'=>'Customer','last_inbound_at'=>now()]);
        $conversation=Conversation::create(['merchant_id'=>$merchant->id,'customer_id'=>$customer->id,'whatsapp_account_id'=>$account->id,'status'=>'OPEN','mode'=>'AI','last_message_at'=>now()]);
        $product=Product::create(['merchant_id'=>$merchant->id,'sku'=>'SAM-A55-BLU','name'=>'Samsung A55 Blue','description'=>'Samsung Galaxy A55 blue phone','type'=>'PRODUCT','price'=>900000,'stock_quantity'=>5,'track_stock'=>true,'is_active'=>true]);

        $agent=app(SalesAgent::class);
        $agent->handle($account,$customer,$conversation,'Nataka Samsung A55 blue mbili');

        $cart=Cart::with('items')->firstOrFail();
        $this->assertSame('AWAITING_DELIVERY',$cart->status);
        $this->assertSame(2,$cart->items->first()->quantity);
        $this->assertSame($product->id,$cart->items->first()->product_id);
        $this->assertEquals(1800000,(float)$cart->total);
        Queue::assertPushed(SendWhatsAppText::class,fn($job)=>str_contains($job->body,'Samsung A55 Blue') && str_contains($job->body,'delivery'));

        $agent->handle($account,$customer,$conversation,'Mikocheni, Dar es Salaam');
        $cart=$cart->fresh();
        $this->assertSame('AWAITING_PAYMENT',$cart->status);
        $this->assertSame('Mikocheni, Dar es Salaam',$cart->delivery_address);

        $agent->handle($account,$customer,$conversation,'255712345678');
        $cart=$cart->fresh();
        $order=Order::with('items')->firstOrFail();
        $payment=Payment::firstOrFail();

        $this->assertSame('PAYMENT_PENDING',$cart->status);
        $this->assertSame($order->id,$cart->order_id);
        $this->assertSame('PENDING_PAYMENT',$order->status);
        $this->assertEquals(1800000,(float)$order->total);
        $this->assertSame(2,$order->items->first()->quantity);
        $this->assertSame('INIT_FAILED',$payment->status);
        $this->assertSame(3,$product->fresh()->stock_quantity);
    }
}
