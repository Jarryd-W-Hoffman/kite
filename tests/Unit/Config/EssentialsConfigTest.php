<?php

declare(strict_types=1);

use NunoMaduro\Essentials\Configurables\ForceScheme;

it('keeps plain http in production, as the desktop app serves itself over localhost', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    expect(app()->isProduction())->toBeTrue()
        ->and(new ForceScheme()->enabled())->toBeFalse();
});
