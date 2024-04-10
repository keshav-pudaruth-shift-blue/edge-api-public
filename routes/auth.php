<?php

use Illuminate\Support\Facades\Route;

Route::get('/auth/redirect/{provider}', \App\Feature\SocialLogin\Http\Controllers\SocialLoginController::class)->name('auth.social.redirect');

Route::get('/auth/callback/{provider}', \App\Feature\SocialLogin\Http\Controllers\SocialCallbackController::class)
    ->name('auth.social.callback');
