<?php

declare(strict_types=1);

use App\Events\MenuCommandSelected;
use App\Services\KeyBindings;
use App\Services\Settings;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

it("shares each command's shortcut, changed or not", function (): void {
    $this->from(route('vaults.index'))
        ->put(route('key-bindings.update'), ['bindings' => ['search' => 'CmdOrCtrl+Shift+P', 'graph' => '']])
        ->assertRedirectToRoute('vaults.index')
        ->assertSessionHasNoErrors();

    $this->get(route('vaults.index'))->assertInertia(fn ($page) => $page
        ->where('kite.key_bindings.search', 'CmdOrCtrl+Shift+P')
        ->where('kite.key_bindings.graph', '')
        ->where('kite.key_bindings.new-note', 'CmdOrCtrl+N')
        ->where('kite.default_key_bindings.search', 'CmdOrCtrl+K'));

    $this->put(route('key-bindings.update'), ['bindings' => ['search' => null, 'graph' => 'CmdOrCtrl+G']]);

    expect(resolve(KeyBindings::class)->all())->toBe(resolve(KeyBindings::class)->defaults())
        ->and(resolve(Settings::class)->all()['key_bindings'])->toBe([]);
});

it("won't give a command a shortcut something else uses", function (array $bindings, string $command, string $message): void {
    $this->put(route('key-bindings.update'), ['bindings' => ['graph' => 'CmdOrCtrl+Shift+K']]);

    $this->put(route('key-bindings.update'), ['bindings' => $bindings])
        ->assertSessionHasErrors(["bindings.{$command}" => $message]);
})->with([
    "the editor's" => [['search' => 'CmdOrCtrl+B'], 'search', 'Already used for Bold.'],
    "another command's" => [['search' => 'CmdOrCtrl+N'], 'search', 'Already used for New note.'],
    "a changed command's" => [['search' => 'CmdOrCtrl+Shift+K'], 'search', 'Already used for Graph view.'],
    'a default being restored' => [['search' => 'CmdOrCtrl+G', 'graph' => null], 'search', 'Already used for Graph view.'],
    'two at once' => [['search' => 'CmdOrCtrl+J', 'graph' => 'CmdOrCtrl+J'], 'graph', 'Already used for Search.'],
]);

it('only takes command shortcuts it can register', function (array $bindings): void {
    $this->put(route('key-bindings.update'), ['bindings' => $bindings])->assertSessionHasErrors();

    expect(resolve(Settings::class)->all()['key_bindings'])->toBe([]);
})->with([
    'no command key' => [['search' => 'Shift+K']],
    'just a modifier' => [['search' => 'CmdOrCtrl+Shift']],
    'an unknown key' => [['search' => 'CmdOrCtrl+Space']],
    'lower case' => [['search' => 'CmdOrCtrl+k']],
    'an unknown command' => [['format-disk' => 'CmdOrCtrl+J']],
    'nothing' => [[]],
]);

it('swaps two shortcuts in one go, and rebuilds the menu in the desktop app', function (): void {
    config(['nativephp-internal.running' => true, 'nativephp-internal.secret' => 'secret']);
    Http::fake();

    $this->withHeader('X-NativePHP-Secret', 'secret')
        ->put(route('key-bindings.update'), ['bindings' => ['search' => 'CmdOrCtrl+G', 'graph' => 'CmdOrCtrl+K', 'toggle-theme' => '']])
        ->assertSessionHasNoErrors();

    Http::assertSent(function (ClientRequest $request): bool {
        if (! str_ends_with($request->url(), 'menu')) {
            return false;
        }

        $commands = collect($request['items'])
            ->flatMap(fn (array $item): array => $item['submenu'] ?? [$item])
            ->where('event', MenuCommandSelected::class)
            ->keyBy('id');

        return $commands['search']['accelerator'] === 'CmdOrCtrl+G'
            && $commands['graph']['accelerator'] === 'CmdOrCtrl+K'
            && ! isset($commands['toggle-theme']['accelerator']);
    });
});

it("ignores stored shortcuts it can't use", function (): void {
    resolve(Settings::class)->update(['key_bindings' => ['search' => 'Banana', 'graph' => '', 'gone' => 'CmdOrCtrl+J', 'tasks' => 3]]);

    expect(resolve(KeyBindings::class)->overrides())->toBe(['graph' => '']);

    resolve(Settings::class)->update(['key_bindings' => 'nonsense']);

    expect(resolve(KeyBindings::class)->overrides())->toBe([]);
});
