<?php

declare(strict_types=1);

use App\Services\Settings;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Inertia\Testing\AssertableInertia;

it('passes interface errors on to crash reports', function (): void {
    $sent = fakeSentry();
    turnOnCrashReports();
    // Production checks CSRF, which the app's own requests carry; this test skips it.
    $this->withoutMiddleware(PreventRequestForgery::class);

    $this->postJson(route('crash-reports.store'), ['message' => 'Boom', 'stack' => 'at x.js:1'])->assertNoContent();
    $this->postJson(route('crash-reports.store'), ['message' => 'No stack'])->assertNoContent();

    expect($sent)->toHaveCount(2)
        ->and($sent[0]->getMessage())->toBe('Interface error: Boom')
        ->and($sent[0]->getContexts()['interface'])->toBe(['stack' => 'at x.js:1'])
        ->and($sent[1]->getContexts()['interface'])->toBe(['stack' => '']);
});

it('sends nothing while crash reports are off', function (): void {
    $sent = fakeSentry();

    $this->postJson(route('crash-reports.store'), ['message' => 'Boom'])->assertNoContent();

    expect($sent)->toHaveCount(0);
});

it('validates interface errors', function (): void {
    $this->postJson(route('crash-reports.store'), ['message' => str_repeat('x', 1001)])
        ->assertJsonValidationErrors('message');
});

it('asks once on the Welcome tab, only with somewhere to send reports', function (): void {
    $vault = vaultWithFiles();

    $this->get(route('welcome.show', $vault))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('ask_crash_reports', false));

    config(['sentry.dsn' => 'https://public@sentry.example/1']);

    $this->get(route('welcome.show', $vault))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('ask_crash_reports', true));

    $this->from(route('welcome.show', $vault))
        ->put(route('crash-report-consent.update'), ['crash_reports' => true])
        ->assertRedirect(route('welcome.show', $vault));

    expect(resolve(Settings::class)->all()['crash_reports'])->toBeTrue();

    $this->get(route('welcome.show', $vault))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('ask_crash_reports', false));
});

it('keeps reports off when the answer is no', function (): void {
    $this->put(route('crash-report-consent.update'), ['crash_reports' => false]);

    expect(resolve(Settings::class)->all()['crash_reports'])->toBeFalse()
        ->and(resolve(Settings::class)->state('crash_reports_asked'))->toBeTrue();
});

it('validates the answer', function (): void {
    $this->put(route('crash-report-consent.update'), ['crash_reports' => 'maybe'])
        ->assertSessionHasErrors('crash_reports');
});
