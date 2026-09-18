<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductVariant extends Model {
    protected $fillable=['merchant_id','product_id','sku','name','attributes','price','stock_quantity','status'];
    protected function casts(): array { return ['attributes'=>'array','price'=>'decimal:2']; }
    public function merchant(){ return $this->belongsTo(Merchant::class); }
    public function product(){ return $this->belongsTo(Product::class); }
}
