<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WhatsappTemplate extends Model {
    protected $fillable=['merchant_id','whatsapp_account_id','name','language','category','status','components'];
    protected function casts(): array { return ['components'=>'array']; }
}
