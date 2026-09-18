<?php
namespace Database\Seeders;

use App\Models\{Customer,Merchant,Product,SubscriptionPlan,User,WhatsappAccount};
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder {
    public function run(): void {
        SubscriptionPlan::updateOrCreate(['code'=>'STARTER'],['name'=>'Starter','price'=>20000,'currency'=>'TZS','message_limit'=>1000,'product_limit'=>100,'user_limit'=>2,'features'=>['catalog','orders','whatsapp'],'is_active'=>true]);
        SubscriptionPlan::updateOrCreate(['code'=>'BUSINESS'],['name'=>'Business','price'=>50000,'currency'=>'TZS','message_limit'=>5000,'product_limit'=>1000,'user_limit'=>10,'features'=>['catalog','orders','whatsapp','ai_agent','inbox'],'is_active'=>true]);
        SubscriptionPlan::updateOrCreate(['code'=>'PRO'],['name'=>'Pro','price'=>100000,'currency'=>'TZS','message_limit'=>20000,'product_limit'=>5000,'user_limit'=>25,'features'=>['catalog','orders','whatsapp','ai_agent','inbox','reports'],'is_active'=>true]);

        if(app()->environment('production') && !env('SEED_DEMO_DATA')) return;

        $password=env('DEMO_OWNER_PASSWORD','ChangeMe12345');
        $owner=User::firstOrCreate(['email'=>'demo.owner@example.com'],['name'=>'Demo Owner','password'=>$password]);
        $merchant=Merchant::firstOrCreate(['slug'=>'demo-electronics'],[
            'name'=>'Demo Electronics',
            'business_type'=>'Retail',
            'email'=>'demo.owner@example.com',
            'country'=>'TZ',
            'currency'=>'TZS',
            'timezone'=>'Africa/Dar_es_Salaam',
            'status'=>'ACTIVE',
        ]);
        $merchant->users()->syncWithoutDetaching([$owner->id=>['role'=>'OWNER','is_active'=>true]]);

        $phones=$merchant->categories()->firstOrCreate(['name'=>'Phones'],['status'=>'ACTIVE']);
        $product=Product::updateOrCreate(['merchant_id'=>$merchant->id,'sku'=>'SAM-A55'],[
            'category_id'=>$phones->id,
            'name'=>'Samsung Galaxy A55',
            'description'=>'Samsung Galaxy A55 smartphone',
            'type'=>'PRODUCT',
            'price'=>850000,
            'currency'=>'TZS',
            'stock_quantity'=>0,
            'track_stock'=>true,
            'status'=>'ACTIVE',
            'is_active'=>true,
        ]);
        $product->variants()->updateOrCreate(['sku'=>'SAM-A55-BLU-128'],['merchant_id'=>$merchant->id,'name'=>'Blue 128GB','attributes'=>['color'=>'Blue','storage'=>'128GB'],'price'=>850000,'stock_quantity'=>5,'status'=>'ACTIVE']);
        $product->variants()->updateOrCreate(['sku'=>'SAM-A55-BLK-128'],['merchant_id'=>$merchant->id,'name'=>'Black 128GB','attributes'=>['color'=>'Black','storage'=>'128GB'],'price'=>850000,'stock_quantity'=>5,'status'=>'ACTIVE']);

        Customer::firstOrCreate(['merchant_id'=>$merchant->id,'phone'=>'255700000001'],['wa_id'=>'255700000001','whatsapp_number'=>'255700000001','name'=>'Demo Customer','language'=>'sw','last_inbound_at'=>now()]);
    }
}
