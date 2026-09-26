<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app cannot do anything without a Places key and a mailbox, so a fresh
 * account is walked through onboarding before it sees the dashboard.
 */
class EnsureOnboarded
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isOnboarded() === false) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
