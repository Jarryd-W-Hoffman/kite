<?php

declare(strict_types=1);

use App\Events\MenuCommandSelected;
use App\Events\QuickCaptureRequested;
use App\Services\AppMenu;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

it('registers the app menu with commands that own their accelerators', function (): void {
    Http::fake();

    resolve(AppMenu::class)->register();

    Http::assertSent(function (ClientRequest $request): bool {
        if (! str_ends_with($request->url(), 'menu')) {
            return false;
        }

        $items = collect($request['items'])->flatMap(fn (array $item): array => $item['submenu'] ?? [$item]);
        $commands = $items->where('event', MenuCommandSelected::class)->mapWithKeys(fn (array $item): array => [$item['id'] => $item['accelerator']]);

        return $commands->sortKeys()->all() === collect(AppMenu::COMMANDS)->map(fn (array $command): string => $command[1])->sortKeys()->all()
            && $items->contains('event', QuickCaptureRequested::class)
            && collect($request['items'])->pluck('label')->filter()->values()->all() === ['File', 'View'];
    });
});

it('reads the command from whatever payload NativePHP sends', function (array $payload, string $command): void {
    $event = new MenuCommandSelected(...$payload);

    expect($event->command)->toBe($command)
        ->and($event->broadcastOn())->toBe(['nativephp']);
})->with([
    'named' => [['item' => ['id' => 'search'], 'combo' => []], 'search'],
    'positional' => [[['id' => 'graph'], []], 'graph'],
    'no id' => [[['label' => 'x']], ''],
    'garbage' => [['nope'], ''],
    'empty' => [[], ''],
]);
