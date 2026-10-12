<?php

declare(strict_types=1);

it('asks first-time users to open a folder', function (): void {
    $page = visit('/');

    $page->assertPathIs('/vaults')
        ->assertSee('Welcome to Kite')
        ->assertSee('Folder path')
        ->assertNoJavaScriptErrors();
});
