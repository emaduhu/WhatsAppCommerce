<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Product extends Model {
    protected $fillable=['merchant_id','category_id','sku','name','description','type','price','cost_price','currency','stock_quantity','track_stock','status','is_active','metadata','archived_at'];
    protected function casts(): array { return ['price'=>'decimal:2','cost_price'=>'decimal:2','track_stock'=>'boolean','is_active'=>'boolean','metadata'=>'array','archived_at'=>'datetime']; }
    public function merchant(){ return $this->belongsTo(Merchant::class); }
    public function category(){ return $this->belongsTo(Category::class); }
    public function variants(){ return $this->hasMany(ProductVariant::class); }
    public function images(){ return $this->hasMany(ProductImage::class)->orderBy('sort_order'); }
    public function inventoryMovements(){ return $this->hasMany(InventoryMovement::class); }
    public function cartItems(){ return $this->hasMany(CartItem::class); }
    public function scopeActive($q){ return $q->where(fn($x)=>$x->where('is_active',true)->orWhere('status','ACTIVE'))->whereNull('archived_at'); }
}
