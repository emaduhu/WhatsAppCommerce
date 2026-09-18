<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Message extends Model {
    protected $fillable=['merchant_id','conversation_id','customer_id','whatsapp_account_id','whatsapp_message_id','wamid','direction','type','status','body','payload','error_code','error_message','sent_at','delivered_at','read_at','failed_at'];
    protected function casts(): array { return ['payload'=>'array','sent_at'=>'datetime','delivered_at'=>'datetime','read_at'=>'datetime','failed_at'=>'datetime']; }
    public function conversation(){ return $this->belongsTo(Conversation::class); }
    public function customer(){ return $this->belongsTo(Customer::class); }
}
