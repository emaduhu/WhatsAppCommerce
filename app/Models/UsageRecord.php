<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UsageRecord extends Model {
    protected $fillable=['merchant_id','metric','quantity','period_start','period_end','metadata'];
    protected function casts(): array { return ['period_start'=>'datetime','period_end'=>'datetime','metadata'=>'array']; }
}
