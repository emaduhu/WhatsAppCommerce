<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('merchants', function(Blueprint $t){
            if(!Schema::hasColumn('merchants','business_type')) $t->string('business_type')->nullable()->after('slug');
            if(!Schema::hasColumn('merchants','country')) $t->string('country',2)->default('TZ')->after('phone');
            if(!Schema::hasColumn('merchants','address')) $t->text('address')->nullable()->after('timezone');
            if(!Schema::hasColumn('merchants','logo')) $t->string('logo')->nullable()->after('address');
        });

        if(!Schema::hasTable('categories')){
            Schema::create('categories', function(Blueprint $t){
                $t->id();
                $t->foreignId('merchant_id')->constrained()->cascadeOnDelete();
                $t->string('name');
                $t->text('description')->nullable();
                $t->string('status')->default('ACTIVE');
                $t->timestamps();
                $t->unique(['merchant_id','name']);
                $t->index(['merchant_id','status']);
            });
        }

        Schema::table('products', function(Blueprint $t){
            if(!Schema::hasColumn('products','category_id')) $t->unsignedBigInteger('category_id')->nullable()->after('merchant_id');
            if(!Schema::hasColumn('products','currency')) $t->string('currency',3)->nullable()->after('cost_price');
            if(!Schema::hasColumn('products','status')) $t->string('status')->default('ACTIVE')->after('track_stock');
            if(!Schema::hasColumn('products','archived_at')) $t->timestamp('archived_at')->nullable()->after('metadata');
        });

        if(!Schema::hasTable('product_variants')){
            Schema::create('product_variants', function(Blueprint $t){
                $t->id();
                $t->foreignId('merchant_id')->constrained()->cascadeOnDelete();
                $t->foreignId('product_id')->constrained()->cascadeOnDelete();
                $t->string('sku')->nullable();
                $t->string('name');
                $t->json('attributes')->nullable();
                $t->decimal('price',18,2)->nullable();
                $t->integer('stock_quantity')->default(0);
                $t->string('status')->default('ACTIVE');
                $t->timestamps();
                $t->unique(['merchant_id','sku']);
                $t->index(['merchant_id','product_id','status']);
            });
        }

        if(!Schema::hasTable('product_images')){
            Schema::create('product_images', function(Blueprint $t){
                $t->id();
                $t->foreignId('merchant_id')->constrained()->cascadeOnDelete();
                $t->foreignId('product_id')->constrained()->cascadeOnDelete();
                $t->string('path');
                $t->string('disk')->default('public');
                $t->string('alt_text')->nullable();
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamps();
                $t->index(['merchant_id','product_id']);
            });
        }

        if(!Schema::hasTable('inventory_movements')){
            Schema::create('inventory_movements', function(Blueprint $t){
                $t->id();
                $t->foreignId('merchant_id')->constrained()->cascadeOnDelete();
                $t->unsignedBigInteger('product_id')->nullable();
                $t->unsignedBigInteger('product_variant_id')->nullable();
                $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $t->string('type');
                $t->integer('quantity');
                $t->integer('stock_before')->nullable();
                $t->integer('stock_after')->nullable();
                $t->string('reference')->nullable();
                $t->text('notes')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->index(['merchant_id','product_id','created_at'],'inv_mov_merchant_product_created_idx');
                $t->index(['merchant_id','product_variant_id','created_at'],'inv_mov_merchant_variant_created_idx');
                $t->index(['merchant_id','type','created_at'],'inv_mov_merchant_type_created_idx');
            });
        }

        Schema::table('whatsapp_accounts', function(Blueprint $t){
            if(!Schema::hasColumn('whatsapp_accounts','last_verified_at')) $t->timestamp('last_verified_at')->nullable()->after('status');
        });

        Schema::table('customers', function(Blueprint $t){
            if(!Schema::hasColumn('customers','whatsapp_number')) $t->string('whatsapp_number')->nullable()->after('wa_id');
            if(!Schema::hasColumn('customers','language')) $t->string('language',10)->nullable()->after('email');
            if(!Schema::hasColumn('customers','last_order_at')) $t->timestamp('last_order_at')->nullable()->after('last_outbound_at');
            if(!Schema::hasColumn('customers','total_orders')) $t->unsignedInteger('total_orders')->default(0)->after('last_order_at');
            if(!Schema::hasColumn('customers','lifetime_value')) $t->decimal('lifetime_value',18,2)->default(0)->after('total_orders');
            if(!Schema::hasColumn('customers','metadata')) $t->json('metadata')->nullable()->after('lifetime_value');
        });

        Schema::table('conversations', function(Blueprint $t){
            if(!Schema::hasColumn('conversations','current_state')) $t->string('current_state')->default('NEW')->after('assigned_user_id');
            if(!Schema::hasColumn('conversations','context')) $t->json('context')->nullable()->after('current_state');
            if(!Schema::hasColumn('conversations','resolved_at')) $t->timestamp('resolved_at')->nullable()->after('last_message_at');
        });

        Schema::table('messages', function(Blueprint $t){
            if(!Schema::hasColumn('messages','whatsapp_message_id')) $t->string('whatsapp_message_id')->nullable()->after('whatsapp_account_id');
            if(!Schema::hasColumn('messages','failed_at')) $t->timestamp('failed_at')->nullable()->after('read_at');
        });

        Schema::table('carts', function(Blueprint $t){
            if(!Schema::hasColumn('carts','delivery_method')) $t->string('delivery_method')->nullable()->after('delivery_address');
            if(!Schema::hasColumn('carts','expires_at')) $t->timestamp('expires_at')->nullable()->after('metadata');
        });

        Schema::table('cart_items', function(Blueprint $t){
            if(!Schema::hasColumn('cart_items','product_variant_id')) $t->unsignedBigInteger('product_variant_id')->nullable()->after('product_id');
            if(!Schema::hasColumn('cart_items','subtotal')) $t->decimal('subtotal',18,2)->default(0)->after('total');
        });

        Schema::table('orders', function(Blueprint $t){
            if(!Schema::hasColumn('orders','cart_id')) $t->unsignedBigInteger('cart_id')->nullable()->after('customer_id');
            if(!Schema::hasColumn('orders','order_number')) $t->string('order_number')->nullable()->after('cart_id');
            if(!Schema::hasColumn('orders','fulfilment_status')) $t->string('fulfilment_status')->default('PENDING')->after('payment_status');
        });

        Schema::table('order_items', function(Blueprint $t){
            if(!Schema::hasColumn('order_items','product_variant_id')) $t->unsignedBigInteger('product_variant_id')->nullable()->after('product_id');
            if(!Schema::hasColumn('order_items','product_name')) $t->string('product_name')->nullable()->after('product_variant_id');
            if(!Schema::hasColumn('order_items','subtotal')) $t->decimal('subtotal',18,2)->default(0)->after('total');
        });

        Schema::table('payments', function(Blueprint $t){
            if(!Schema::hasColumn('payments','provider_transaction_id')) $t->string('provider_transaction_id')->nullable()->after('provider');
            if(!Schema::hasColumn('payments','reference')) $t->string('reference')->nullable()->after('provider_transaction_id');
            if(!Schema::hasColumn('payments','phone_number')) $t->string('phone_number')->nullable()->after('currency');
            if(!Schema::hasColumn('payments','provider_response')) $t->json('provider_response')->nullable()->after('raw_response');
        });

        if(!Schema::hasTable('payment_transactions')){
            Schema::create('payment_transactions', function(Blueprint $t){
                $t->id();
                $t->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
                $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
                $t->string('provider');
                $t->string('event_key');
                $t->string('provider_transaction_id')->nullable();
                $t->string('status')->default('RECEIVED');
                $t->decimal('amount',18,2)->nullable();
                $t->string('currency',3)->nullable();
                $t->json('payload')->nullable();
                $t->timestamp('processed_at')->nullable();
                $t->timestamps();
                $t->unique(['provider','event_key']);
                $t->index(['provider','provider_transaction_id']);
                $t->index(['merchant_id','status','created_at']);
            });
        }

        Schema::table('webhook_events', function(Blueprint $t){
            if(!Schema::hasColumn('webhook_events','merchant_id')) $t->unsignedBigInteger('merchant_id')->nullable()->after('id');
            if(!Schema::hasColumn('webhook_events','status')) $t->string('status')->default('RECEIVED')->after('event_type');
            if(!Schema::hasColumn('webhook_events','processed_attempts')) $t->unsignedInteger('processed_attempts')->default(0)->after('status');
            if(!Schema::hasColumn('webhook_events','last_error')) $t->text('last_error')->nullable()->after('processed_attempts');
        });

        if(!Schema::hasTable('whatsapp_templates')){
            Schema::create('whatsapp_templates', function(Blueprint $t){
                $t->id();
                $t->foreignId('merchant_id')->constrained()->cascadeOnDelete();
                $t->foreignId('whatsapp_account_id')->nullable()->constrained()->nullOnDelete();
                $t->string('name');
                $t->string('language',20)->default('en_US');
                $t->string('category')->default('UTILITY');
                $t->string('status')->default('PENDING');
                $t->json('components')->nullable();
                $t->timestamps();
                $t->unique(['merchant_id','whatsapp_account_id','name','language'],'wa_tpl_merchant_account_name_lang_unique');
            });
        }

        if(!Schema::hasTable('usage_records')){
            Schema::create('usage_records', function(Blueprint $t){
                $t->id();
                $t->foreignId('merchant_id')->constrained()->cascadeOnDelete();
                $t->string('metric');
                $t->unsignedInteger('quantity')->default(0);
                $t->dateTime('period_start');
                $t->dateTime('period_end');
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->index(['merchant_id','metric','period_start','period_end']);
            });
        }

        if(!Schema::hasTable('audit_logs')){
            Schema::create('audit_logs', function(Blueprint $t){
                $t->id();
                $t->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
                $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $t->string('action');
                $t->string('entity_type')->nullable();
                $t->unsignedBigInteger('entity_id')->nullable();
                $t->json('before')->nullable();
                $t->json('after')->nullable();
                $t->string('ip_address',45)->nullable();
                $t->text('user_agent')->nullable();
                $t->timestamps();
                $t->index(['merchant_id','action','created_at']);
            });
        }
    }

    public function down(): void {
        foreach(['audit_logs','usage_records','whatsapp_templates','payment_transactions','inventory_movements','product_images','product_variants','categories'] as $table){
            Schema::dropIfExists($table);
        }
    }
};
