<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Landing pages the in-app WebView detects to dismiss after Stripe Checkout.
Route::view('subscription/success', 'subscription.success')->name('subscription.success');
Route::view('subscription/cancel', 'subscription.cancel')->name('subscription.cancel');
