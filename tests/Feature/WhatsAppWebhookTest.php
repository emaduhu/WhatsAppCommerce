<?php
namespace Tests\Feature;use Tests\TestCase;
class WhatsAppWebhookTest extends TestCase {public function test_verification_rejects_wrong_token():void{config(['services.whatsapp.verify_token'=>'correct']);$this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=123')->assertStatus(403);}public function test_verification_returns_challenge():void{config(['services.whatsapp.verify_token'=>'correct']);$this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=correct&hub_challenge=123')->assertOk()->assertSee('123');}}
