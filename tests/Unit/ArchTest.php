<?php

declare(strict_types=1);

use App\Providers\NativeAppServiceProvider;

arch()->preset()->php();
arch()->preset()->strict();
arch()->preset()->laravel()->ignoring([
    NativeAppServiceProvider::class,
]);
arch()->preset()->security()->ignoring([
    'assert',
]);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

//
