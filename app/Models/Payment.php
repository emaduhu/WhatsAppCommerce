<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model {
    protected $fillable=['merchant_id','order_id','provider','provider_transaction_id','reference','provider_reference','status','amount','currency','phone_number','msisdn','raw_request','raw_response','provider_response','paid_at'];
    protected function casts(): array { return ['amount'=>'decimal:2','raw_request'=>'array','raw_response'=>'array','provider_response'=>'array','paid_at'=>'datetime']; }
    public function order(){ return $this->belongsTo(Order::class); }
    public function merchant(){ return $this->belongsTo(Merchant::class); }
    public function transactions(){ return $this->hasMany(PaymentTransaction::class); }
}
