<?php
namespace App\Services\WhatsApp;

use App\Models\{Conversation,Customer,Message,WhatsappAccount};
use Illuminate\Support\Facades\DB;

class InboundProcessor {
    public function __construct(private SalesAgent $salesAgent) {}

    public function process(array $payload): void {
        foreach(data_get($payload,'entry',[]) as $entry){
            foreach(data_get($entry,'changes',[]) as $change){
                $field=(string)($change['field'] ?? 'messages');
                if(in_array($field,['message_echoes','smb_message_echoes','history','smb_app_state_sync'],true)) continue;
                $value=$change['value'] ?? [];
                $phoneId=data_get($value,'metadata.phone_number_id');
                $account=WhatsappAccount::where('phone_number_id',$phoneId)->first();
                if(!$account) continue;

                $account->update(['last_webhook_at'=>now()]);
                foreach($value['messages'] ?? [] as $message){
                    $this->message($account,$message,$value['contacts'][0] ?? null);
                }
                foreach($value['statuses'] ?? [] as $status){
                    $this->status($status);
                }
            }
        }
    }

    private function message(WhatsappAccount $account,array $message,?array $contact): void {
        $wamid=(string)($message['id'] ?? '');
        if($wamid!=='' && Message::where('wamid',$wamid)->exists()) return;

        $handled=DB::transaction(function() use($account,$message,$contact,$wamid){
            $from=(string)$message['from'];
            $customer=Customer::updateOrCreate(
                ['merchant_id'=>$account->merchant_id,'phone'=>$from],
                [
                    'wa_id'=>$from,
                    'whatsapp_number'=>$from,
                    'name'=>data_get($contact,'profile.name'),
                    'last_inbound_at'=>now(),
                ]
            );
            $conversation=Conversation::firstOrCreate(
                ['merchant_id'=>$account->merchant_id,'customer_id'=>$customer->id,'whatsapp_account_id'=>$account->id,'status'=>'OPEN'],
                ['mode'=>'AI','current_state'=>'NEW','last_message_at'=>now()]
            );
            $body=$this->extractBody($message);
            Message::create([
                'merchant_id'=>$account->merchant_id,
                'conversation_id'=>$conversation->id,
                'customer_id'=>$customer->id,
                'whatsapp_account_id'=>$account->id,
                'whatsapp_message_id'=>$wamid ?: null,
                'wamid'=>$wamid ?: null,
                'direction'=>'INBOUND',
                'type'=>strtoupper((string)($message['type'] ?? 'OTHER')),
                'status'=>'RECEIVED',
                'body'=>$body,
                'payload'=>$message,
            ]);
            $conversation->update(['last_message_at'=>now()]);
            return ['customer'=>$customer,'conversation'=>$conversation->fresh(),'body'=>$body];
        });

        if(($handled['conversation']->mode ?? null)==='AI' && $handled['body']){
            $this->salesAgent->handle($account,$handled['customer'],$handled['conversation'],$handled['body']);
        }
    }

    private function extractBody(array $message): ?string {
        return match($message['type'] ?? null){
            'text'=>data_get($message,'text.body'),
            'button'=>data_get($message,'button.text'),
            'interactive'=>data_get($message,'interactive.button_reply.title') ?? data_get($message,'interactive.list_reply.title'),
            'location'=>trim((string)data_get($message,'location.name').' '.(string)data_get($message,'location.address')) ?: null,
            default=>null,
        };
    }

    private function status(array $statusPayload): void {
        $message=Message::where('wamid',$statusPayload['id'] ?? null)->orWhere('whatsapp_message_id',$statusPayload['id'] ?? null)->first();
        if(!$message) return;
        $status=strtoupper((string)($statusPayload['status'] ?? ''));
        $data=['status'=>$status];
        if($status==='SENT') $data['sent_at']=now();
        if($status==='DELIVERED') $data['delivered_at']=now();
        if($status==='READ') $data['read_at']=now();
        if($status==='FAILED'){
            $data['failed_at']=now();
            $data['error_code']=(string)data_get($statusPayload,'errors.0.code');
            $data['error_message']=data_get($statusPayload,'errors.0.title') ?? data_get($statusPayload,'errors.0.message');
        }
        $message->update($data);
    }
}
