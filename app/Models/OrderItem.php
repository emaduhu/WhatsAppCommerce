<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrderItem extends Model {
    protected $fillable=['order_id','product_id','product_variant_id','product_name','name','sku','quantity','unit_price','total','subtotal'];
    protected function casts(): array { return ['unit_price'=>'decimal:2','total'=>'decimal:2','subtotal'=>'decimal:2']; }
    public function product(){ return $this->belongsTo(Product::class); }
    public function variant(){ return $this->belongsTo(ProductVariant::class,'product_variant_id'); }
}
