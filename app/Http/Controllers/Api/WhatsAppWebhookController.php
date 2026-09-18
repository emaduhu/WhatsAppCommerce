<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\{WebhookEvent,WhatsappAccount};
use App\Services\WhatsApp\WebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller {
    public function verify(Request $request){
        $mode=$request->query('hub_mode') ?? $request->query('hub.mode');
        $token=$request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge=$request->query('hub_challenge') ?? $request->query('hub.challenge');
        if($mode==='subscribe' && hash_equals((string)config('services.whatsapp.verify_token'),(string)$token)){
            $this->webhookLog('whatsapp.webhook.verify.ok',['challenge_length'=>strlen((string)$challenge)]);
            return response($challenge,200)->header('Content-Type','text/plain');
        }
        $this->webhookLog('whatsapp.webhook.verify.failed',['mode'=>$mode,'has_token'=>$token!==null,'has_challenge'=>$challenge!==null],'warning');
        return response('Forbidden',403);
    }

    public function receive(Request $request,WebhookSignature $signature){
        $raw=$request->getContent();
        if(!$signature->valid($raw,$request->header('X-Hub-Signature-256'))){
            $this->webhookLog('whatsapp.webhook.signature.invalid',[
                'bytes'=>strlen($raw),
                'signature_present'=>$request->hasHeader('X-Hub-Signature-256'),
            ],'warning');
            return response()->json(['message'=>'Invalid signature'],401);
        }

        $payload=$request->json()->all();
        $this->webhookLog('whatsapp.webhook.received',$this->logSummary($payload,$raw));
        $created=[];
        foreach($this->eventKeys($payload,$raw) as $key){
            $event=WebhookEvent::firstOrCreate(
                ['provider'=>'whatsapp','event_key'=>$key['event_key']],
                [
                    'merchant_id'=>$key['merchant_id'],
                    'event_type'=>$key['event_type'],
                    'status'=>'RECEIVED',
                    'payload'=>$payload,
                ]
            );
            if($event->wasRecentlyCreated || !$event->processed_at){
                ProcessWhatsAppWebhook::dispatch($event->id);
            }
            $created[]=$event->id;
        }

        $this->webhookLog('whatsapp.webhook.persisted',['event_ids'=>$created]);
        return response()->json(['received'=>true,'events'=>$created]);
    }

    private function webhookLog(string $event,array $context=[],string $level='info'): void {
        Log::log($level,$event,$context);
        $line=json_encode([
            'timestamp'=>now()->toIso8601String(),
            'level'=>$level,
            'event'=>$event,
            'context'=>$context,
        ],JSON_UNESCAPED_SLASHES).PHP_EOL;
        @file_put_contents(storage_path('logs/whatsapp-webhook.log'),$line,FILE_APPEND | LOCK_EX);
    }

    private function logSummary(array $payload,string $raw): array {
        $summary=[
            'bytes'=>strlen($raw),
            'object'=>$payload['object'] ?? null,
            'entry_count'=>count(data_get($payload,'entry',[])),
            'fields'=>[],
            'phone_number_ids'=>[],
            'message_ids'=>[],
            'message_texts'=>[],
            'statuses'=>[],
        ];
        foreach(data_get($payload,'entry',[]) as $entry){
            foreach(data_get($entry,'changes',[]) as $change){
                $summary['fields'][]=$change['field'] ?? null;
                $value=$change['value'] ?? [];
                if(data_get($value,'metadata.phone_number_id')) $summary['phone_number_ids'][]=data_get($value,'metadata.phone_number_id');
                foreach($value['messages'] ?? [] as $message){
                    if($message['id'] ?? null) $summary['message_ids'][]=$message['id'];
                    $body=data_get($message,'text.body')
                        ?? data_get($message,'button.text')
                        ?? data_get($message,'interactive.button_reply.title')
                        ?? data_get($message,'interactive.list_reply.title');
                    if($body) $summary['message_texts'][]=mb_substr((string)$body,0,160);
                }
                foreach($value['statuses'] ?? [] as $status){
                    $summary['statuses'][]=[
                        'id'=>$status['id'] ?? null,
                        'status'=>$status['status'] ?? null,
                    ];
                }
            }
        }
        foreach(['fields','phone_number_ids','message_ids','message_texts'] as $key){
            $summary[$key]=array_values(array_unique(array_filter($summary[$key])));
        }
        return $summary;
    }

    private function eventKeys(array $payload,string $raw): array {
        $keys=[];
        foreach(data_get($payload,'entry',[]) as $entry){
            foreach(data_get($entry,'changes',[]) as $change){
                $value=$change['value'] ?? [];
                $phoneId=data_get($value,'metadata.phone_number_id');
                $merchantId=$phoneId ? WhatsappAccount::where('phone_number_id',$phoneId)->value('merchant_id') : null;
                foreach($value['messages'] ?? [] as $message){
                    $id=(string)($message['id'] ?? '');
                    if($id!=='') $keys[]=['event_key'=>'message:'.$id,'event_type'=>'message','merchant_id'=>$merchantId];
                }
                foreach($value['statuses'] ?? [] as $status){
                    $id=(string)($status['id'] ?? '');
                    $statusName=(string)($status['status'] ?? 'status');
                    if($id!=='') $keys[]=['event_key'=>'status:'.$id.':'.$statusName,'event_type'=>'status','merchant_id'=>$merchantId];
                }
            }
        }

        if(!$keys){
            $keys[]=['event_key'=>'payload:'.hash('sha256',$raw),'event_type'=>'payload','merchant_id'=>null];
        }
        return $keys;
    }
}
