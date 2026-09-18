<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Order extends Model {
    protected $fillable=['merchant_id','customer_id','cart_id','order_number','number','status','payment_status','fulfilment_status','currency','subtotal','delivery_fee','discount','total','delivery_method','delivery_address','notes','paid_at'];
    protected function casts(): array { return ['subtotal'=>'decimal:2','delivery_fee'=>'decimal:2','discount'=>'decimal:2','total'=>'decimal:2','paid_at'=>'datetime']; }
    public function items(){ return $this->hasMany(OrderItem::class); }
    public function customer(){ return $this->belongsTo(Customer::class); }
    public function merchant(){ return $this->belongsTo(Merchant::class); }
    public function payments(){ return $this->hasMany(Payment::class); }
    public function cart(){ return $this->belongsTo(Cart::class); }
}
