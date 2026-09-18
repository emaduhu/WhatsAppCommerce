<?php
namespace Tests\Feature;

use App\Models\{Merchant,WhatsappAccount};
use App\Services\WhatsApp\MetaWhatsAppOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppOnboardingTest extends TestCase {
    use RefreshDatabase;

    public function test_embedded_signup_completion_stores_whatsapp_account(): void {
        config([
            'app.key'=>'base64:'.base64_encode(str_repeat('b',32)),
            'services.whatsapp.base_url'=>'https://graph.facebook.com',
            'services.whatsapp.version'=>'v26.0',
            'services.whatsapp.app_id'=>'app-123',
            'services.whatsapp.app_secret'=>'secret-123',
            'services.whatsapp.embedded_signup_config_id'=>'config-123',
            'services.whatsapp.embedded_signup_callback_url'=>'https://wc.vigourtech.net',
            'services.whatsapp.verify_token'=>'verify-123',
        ]);

        Http::fake(function($request){
            $url=$request->url();
            if(str_contains($url,'/oauth/access_token')) return Http::response(['access_token'=>'business-token','expires_in'=>3600]);
            if(str_contains($url,'/debug_token')) return Http::response(['data'=>['granular_scopes'=>[['scope'=>'whatsapp_business_management','target_ids'=>['waba-123']]]]]);
            if(str_contains($url,'/waba-123/phone_numbers')) return Http::response(['data'=>[[
                'id'=>'phone-123',
                'display_phone_number'=>'+255 752 160 150',
                'verified_name'=>'Vigour Shop',
                'code_verification_status'=>'VERIFIED',
            ]]]);
            if(str_contains($url,'/app-123/subscriptions') && $request->method()==='GET') return Http::response(['data'=>[]]);
            if(str_contains($url,'/app-123/subscriptions') && $request->method()==='POST') return Http::response(['success'=>true]);
            if(str_contains($url,'/waba-123/subscribed_apps')) return Http::response(['success'=>true]);
            if(str_contains($url,'/phone-123/register')) return Http::response(['success'=>true]);
            return Http::response([],404);
        });

        $merchant=Merchant::create(['name'=>'Vigour Shop','slug'=>'vigour-shop']);

        $account=app(MetaWhatsAppOnboarding::class)->complete($merchant,[
            'whatsapp_number'=>'255752160150',
            'code'=>'auth-code',
            'register_phone_number'=>true,
            'registration_pin'=>'123456',
        ]);

        $this->assertSame('phone-123',$account->phone_number_id);
        $this->assertSame('waba-123',$account->waba_id);
        $this->assertSame('+255 752 160 150',$account->display_phone_number);
        $this->assertSame('COEXISTENCE',$account->onboarding_status);
        $this->assertSame('business_app_coexistence',$account->metadata['connection_mode']);
        $this->assertFalse($account->metadata['registration']['attempted']);
        $this->assertSame('ACTIVE',$account->status);
        $this->assertSame(1,WhatsappAccount::count());

        Http::assertSent(fn($request)=>str_contains($request->url(),'/app-123/subscriptions') && $request->method()==='POST');
        Http::assertSent(fn($request)=>str_contains($request->url(),'/waba-123/subscribed_apps'));
        Http::assertNotSent(fn($request)=>str_contains($request->url(),'/phone-123/register'));
    }
}
