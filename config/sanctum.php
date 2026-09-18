<?php
use Laravel\Sanctum\Sanctum;
return ['stateful'=>explode(',',env('SANCTUM_STATEFUL_DOMAINS',sprintf('%s%s',Sanctum::currentApplicationUrlWithPort(),env('FRONTEND_URL')?','.parse_url(env('FRONTEND_URL'),PHP_URL_HOST):''))),'guard'=>['web'],'expiration'=>env('SANCTUM_EXPIRATION'),'token_prefix'=>env('SANCTUM_TOKEN_PREFIX',''),'middleware'=>['authenticate_session'=>Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,'encrypt_cookies'=>Illuminate\Cookie\Middleware\EncryptCookies::class,'validate_csrf_token'=>Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]];
