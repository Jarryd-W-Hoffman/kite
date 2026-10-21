<?php

declare(strict_types=1);

use App\Services\CrashReports;

/*
|--------------------------------------------------------------------------
| Crash reports
|--------------------------------------------------------------------------
|
| Kite sends crash reports to Sentry only when the user has turned them on
| (Preferences → Privacy), and only from installed builds. Every event goes
| through CrashReports::beforeSend(), which drops it otherwise and removes
| anything that could identify the user or their notes. Breadcrumbs,
| tracing, profiling, logs and metrics are all off.
|
*/

return [

    // Public by design: a DSN only lets Kite send events to the project, not read them.
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'release' => env('NATIVEPHP_APP_VERSION', '0.1.0'),

    'environment' => env('APP_ENV', 'production'),

    'sample_rate' => 1.0,

    'traces_sample_rate' => null,

    'profiles_sample_rate' => null,

    'enable_logs' => false,

    'enable_metrics' => false,

    'send_default_pii' => false,

    'before_send' => [CrashReports::class, 'beforeSend'],

    'breadcrumbs' => [
        'logs' => false,
        'cache' => false,
        'livewire' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'queue_info' => false,
        'command_info' => false,
        'http_client_requests' => false,
        'notifications' => false,
    ],

    'tracing' => [
        'default_integrations' => false,
    ],

];
