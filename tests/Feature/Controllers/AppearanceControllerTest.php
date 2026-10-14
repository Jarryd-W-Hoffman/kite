<?php

declare(strict_types=1);

use App\Services\Settings;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Enums\SystemThemesEnum;
use Native\Desktop\Http\Middleware\PreventRegularBrowserAccess;

it('saves the appearance without touching other preferences', function (): void {
    Http::fake();

    $this->putJson(route('appearance.update'), ['appearance' => 'dark'])
        ->assertOk()
        ->assertExactJson(['appearance' => 'dark']);

    expect(resolve(Settings::class)->all())
        ->toMatchArray(['appearance' => 'dark', 'editor_font' => 'sans']);

    Http::assertNothingSent();
});

it('validates the appearance', function (mixed $appearance): void {
    $this->putJson(route('appearance.update'), ['appearance' => $appearance])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('appearance');
})->with(['missing' => null, 'unknown' => 'sepia']);

it('matches the native window theme in the desktop app', function (): void {
    config(['nativephp-internal.running' => true]);
    Http::fake(['*' => ['result' => 'light']]);

    $this->withoutMiddleware(PreventRegularBrowserAccess::class)
        ->putJson(route('appearance.update'), ['appearance' => 'light'])
        ->assertOk();

    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), 'system/theme')
        && $request['theme'] === SystemThemesEnum::LIGHT);
});
