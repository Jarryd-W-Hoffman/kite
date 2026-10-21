<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vault;
use Sentry\Event;
use Sentry\Frame;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\State\Scope;

use function Sentry\captureMessage;
use function Sentry\withScope;

/**
 * Opt-in crash reports. Nothing is sent unless the user turned reports on, Kite is an installed
 * (production) build and a Sentry DSN is configured. What is sent is the error and where in Kite's
 * code it happened, with vault paths and the home folder replaced, and no request, user,
 * breadcrumb, server or local variable data.
 */
final readonly class CrashReports
{
    public function __construct(private Settings $settings)
    {
        //
    }

    /**
     * Sentry's `before_send` hook: every event passes through here before it can leave the machine.
     */
    public static function beforeSend(Event $event): ?Event
    {
        return resolve(self::class)->prepare($event);
    }

    public function enabled(): bool
    {
        return app()->isProduction()
            && filled(config('sentry.dsn'))
            && $this->settings->all()['crash_reports'] === true;
    }

    /**
     * The event as it may be sent, or null when reports are off.
     */
    public function prepare(Event $event): ?Event
    {
        if (! $this->enabled()) {
            return null;
        }

        $event->setRequest([]);
        $event->setUser(null);
        $event->setBreadcrumb([]);
        $event->setServerName(null);
        $event->setExtra([]);

        foreach ($event->getExceptions() as $exception) {
            $exception->setValue($this->scrub($exception->getValue()));

            $stacktrace = $exception->getStacktrace();

            if ($stacktrace instanceof Stacktrace) {
                $exception->setStacktrace($this->scrubStacktrace($stacktrace));
            }
        }

        $stacktrace = $event->getStacktrace();

        if ($stacktrace instanceof Stacktrace) {
            $event->setStacktrace($this->scrubStacktrace($stacktrace));
        }

        $message = $event->getMessage();

        if ($message !== null) {
            $event->setMessage($this->scrub($message));
        }

        return $event;
    }

    /**
     * Report an error from Kite's interface, which the server can't see on its own.
     */
    public function reportInterfaceError(string $message, ?string $stack): void
    {
        if (! $this->enabled()) {
            return;
        }

        withScope(function (Scope $scope) use ($message, $stack): void {
            $scope->setTag('source', 'interface');
            $scope->setContext('interface', ['stack' => $this->scrub((string) $stack)]);

            captureMessage('Interface error: '.$message, Severity::error());
        });
    }

    /**
     * Replace vault paths and the home folder, which can carry names, with placeholders.
     */
    public function scrub(string $text): string
    {
        $replacements = [];

        foreach (Vault::query()->pluck('path') as $path) {
            if (is_string($path) && $path !== '') {
                $replacements[$path] = '<vault>';
                $replacements[str_replace('\\', '/', $path)] = '<vault>';
            }
        }

        foreach (['NATIVEPHP_USER_HOME_PATH', 'HOME', 'USERPROFILE'] as $variable) {
            $home = getenv($variable);

            if (is_string($home) && mb_strlen($home) > 1) {
                $replacements[$home] = '~';
                $replacements[str_replace('\\', '/', $home)] = '~';
            }
        }

        // strtr() tries the longest match first, so a vault inside the home folder becomes <vault>.
        return strtr($text, $replacements);
    }

    private function scrubStacktrace(Stacktrace $stacktrace): Stacktrace
    {
        return new Stacktrace(array_map(function (Frame $frame): Frame {
            $absolute = $frame->getAbsoluteFilePath();

            $scrubbed = new Frame(
                $frame->getFunctionName(),
                $this->scrub($frame->getFile()),
                $frame->getLine(),
                $frame->getRawFunctionName(),
                $absolute === null ? null : $this->scrub($absolute),
                [],
                $frame->isInApp(),
            );

            $scrubbed->setPreContext($frame->getPreContext());
            $scrubbed->setContextLine($frame->getContextLine());
            $scrubbed->setPostContext($frame->getPostContext());

            return $scrubbed;
        }, $stacktrace->getFrames()));
    }
}
