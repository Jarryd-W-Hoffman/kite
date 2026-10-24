<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only Kite's own scripts may run in its window. Notes are escaped when rendered, but an unlocked
 * vault's key is held in the window's memory, so a script slipped in through a note must not run
 * even if escaping ever misses one.
 *
 * Styles may be inline (KaTeX, Mermaid and the editor set them), and notes may show images and
 * media from the web. Responses that set a policy of their own, such as attachments, keep it.
 */
final readonly class ContentSecurityPolicy
{
    public const string HEADER = 'Content-Security-Policy';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        if (! $response->headers->has(self::HEADER)) {
            $response->headers->set(self::HEADER, $this->policy($nonce));
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        // While developing, Vite serves scripts, styles and fonts from its own server and pushes
        // changes over a websocket.
        $server = Vite::isRunningHot() ? mb_trim((string) file_get_contents(Vite::hotFile())) : '';
        $socket = (string) preg_replace('/^http/', 'ws', $server);

        $directives = [
            'default-src' => "'self'",
            'script-src' => "'self' 'nonce-{$nonce}' {$server}",
            'style-src' => "'self' 'unsafe-inline' {$server}",
            'font-src' => "'self' data: {$server}",
            'img-src' => "'self' data: blob: https: http:",
            'media-src' => "'self' blob: https: http:",
            'connect-src' => "'self' {$server} {$socket}",
            'worker-src' => "'self' blob:",
            'object-src' => "'none'",
            'frame-src' => "'none'",
            'frame-ancestors' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
        ];

        return implode('; ', array_map(
            fn (string $directive, string $sources): string => $directive.' '.mb_trim((string) preg_replace('/\s+/', ' ', $sources)),
            array_keys($directives),
            $directives,
        ));
    }
}
