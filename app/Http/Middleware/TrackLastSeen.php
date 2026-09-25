<?php

namespace App\Http\Middleware;

use App\Support\Presence;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Позначає учасника активним (users.last_seen_at) — ПІСЛЯ обробки
 * запиту: лише тоді route-middleware auth:sanctum уже визначив, хто це,
 * і $request->user() повертає власника токена, а не null.
 */
class TrackLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Статус — другорядний: збій запису (напр., між git pull і
        // migrate ще немає колонки) не повинен зламати сам запит.
        try {
            if ($user = $request->user()) {
                Presence::touch($user);
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $response;
    }
}
