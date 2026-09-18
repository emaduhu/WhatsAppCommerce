<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('carts', function(Blueprint $t){
            $t->id();
            $t->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('DRAFT');
            $t->string('currency',3)->default('TZS');
            $t->decimal('subtotal',18,2)->default(0);
            $t->decimal('delivery_fee',18,2)->default(0);
            $t->decimal('discount',18,2)->default(0);
            $t->decimal('total',18,2)->default(0);
            $t->text('delivery_address')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('payment_msisdn')->nullable();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['merchant_id','customer_id','status']);
        });
        Schema::create('cart_items', function(Blueprint $t){
            $t->id();
            $t->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('sku')->nullable();
            $t->integer('quantity');
            $t->decimal('unit_price',18,2);
            $t->decimal('total',18,2);
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->unique(['cart_id','product_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
