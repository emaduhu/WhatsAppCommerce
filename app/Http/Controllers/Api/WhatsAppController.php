<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppText;
use App\Models\{Customer,Merchant,WhatsappAccount};
use App\Services\WhatsApp\{MetaWhatsAppOnboarding,WhatsAppCloudClient};
use Illuminate\Http\Request;
use RuntimeException;

class WhatsAppController extends Controller {
    public function accounts(Merchant $merchant){
        return $merchant->whatsappAccounts()->where('status','ACTIVE')->latest()->get()->map(fn(WhatsappAccount $account)=>$this->accountResource($account));
    }

    public function onboardingConfig(Merchant $merchant,MetaWhatsAppOnboarding $onboarding){
        return $onboarding->publicConfig();
    }

    public function completeOnboarding(Request $r,Merchant $merchant,MetaWhatsAppOnboarding $onboarding){
        $data=$r->validate([
            'whatsapp_number'=>'required|string|max:30',
            'code'=>'nullable|required_without:access_token|string|max:2000',
            'access_token'=>'nullable|required_without:code|string|max:5000',
            'access_token_expires_in'=>'nullable|integer|min:0',
            'granted_scopes'=>'nullable|string|max:3000',
            'denied_scopes'=>'nullable|string|max:3000',
            'waba_id'=>'nullable|string|max:120',
            'phone_number_id'=>'nullable|string|max:120',
            'verified_name'=>'nullable|string|max:180',
            'registration_pin'=>'nullable|digits:6',
            'register_phone_number'=>'sometimes|boolean',
            'connection_mode'=>'nullable|string|in:business_app_coexistence,cloud_api_only',
            'session_info'=>'nullable|array',
        ]);
        try{
            return response()->json(['account'=>$this->accountResource($onboarding->complete($merchant,$data))],201);
        }catch(RuntimeException $e){
            return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function retryRegistration(Request $r,Merchant $merchant,WhatsappAccount $account,MetaWhatsAppOnboarding $onboarding){
        abort_unless($account->merchant_id===$merchant->id,404);
        $data=$r->validate(['registration_pin'=>'nullable|digits:6']);
        try{
            return response()->json(['account'=>$this->accountResource($onboarding->retryRegistration($account,$data['registration_pin'] ?? null))]);
        }catch(RuntimeException $e){
            return response()->json(['message'=>$e->getMessage()],422);
        }
    }

    public function connect(Request $r,Merchant $merchant){
        $d=$r->validate(['waba_id'=>'required|string','phone_number_id'=>'required|string|unique:whatsapp_accounts,phone_number_id','display_phone_number'=>'nullable|string','verified_name'=>'nullable|string','access_token'=>'required|string|min:50']);
        return response()->json($this->accountResource($merchant->whatsappAccounts()->create($d+['status'=>'ACTIVE','onboarding_status'=>'MANUAL'])),201);
    }

    public function sendText(Request $r,Merchant $merchant){
        $d=$r->validate(['account_id'=>'required|integer','customer_id'=>'required|integer','body'=>'required|string|max:4096']);
        $a=WhatsappAccount::where('merchant_id',$merchant->id)->findOrFail($d['account_id']);
        $c=Customer::where('merchant_id',$merchant->id)->findOrFail($d['customer_id']);
        SendWhatsAppText::dispatch($a->id,$c->id,$d['body']);
        return response()->json(['queued'=>true],202);
    }

    public function sendTemplate(Request $r,Merchant $merchant,WhatsAppCloudClient $client){
        $d=$r->validate(['account_id'=>'required|integer','customer_id'=>'required|integer','name'=>'required|string','language'=>'nullable|string','components'=>'nullable|array']);
        $a=WhatsappAccount::where('merchant_id',$merchant->id)->findOrFail($d['account_id']);
        $c=Customer::where('merchant_id',$merchant->id)->findOrFail($d['customer_id']);
        return $client->sendTemplate($a,$c->phone,$d['name'],$d['language']??'en_US',$d['components']??[]);
    }

    private function accountResource(WhatsappAccount $account): array {
        $metadata=$account->metadata ?: [];
        $registration=$metadata['registration'] ?? null;
        $connectionMode=$metadata['connection_mode'] ?? null;
        return [
            'id'=>$account->id,
            'merchant_id'=>$account->merchant_id,
            'waba_id'=>$account->waba_id,
            'phone_number_id'=>$account->phone_number_id,
            'display_phone_number'=>$account->display_phone_number,
            'verified_name'=>$account->verified_name,
            'status'=>$account->status,
            'onboarding_status'=>$account->onboarding_status,
            'last_verified_at'=>$account->last_verified_at,
            'last_webhook_at'=>$account->last_webhook_at,
            'created_at'=>$account->created_at,
            'updated_at'=>$account->updated_at,
            'connection_mode'=>$connectionMode,
            'registration_ok'=>is_array($registration) ? (bool)($registration['ok'] ?? false) : null,
            'registration_status'=>is_array($registration) ? ($registration['status'] ?? null) : null,
            'registration_error'=>data_get($registration,'response.error.error_user_msg')
                ?? data_get($registration,'response.error.message')
                ?? ($metadata['registration_last_error'] ?? null),
            'quality_rating'=>$metadata['quality_rating'] ?? null,
            'platform_type'=>$metadata['platform_type'] ?? null,
            'code_verification_status'=>$metadata['code_verification_status'] ?? null,
        ];
    }
}
