<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Merchant,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller {
    public function register(Request $request){
        $data=$request->validate([
            'name'=>'required|string|max:120',
            'email'=>'required|email|unique:users,email',
            'password'=>['required','confirmed',Password::min(10)->mixedCase()->numbers()],
            'business_name'=>'required|string|max:160',
            'business_type'=>'nullable|string|max:80',
            'phone'=>'nullable|string|max:40',
            'country'=>'nullable|string|size:2',
            'currency'=>'nullable|string|size:3',
            'timezone'=>'nullable|string|max:80',
            'address'=>'nullable|string|max:1000',
        ]);
        [$user,$merchant]=DB::transaction(function() use($data){
            $user=User::create($data);
            $merchant=Merchant::create([
                'name'=>$data['business_name'],
                'slug'=>Str::slug($data['business_name']).'-'.Str::lower(Str::random(5)),
                'business_type'=>$data['business_type'] ?? null,
                'email'=>$data['email'],
                'phone'=>$data['phone'] ?? null,
                'country'=>$data['country'] ?? 'TZ',
                'currency'=>$data['currency'] ?? 'TZS',
                'timezone'=>$data['timezone'] ?? 'Africa/Dar_es_Salaam',
                'address'=>$data['address'] ?? null,
                'status'=>'ACTIVE',
            ]);
            $merchant->users()->attach($user->id,['role'=>'OWNER','is_active'=>true]);
            return [$user,$merchant];
        });
        return response()->json(['user'=>$user,'merchant'=>$merchant,'token'=>$user->createToken('primary',['*'])->plainTextToken],201);
    }

    public function login(Request $request){
        $data=$request->validate(['email'=>'required|email','password'=>'required|string','device_name'=>'nullable|string|max:100']);
        $user=User::where('email',$data['email'])->first();
        if(!$user || !Hash::check($data['password'],$user->password)){
            return response()->json(['message'=>'Invalid credentials'],422);
        }
        return ['user'=>$user,'merchants'=>$user->merchants,'token'=>$user->createToken($data['device_name'] ?? 'api',['*'])->plainTextToken];
    }

    public function me(Request $request){
        return ['user'=>$request->user(),'merchants'=>$request->user()->merchants()->wherePivot('is_active',true)->get()];
    }

    public function logout(Request $request){
        $request->user()->currentAccessToken()?->delete();
        return response()->noContent();
    }
}
