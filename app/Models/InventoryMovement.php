<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InventoryMovement extends Model {
    protected $fillable=['merchant_id','product_id','product_variant_id','order_id','user_id','type','quantity','stock_before','stock_after','reference','notes','metadata'];
    protected function casts(): array { return ['metadata'=>'array']; }
    public function product(){ return $this->belongsTo(Product::class); }
    public function variant(){ return $this->belongsTo(ProductVariant::class,'product_variant_id'); }
}
