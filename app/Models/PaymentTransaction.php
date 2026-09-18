<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentTransaction extends Model {
    protected $fillable=['merchant_id','payment_id','provider','event_key','provider_transaction_id','status','amount','currency','payload','processed_at'];
    protected function casts(): array { return ['amount'=>'decimal:2','payload'=>'array','processed_at'=>'datetime']; }
    public function payment(){ return $this->belongsTo(Payment::class); }
}
