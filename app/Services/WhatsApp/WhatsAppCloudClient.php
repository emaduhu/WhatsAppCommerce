<?php
namespace App\Services\WhatsApp;

use App\Models\WhatsappAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\{Http,Log};
use RuntimeException;

class WhatsAppCloudClient {
    private function client(WhatsappAccount $account): PendingRequest {
        return Http::baseUrl(rtrim((string)config('services.whatsapp.base_url'),'/').'/'.config('services.whatsapp.version'))
            ->withToken($account->access_token)
            ->acceptJson()
            ->asJson()
            ->timeout(config('services.whatsapp.timeout'))
            ->retry(3,500,throw:false);
    }

    public function sendText(WhatsappAccount $account,string $to,string $body,bool $preview=true): array {
        return $this->send($account,['messaging_product'=>'whatsapp','recipient_type'=>'individual','to'=>$to,'type'=>'text','text'=>['preview_url'=>$preview,'body'=>$body]]);
    }

    public function sendTemplate(WhatsappAccount $account,string $to,string $name,string $language='en_US',array $components=[]): array {
        return $this->send($account,['messaging_product'=>'whatsapp','to'=>$to,'type'=>'template','template'=>['name'=>$name,'language'=>['code'=>$language],'components'=>$components]]);
    }

    public function sendInteractive(WhatsappAccount $account,string $to,array $interactive): array {
        return $this->send($account,['messaging_product'=>'whatsapp','to'=>$to,'type'=>'interactive','interactive'=>$interactive]);
    }

    public function sendImage(WhatsappAccount $account,string $to,string $link,?string $caption=null): array {
        $image=['link'=>$link];
        if($caption) $image['caption']=$caption;
        return $this->send($account,['messaging_product'=>'whatsapp','to'=>$to,'type'=>'image','image'=>$image]);
    }

    public function sendDocument(WhatsappAccount $account,string $to,string $link,?string $filename=null,?string $caption=null): array {
        $document=['link'=>$link];
        if($filename) $document['filename']=$filename;
        if($caption) $document['caption']=$caption;
        return $this->send($account,['messaging_product'=>'whatsapp','to'=>$to,'type'=>'document','document'=>$document]);
    }

    public function markRead(WhatsappAccount $account,string $wamid): array { return $this->markAsRead($account,$wamid); }

    public function markAsRead(WhatsappAccount $account,string $wamid): array {
        return $this->send($account,['messaging_product'=>'whatsapp','status'=>'read','message_id'=>$wamid]);
    }

    public function getPhoneNumber(WhatsappAccount $account): array {
        $response=$this->client($account)->get('/'.$account->phone_number_id,['fields'=>'id,display_phone_number,verified_name,code_verification_status,quality_rating,platform_type']);
        if($response->failed()) $this->handleApiError($response->status(),$response->json() ?: ['body'=>$response->body()],$response->header('x-fb-request-id'));
        return $response->json() ?: [];
    }

    private function send(WhatsappAccount $account,array $payload): array {
        $response=$this->client($account)->post('/'.$account->phone_number_id.'/messages',$payload);
        if($response->failed()) $this->handleApiError($response->status(),$response->json() ?: ['body'=>$response->body()],$response->header('x-fb-request-id'));
        return $response->json() ?: [];
    }

    private function handleApiError(int $status,array $payload,?string $requestId=null): never {
        Log::warning('WhatsApp Cloud API request failed',['status'=>$status,'meta_request_id'=>$requestId,'error'=>data_get($payload,'error.message'),'code'=>data_get($payload,'error.code')]);
        throw new RuntimeException('WhatsApp API error '.$status.': '.(data_get($payload,'error.message') ?: 'request failed'));
    }
}
