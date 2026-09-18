<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Merchant extends Model {
    protected $fillable=['name','slug','business_type','email','phone','country','currency','timezone','address','logo','status'];
    public function users(){ return $this->belongsToMany(User::class,'merchant_users')->withPivot(['role','is_active'])->withTimestamps(); }
    public function whatsappAccounts(){ return $this->hasMany(WhatsappAccount::class); }
    public function categories(){ return $this->hasMany(Category::class); }
    public function products(){ return $this->hasMany(Product::class); }
    public function productVariants(){ return $this->hasMany(ProductVariant::class); }
    public function customers(){ return $this->hasMany(Customer::class); }
    public function conversations(){ return $this->hasMany(Conversation::class); }
    public function orders(){ return $this->hasMany(Order::class); }
    public function carts(){ return $this->hasMany(Cart::class); }
    public function payments(){ return $this->hasMany(Payment::class); }
}
