<?php
namespace App\Jobs;

use App\Models\{Conversation,Customer,Message,WhatsappAccount};
use App\Services\WhatsApp\{WhatsAppCloudClient,WhatsAppMessagingPolicyService};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use RuntimeException;

class SendWhatsAppText implements ShouldQueue {
    use Queueable;
    public int $tries=8;
    public array $backoff=[2,5,10,30,60];

    public function __construct(public int $accountId,public int $customerId,public string $body){}

    public function middleware(): array {
        return [(new ThrottlesExceptions(5,60))->backoff(5)->by('whatsapp-api')];
    }

    public function handle(WhatsAppCloudClient $client,WhatsAppMessagingPolicyService $policy): void {
        $account=WhatsappAccount::findOrFail($this->accountId);
        $customer=Customer::findOrFail($this->customerId);
        if($policy->requiresTemplate($customer)){
            throw new RuntimeException('Free-form message blocked: customer service window is closed; use an approved template.');
        }

        $response=$client->sendText($account,$customer->phone,$this->body);
        $conversation=Conversation::where('merchant_id',$account->merchant_id)
            ->where('customer_id',$customer->id)
            ->where('whatsapp_account_id',$account->id)
            ->where('status','OPEN')
            ->latest('last_message_at')
            ->firstOrFail();
        $wamid=data_get($response,'messages.0.id');
        Message::create([
            'merchant_id'=>$account->merchant_id,
            'conversation_id'=>$conversation->id,
            'customer_id'=>$customer->id,
            'whatsapp_account_id'=>$account->id,
            'whatsapp_message_id'=>$wamid,
            'wamid'=>$wamid,
            'direction'=>'OUTBOUND',
            'type'=>'TEXT',
            'status'=>'SENT',
            'body'=>$this->body,
            'payload'=>$response,
            'sent_at'=>now(),
        ]);
        $conversation->update(['last_message_at'=>now()]);
        $customer->update(['last_outbound_at'=>now()]);
    }
}
