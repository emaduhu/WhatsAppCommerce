<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SubscriptionPlan extends Model {
    protected $fillable=['name','code','price','currency','message_limit','product_limit','user_limit','features','is_active'];
    protected function casts(): array { return ['price'=>'decimal:2','features'=>'array','is_active'=>'boolean']; }
}
