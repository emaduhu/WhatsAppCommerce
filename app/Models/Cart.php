<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Cart extends Model {
    protected $fillable=['merchant_id','customer_id','conversation_id','whatsapp_account_id','status','currency','subtotal','delivery_fee','discount','total','delivery_address','delivery_method','payment_method','payment_msisdn','order_id','metadata','expires_at'];
    protected function casts(): array { return ['subtotal'=>'decimal:2','delivery_fee'=>'decimal:2','discount'=>'decimal:2','total'=>'decimal:2','metadata'=>'array','expires_at'=>'datetime']; }
    public function items(){ return $this->hasMany(CartItem::class); }
    public function merchant(){ return $this->belongsTo(Merchant::class); }
    public function customer(){ return $this->belongsTo(Customer::class); }
    public function conversation(){ return $this->belongsTo(Conversation::class); }
    public function whatsappAccount(){ return $this->belongsTo(WhatsappAccount::class); }
    public function order(){ return $this->belongsTo(Order::class); }
}
