<?php

use Illuminate\Support\Facades\Route;

it('trusts the X-Forwarded-Proto header so URLs keep the https scheme behind the proxy', function () {
    Route::get('/__proxy-scheme-check', fn () => request()->getScheme());

    $this->get('/__proxy-scheme-check', ['X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertSee('https');
});
