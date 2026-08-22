<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCinemaWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.cinema_webhook.token');
        $provided = $request->header('X-Webhook-Token', '');

        if (! $expected || ! hash_equals($expected, $provided)) {
            abort(401, 'Invalid webhook token.');
        }

        return $next($request);
    }
}
