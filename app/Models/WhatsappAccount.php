<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WhatsappAccount extends Model {
    protected $fillable=['merchant_id','waba_id','phone_number_id','display_phone_number','verified_name','access_token','status','last_verified_at','onboarding_status','registration_pin','token_expires_at','last_webhook_at','metadata'];
    protected $hidden=['access_token','registration_pin'];
    protected $casts=['access_token'=>'encrypted','registration_pin'=>'encrypted','last_verified_at'=>'datetime','token_expires_at'=>'datetime','last_webhook_at'=>'datetime','metadata'=>'array'];
    public function merchant(){ return $this->belongsTo(Merchant::class); }
    public function carts(){ return $this->hasMany(Cart::class); }
}
