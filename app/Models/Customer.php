<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Customer extends Model {
    protected $fillable=['merchant_id','wa_id','whatsapp_number','name','phone','email','language','locale','marketing_opt_in','last_inbound_at','last_outbound_at','last_order_at','total_orders','lifetime_value','metadata'];
    protected function casts(): array { return ['marketing_opt_in'=>'boolean','last_inbound_at'=>'datetime','last_outbound_at'=>'datetime','last_order_at'=>'datetime','lifetime_value'=>'decimal:2','metadata'=>'array']; }
    public function merchant(){ return $this->belongsTo(Merchant::class); }
    public function conversations(){ return $this->hasMany(Conversation::class); }
    public function carts(){ return $this->hasMany(Cart::class); }
    public function orders(){ return $this->hasMany(Order::class); }
}
