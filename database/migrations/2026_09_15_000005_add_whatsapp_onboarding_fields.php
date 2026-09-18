<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('whatsapp_accounts', function(Blueprint $t){
            $t->string('onboarding_status')->default('MANUAL')->after('status');
            $t->string('registration_pin')->nullable()->after('onboarding_status');
            $t->timestamp('token_expires_at')->nullable()->after('registration_pin');
            $t->json('metadata')->nullable()->after('last_webhook_at');
        });
    }

    public function down(): void {
        Schema::table('whatsapp_accounts', function(Blueprint $t){
            $t->dropColumn(['onboarding_status','registration_pin','token_expires_at','metadata']);
        });
    }
};
