<?php
namespace App\Services\WhatsApp;

use App\Models\{Merchant,WhatsappAccount};
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class MetaWhatsAppOnboarding {
    public function publicConfig(): array {
        $appId=(string)config('services.whatsapp.app_id');
        $configId=(string)config('services.whatsapp.embedded_signup_config_id');
        $required=[
            'WHATSAPP_APP_ID'=>$appId,
            'WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID'=>$configId,
            'WHATSAPP_APP_SECRET'=>(string)config('services.whatsapp.app_secret'),
            'WHATSAPP_WEBHOOK_VERIFY_TOKEN'=>(string)config('services.whatsapp.verify_token'),
        ];
        $missing=array_keys(array_filter($required,fn($value)=>$value===''));

        return [
            'enabled'=>$missing===[],
            'app_id'=>$appId ?: null,
            'config_id'=>$configId ?: null,
            'graph_version'=>config('services.whatsapp.version'),
            'callback_url'=>$this->callbackUrl(),
            'message'=>$missing===[] ? null : 'Meta Embedded Signup is not configured. Set '.implode(', ',$missing).'.',
        ];
    }

    public function complete(Merchant $merchant,array $data): WhatsappAccount {
        $this->assertConfigured();
        $tokenSource=filled($data['code'] ?? null) ? 'authorization_code' : 'facebook_login_access_token';
        $tokenPayload=$tokenSource==='authorization_code'
            ? $this->exchangeCode((string)$data['code'])
            : $this->exchangeLoginAccessToken((string)$data['access_token']);
        $businessToken=(string)($tokenPayload['access_token'] ?? '');
        if($businessToken==='') throw new RuntimeException('Meta did not return an access token.');

        $wantedPhone=$this->normalizePhone((string)$data['whatsapp_number']);
        $wabaIds=$this->candidateWabaIds($businessToken,$data['waba_id'] ?? null);
        $phone=$this->resolvePhone($businessToken,$wabaIds,$wantedPhone,$data['phone_number_id'] ?? null);

        $connectionMode=(string)($data['connection_mode'] ?? 'business_app_coexistence');
        $coexistence=$connectionMode==='business_app_coexistence';
        $appWebhook=$this->ensureAppWebhookSubscription($coexistence);
        $subscription=$this->subscribeWaba($businessToken,$phone['waba_id'],$coexistence);
        $registration=$connectionMode==='cloud_api_only'
            ? $this->maybeRegisterPhone($businessToken,$phone['id'],$data)
            : [
                'attempted'=>false,
                'ok'=>true,
                'mode'=>'WHATSAPP_BUSINESS_APP_COEXISTENCE',
                'message'=>'Using WhatsApp Business app coexistence; Cloud API phone registration is not required.',
            ];

        $existing=WhatsappAccount::where('phone_number_id',$phone['id'])->first();
        if($existing && $existing->merchant_id!==$merchant->id) throw new RuntimeException('This WhatsApp phone number is already connected to another merchant.');

        $account=WhatsappAccount::updateOrCreate(
            ['phone_number_id'=>$phone['id']],
            [
                'merchant_id'=>$merchant->id,
                'waba_id'=>$phone['waba_id'],
                'display_phone_number'=>$phone['display_phone_number'] ?? $data['whatsapp_number'],
                'verified_name'=>$phone['verified_name'] ?? ($data['verified_name'] ?? null),
                'access_token'=>$businessToken,
                'status'=>'ACTIVE',
                'onboarding_status'=>$connectionMode==='business_app_coexistence' ? 'COEXISTENCE' : 'EMBEDDED_SIGNUP',
                'registration_pin'=>$registration['pin'] ?? null,
                'token_expires_at'=>isset($tokenPayload['expires_in']) ? now()->addSeconds((int)$tokenPayload['expires_in']) : null,
                'last_webhook_at'=>null,
                'metadata'=>[
                    'onboarded_at'=>now()->toIso8601String(),
                    'connection_mode'=>$connectionMode,
                    'token_source'=>$tokenSource,
                    'token_exchange_fallback'=>$tokenPayload['fallback_exchange_error'] ?? null,
                    'login_access_token_expires_in'=>$data['access_token_expires_in'] ?? null,
                    'granted_scopes'=>$data['granted_scopes'] ?? null,
                    'denied_scopes'=>$data['denied_scopes'] ?? null,
                    'code_verification_status'=>$phone['code_verification_status'] ?? null,
                    'quality_rating'=>$phone['quality_rating'] ?? null,
                    'platform_type'=>$phone['platform_type'] ?? null,
                    'app_webhook_subscription'=>$appWebhook,
                    'webhook_subscription'=>$subscription,
                    'registration'=>$registration,
                    'embedded_signup_session'=>$data['session_info'] ?? null,
                ],
            ]
        );
        $this->retireDuplicatePhoneAccounts($account);
        return $account->fresh();
    }

    private function retireDuplicatePhoneAccounts(WhatsappAccount $account): void {
        $target=$this->normalizePhone((string)$account->display_phone_number);
        if($target==='') return;

        WhatsappAccount::where('merchant_id',$account->merchant_id)
            ->where('id','!=',$account->id)
            ->where('status','ACTIVE')
            ->get()
            ->each(function(WhatsappAccount $other) use($target){
                if($this->normalizePhone((string)$other->display_phone_number)!==$target) return;
                $metadata=$other->metadata ?: [];
                $metadata['retired_at']=now()->toIso8601String();
                $metadata['retired_reason']='duplicate_phone_reconnected';
                $other->forceFill([
                    'status'=>'DISCONNECTED',
                    'metadata'=>$metadata,
                ])->save();
            });
    }

    public function retryRegistration(WhatsappAccount $account,?string $pin=null): WhatsappAccount {
        $this->assertConfigured();
        $businessToken=(string)$account->access_token;
        if($businessToken==='') throw new RuntimeException('This WhatsApp account does not have a saved Meta access token. Reconnect with Meta.');

        $metadata=$account->metadata ?: [];
        $pin=(string)($pin ?: $account->registration_pin ?: data_get($metadata,'registration.pin') ?: random_int(100000,999999));
        $registration=$this->maybeRegisterPhone($businessToken,$account->phone_number_id,[
            'registration_pin'=>$pin,
            'register_phone_number'=>true,
        ]);

        $phone=null;
        $phoneError=null;
        try{
            $phone=$this->phoneNumber($businessToken,$account->phone_number_id);
        }catch(Throwable $e){
            $phoneError=$e->getMessage();
        }

        $metadata['registration']=$registration;
        $metadata['registration_retried_at']=now()->toIso8601String();
        if($phoneError) $metadata['registration_phone_lookup_error']=$phoneError;
        if(is_array($phone)){
            $metadata['code_verification_status']=$phone['code_verification_status'] ?? ($metadata['code_verification_status'] ?? null);
            $metadata['quality_rating']=$phone['quality_rating'] ?? ($metadata['quality_rating'] ?? null);
            $metadata['platform_type']=$phone['platform_type'] ?? ($metadata['platform_type'] ?? null);
        }

        $account->forceFill([
            'display_phone_number'=>$phone['display_phone_number'] ?? $account->display_phone_number,
            'verified_name'=>$phone['verified_name'] ?? $account->verified_name,
            'registration_pin'=>$pin,
            'metadata'=>$metadata,
            'last_verified_at'=>($registration['ok'] ?? false) ? now() : $account->last_verified_at,
        ])->save();

        if(!($registration['ok'] ?? false)){
            $message=data_get($registration,'response.error.error_user_msg')
                ?? data_get($registration,'response.error.message')
                ?? 'Meta phone registration failed.';
            throw new RuntimeException('Meta phone registration failed: '.$message);
        }

        return $account->fresh();
    }

    private function exchangeCode(string $code): array {
        $params=[
            'client_id'=>config('services.whatsapp.app_id'),
            'client_secret'=>config('services.whatsapp.app_secret'),
            'code'=>$code,
        ];
        $redirect=(string)config('services.whatsapp.embedded_signup_redirect_uri');
        if($redirect!=='') $params['redirect_uri']=$redirect;

        $response=$this->graph()->get('/oauth/access_token',$params);
        if($response->failed()) throw new RuntimeException('Meta code exchange failed: '.$response->body());
        return $response->json();
    }

    private function exchangeLoginAccessToken(string $accessToken): array {
        if($accessToken==='') throw new RuntimeException('Meta did not return an authorization code or access token.');

        $response=$this->graph()->get('/oauth/access_token',[
            'grant_type'=>'fb_exchange_token',
            'client_id'=>config('services.whatsapp.app_id'),
            'client_secret'=>config('services.whatsapp.app_secret'),
            'fb_exchange_token'=>$accessToken,
        ]);

        if($response->successful()){
            $payload=$response->json();
            if(is_array($payload) && filled($payload['access_token'] ?? null)) return $payload;
        }

        return [
            'access_token'=>$accessToken,
            'fallback_exchange_error'=>$response->body(),
        ];
    }

    private function candidateWabaIds(string $businessToken,?string $provided): array {
        $ids=array_filter([$provided]);
        $response=$this->graph()->get('/debug_token',[
            'input_token'=>$businessToken,
            'access_token'=>$this->appAccessToken(),
        ]);
        if($response->ok()){
            foreach(data_get($response->json(),'data.granular_scopes',[]) as $scope){
                $name=(string)($scope['scope'] ?? '');
                if(in_array($name,['whatsapp_business_management','business_management','whatsapp_business_manage_events'],true)){
                    foreach($scope['target_ids'] ?? [] as $target) $ids[]=(string)$target;
                }
            }
        }
        foreach($this->businessWabaIds($businessToken) as $id) $ids[]=$id;
        return array_values(array_unique(array_filter($ids)));
    }

    private function businessWabaIds(string $businessToken): array {
        $ids=[];
        $response=$this->graph($businessToken)->get('/me/businesses',['fields'=>'id']);
        if(!$response->ok()) return $ids;
        foreach($response->json('data') ?? [] as $business){
            $businessId=(string)($business['id'] ?? '');
            if($businessId==='') continue;
            foreach(['owned_whatsapp_business_accounts','client_whatsapp_business_accounts'] as $edge){
                $wabas=$this->graph($businessToken)->get("/{$businessId}/{$edge}",['fields'=>'id']);
                if($wabas->ok()){
                    foreach($wabas->json('data') ?? [] as $waba){
                        if(filled($waba['id'] ?? null)) $ids[]=(string)$waba['id'];
                    }
                }
            }
        }
        return $ids;
    }

    private function resolvePhone(string $businessToken,array $wabaIds,string $wantedPhone,?string $providedPhoneId): array {
        if(!$wabaIds && !$providedPhoneId) throw new RuntimeException('Meta did not return a WABA ID or phone number ID. Complete Embedded Signup again and allow WhatsApp account permissions.');

        foreach($wabaIds as $wabaId){
            $phones=$this->phoneNumbers($businessToken,$wabaId);
            foreach($phones as $phone){
                $phoneId=(string)($phone['id'] ?? '');
                $display=$this->normalizePhone((string)($phone['display_phone_number'] ?? ''));
                if(($providedPhoneId && $phoneId===(string)$providedPhoneId) || ($wantedPhone!=='' && $display===$wantedPhone)){
                    $phone['waba_id']=$wabaId;
                    return $phone;
                }
            }
        }

        if($providedPhoneId && $wabaIds){
            $phone=$this->phoneNumber($businessToken,$providedPhoneId);
            $phone['waba_id']=$wabaIds[0];
            return $phone;
        }

        throw new RuntimeException('The supplied WhatsApp number was not found in the authorized WABA phone-number list.');
    }

    private function phoneNumbers(string $businessToken,string $wabaId): array {
        $response=$this->graph($businessToken)->get("/{$wabaId}/phone_numbers",[
            'fields'=>'id,display_phone_number,verified_name,code_verification_status,quality_rating,platform_type',
        ]);
        if($response->failed()) throw new RuntimeException('Could not read WABA phone numbers: '.$response->body());
        return $response->json('data') ?? [];
    }

    private function phoneNumber(string $businessToken,string $phoneNumberId): array {
        $response=$this->graph($businessToken)->get("/{$phoneNumberId}",[
            'fields'=>'id,display_phone_number,verified_name,code_verification_status,quality_rating,platform_type',
        ]);
        if($response->failed()) throw new RuntimeException('Could not read WhatsApp phone number: '.$response->body());
        return $response->json();
    }

    private function ensureAppWebhookSubscription(bool $coexistence=false): array {
        $appId=(string)config('services.whatsapp.app_id');
        $callback=$this->callbackUrl();
        $verifyToken=(string)config('services.whatsapp.verify_token');
        $fields=$this->webhookFields($coexistence);

        $existing=$this->graph()->get("/{$appId}/subscriptions",[
            'access_token'=>$this->appAccessToken(),
        ]);
        if($existing->ok()){
            foreach($existing->json('data') ?? [] as $subscription){
                if(($subscription['object'] ?? null)==='whatsapp_business_account'
                    && ($subscription['callback_url'] ?? null)===$callback
                    && $this->hasWebhookFields($subscription['fields'] ?? [],$fields)){
                    return ['ok'=>true,'already_configured'=>true,'status'=>$existing->status(),'response'=>$subscription];
                }
            }
        }

        $response=$this->graph()->asForm()->post("/{$appId}/subscriptions",[
            'object'=>'whatsapp_business_account',
            'callback_url'=>$callback,
            'fields'=>implode(',',$fields),
            'verify_token'=>$verifyToken,
            'access_token'=>$this->appAccessToken(),
        ]);
        if($response->failed()) throw new RuntimeException('Meta app webhook setup failed: '.$response->body());
        return ['ok'=>true,'already_configured'=>false,'status'=>$response->status(),'response'=>$response->json() ?: $response->body()];
    }

    private function hasWebhookFields(mixed $fields,array $required): bool {
        if(is_string($fields)){
            $names=array_map('trim',explode(',',$fields));
        }elseif(is_array($fields)){
            $names=[];
            foreach($fields as $field){
                if(is_string($field)) $names[]=$field;
                if(is_array($field) && ($field['name'] ?? null)) $names[]=(string)$field['name'];
            }
        }else{
            $names=[];
        }
        return empty(array_diff($required,$names));
    }

    private function subscribeWaba(string $businessToken,string $wabaId,bool $coexistence=false): array {
        $response=$this->graph($businessToken)->asForm()->post("/{$wabaId}/subscribed_apps",[
            'subscribed_fields'=>implode(',',$this->webhookFields($coexistence)),
        ]);
        $result=['ok'=>$response->successful(),'status'=>$response->status(),'response'=>$response->json() ?: $response->body()];
        if(!$response->successful()) return $result;

        $override=$this->graph($businessToken)->post("/{$wabaId}/subscribed_apps",[
            'override_callback_uri'=>$this->callbackUrl(),
            'verify_token'=>(string)config('services.whatsapp.verify_token'),
        ]);
        $result['override']=[
            'ok'=>$override->successful(),
            'status'=>$override->status(),
            'response'=>$override->json() ?: $override->body(),
        ];
        return $result;
    }

    private function webhookFields(bool $coexistence=false): array {
        $fields=['messages','message_template_status_update','phone_number_quality_update'];
        if($coexistence) array_push($fields,'smb_message_echoes','smb_app_state_sync','history');
        return $fields;
    }

    private function maybeRegisterPhone(string $businessToken,string $phoneNumberId,array $data): array {
        if(array_key_exists('register_phone_number',$data) && !$data['register_phone_number']) return ['attempted'=>false];
        $pin=(string)($data['registration_pin'] ?? random_int(100000,999999));
        $response=$this->graph($businessToken)->post("/{$phoneNumberId}/register",[
            'messaging_product'=>'whatsapp',
            'pin'=>$pin,
        ]);
        return ['attempted'=>true,'ok'=>$response->successful(),'status'=>$response->status(),'pin'=>$pin,'response'=>$response->json() ?: $response->body()];
    }

    private function assertConfigured(): void {
        foreach(['app_id','app_secret','embedded_signup_config_id','verify_token'] as $key){
            if((string)config("services.whatsapp.{$key}")==='') throw new RuntimeException("Missing WhatsApp Meta configuration: {$key}.");
        }
    }

    private function graph(?string $token=null): PendingRequest {
        $request=Http::baseUrl(rtrim((string)config('services.whatsapp.base_url'),'/').'/'.config('services.whatsapp.version'))->acceptJson()->asJson()->timeout(config('services.whatsapp.timeout'))->retry(2,500,throw:false);
        return $token ? $request->withToken($token) : $request;
    }

    private function callbackUrl(): string {
        $configured=(string)config('services.whatsapp.embedded_signup_callback_url');
        return rtrim($configured ?: url('/'),'/').'/api/webhooks/whatsapp';
    }

    private function appAccessToken(): string {
        return config('services.whatsapp.app_id').'|'.config('services.whatsapp.app_secret');
    }

    private function normalizePhone(string $phone): string {
        $digits=preg_replace('/\D+/','',$phone) ?: '';
        if(str_starts_with($digits,'0')) $digits='255'.substr($digits,1);
        return $digits;
    }
}
