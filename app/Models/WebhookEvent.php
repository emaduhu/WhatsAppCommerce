<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WebhookEvent extends Model {
    protected $fillable=['merchant_id','provider','event_key','event_type','status','processed_attempts','last_error','payload','processed_at'];
    protected function casts(): array { return ['payload'=>'array','processed_at'=>'datetime']; }
}
