<?php
namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\WhatsApp\InboundProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWhatsAppWebhook implements ShouldQueue {
    use Queueable;
    public int $tries=5;
    public array $backoff=[1,5,20,60];

    public function __construct(public int|array $eventOrPayload){}

    public function handle(InboundProcessor $processor): void {
        if(is_array($this->eventOrPayload)){
            $processor->process($this->eventOrPayload);
            return;
        }

        $event=WebhookEvent::findOrFail($this->eventOrPayload);
        if($event->processed_at) return;

        try{
            $event->increment('processed_attempts');
            $processor->process($event->payload ?? []);
            $event->update(['status'=>'PROCESSED','processed_at'=>now(),'last_error'=>null]);
        }catch(Throwable $e){
            $event->update(['status'=>'FAILED','last_error'=>$e->getMessage()]);
            throw $e;
        }
    }
}
