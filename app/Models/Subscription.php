<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Subscription extends Model {
    protected $fillable=['merchant_id','subscription_plan_id','status','starts_at','ends_at','messages_used'];
    protected function casts(): array { return ['starts_at'=>'datetime','ends_at'=>'datetime']; }
}
