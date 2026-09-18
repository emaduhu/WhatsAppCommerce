<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Conversation extends Model {
    protected $fillable=['merchant_id','customer_id','whatsapp_account_id','status','mode','assigned_user_id','current_state','context','last_message_at','resolved_at'];
    protected function casts(): array { return ['context'=>'array','last_message_at'=>'datetime','resolved_at'=>'datetime']; }
    public function customer(){ return $this->belongsTo(Customer::class); }
    public function merchant(){ return $this->belongsTo(Merchant::class); }
    public function whatsappAccount(){ return $this->belongsTo(WhatsappAccount::class); }
    public function assignedUser(){ return $this->belongsTo(User::class,'assigned_user_id'); }
    public function messages(){ return $this->hasMany(Message::class); }
    public function carts(){ return $this->hasMany(Cart::class); }
    public function activeCart(){ return $this->hasOne(Cart::class)->whereIn('status',['ACTIVE','DRAFT','AWAITING_DELIVERY','AWAITING_PAYMENT','PAYMENT_PENDING','CHECKOUT']); }
}
