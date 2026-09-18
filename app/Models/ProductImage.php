<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductImage extends Model {
    protected $fillable=['merchant_id','product_id','path','disk','alt_text','sort_order'];
    public function product(){ return $this->belongsTo(Product::class); }
}
