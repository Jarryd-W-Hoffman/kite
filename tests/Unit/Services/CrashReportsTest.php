<?php

declare(strict_types=1);

use App\Models\Vault;
use App\Services\CrashReports;
use App\Services\Settings;
use Illuminate\Support\Facades\Route;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;
use Sentry\UserDataBag;

beforeEach(function (): void {
    $this->home = getenv('HOME');
    putenv('HOME=/home/someone');
    putenv('NATIVEPHP_USER_HOME_PATH');
});

afterEach(function (): void {
    putenv($this->home === false ? 'HOME' : "HOME={$this->home}");
});

it('is off by default, and needs an installed build, a DSN and the user', function (): void {
    expect(resolve(CrashReports::class)->enabled())->toBeFalse();

    turnOnCrashReports();
    expect(resolve(CrashReports::class)->enabled())->toBeTrue();

    config(['sentry.dsn' => null]);
    expect(resolve(CrashReports::class)->enabled())->toBeFalse();

    turnOnCrashReports();
    app()->detectEnvironment(fn (): string => 'local');
    expect(resolve(CrashReports::class)->enabled())->toBeFalse();

    turnOnCrashReports();
    resolve(Settings::class)->update(['crash_reports' => false]);
    expect(resolve(CrashReports::class)->enabled())->toBeFalse();
});

it('drops every event while reports are off', function (): void {
    expect(CrashReports::beforeSend(Event::createEvent()))->toBeNull();
});

it('removes anything that could identify the user or their notes', function (): void {
    turnOnCrashReports();
    Vault::factory()->create(['path' => '/home/someone/Notes/Private']);

    $event = Event::createEvent();
    $event->setRequest(['url' => 'http://127.0.0.1:8100/vaults/1', 'data' => ['contents' => 'secret']]);
    $event->setUser(UserDataBag::createFromUserIdentifier('someone'));
    $event->setServerName('someones-laptop');
    $event->setExtra(['note' => 'secret']);
    $event->setMessage('Saving /home/someone/Notes/Private/Plans.md failed');
    $event->setExceptions([new ExceptionDataBag(new RuntimeException('x'), new Stacktrace([
        new Frame('save', '/home/someone/Notes/Private/x.php', 12, null, '/home/someone/Downloads/kite/app.php', ['secret' => 'value']),
    ]))]);
    $event->getExceptions()[0]->setValue('Could not read /home/someone/Notes/Private/Plans.md or /home/someone/other.md');

    $prepared = CrashReports::beforeSend($event);
    $frame = $prepared?->getExceptions()[0]->getStacktrace()?->getFrames()[0];

    expect($prepared?->getRequest())->toBe([])
        ->and($prepared?->getUser())->toBeNull()
        ->and($prepared?->getServerName())->toBeNull()
        ->and($prepared?->getExtra())->toBe([])
        ->and($prepared?->getMessage())->toBe('Saving <vault>/Plans.md failed')
        ->and($prepared?->getExceptions()[0]->getValue())->toBe('Could not read <vault>/Plans.md or ~/other.md')
        ->and($frame?->getFile())->toBe('<vault>/x.php')
        ->and($frame?->getAbsoluteFilePath())->toBe('~/Downloads/kite/app.php')
        ->and($frame?->getVars())->toBe([])
        ->and($frame?->getLine())->toBe(12);
});

it('replaces Windows-style paths too', function (): void {
    turnOnCrashReports();
    putenv('HOME');
    putenv('NATIVEPHP_USER_HOME_PATH=C:\\Users\\Someone');

    expect(resolve(CrashReports::class)->scrub('C:\\Users\\Someone\\AppData\\kite and C:/Users/Someone/x'))->toBe('~\\AppData\\kite and ~/x');

    putenv('NATIVEPHP_USER_HOME_PATH');
});

it('sends interface errors only when reports are on, scrubbed and tagged', function (): void {
    $sent = fakeSentry();

    resolve(CrashReports::class)->reportInterfaceError('Boom', 'at /home/someone/x.js:1');
    expect($sent)->toHaveCount(0);

    turnOnCrashReports();
    resolve(CrashReports::class)->reportInterfaceError('Boom', 'at /home/someone/x.js:1');

    expect($sent)->toHaveCount(1)
        ->and($sent[0]->getMessage())->toBe('Interface error: Boom')
        ->and($sent[0]->getTags())->toMatchArray(['source' => 'interface'])
        ->and($sent[0]->getContexts()['interface'])->toBe(['stack' => 'at ~/x.js:1']);
});

it("reports the server's own errors once turned on, scrubbed", function (): void {
    $sent = fakeSentry();
    Route::get('/kite-test-boom', fn () => throw new RuntimeException('Broke on /home/someone/Notes/a.md'));

    $this->get('/kite-test-boom')->assertServerError();
    expect($sent)->toHaveCount(0);

    turnOnCrashReports();
    $this->get('/kite-test-boom')->assertServerError();

    expect($sent)->toHaveCount(1)
        ->and($sent[0]->getExceptions()[0]->getValue())->toBe('Broke on ~/Notes/a.md')
        ->and($sent[0]->getRequest())->toBe([]);
});
