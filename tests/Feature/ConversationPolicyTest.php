<?php
namespace Tests\Feature;

use App\Jobs\SendWhatsAppText;
use App\Models\{Conversation,Customer,Merchant,WhatsappAccount};
use App\Services\WhatsApp\{SalesAgent,WhatsAppMessagingPolicyService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConversationPolicyTest extends TestCase {
    use RefreshDatabase;

    public function test_human_handoff_sets_conversation_mode_to_human(): void {
        Queue::fake();
        config(['app.key'=>'base64:'.base64_encode(str_repeat('d',32))]);
        $merchant=Merchant::create(['name'=>'Demo','slug'=>'demo']);
        $account=WhatsappAccount::create(['merchant_id'=>$merchant->id,'waba_id'=>'waba','phone_number_id'=>'phone-1','access_token'=>str_repeat('x',60)]);
        $customer=Customer::create(['merchant_id'=>$merchant->id,'phone'=>'255700000001','last_inbound_at'=>now()]);
        $conversation=Conversation::create(['merchant_id'=>$merchant->id,'customer_id'=>$customer->id,'whatsapp_account_id'=>$account->id,'status'=>'OPEN','mode'=>'AI']);

        app(SalesAgent::class)->handle($account,$customer,$conversation,'nataka kuongea na mtu');

        $this->assertSame('HUMAN',$conversation->fresh()->mode);
        $this->assertSame('HUMAN_REQUIRED',$conversation->fresh()->current_state);
        Queue::assertPushed(SendWhatsAppText::class);
    }

    public function test_messaging_policy_blocks_freeform_after_twenty_four_hours(): void {
        $policy=app(WhatsAppMessagingPolicyService::class);
        $old=Customer::create(['merchant_id'=>Merchant::create(['name'=>'Demo','slug'=>'demo'])->id,'phone'=>'255700000001','last_inbound_at'=>now()->subHours(25)]);
        $recent=Customer::create(['merchant_id'=>$old->merchant_id,'phone'=>'255700000002','last_inbound_at'=>now()->subMinutes(10)]);

        $this->assertFalse($policy->canSendFreeform($old));
        $this->assertTrue($policy->requiresTemplate($old));
        $this->assertTrue($policy->canSendFreeform($recent));
    }
}
