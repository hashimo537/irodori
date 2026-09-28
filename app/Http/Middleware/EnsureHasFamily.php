<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** まだ家族に入っていない人を、家族づくりの画面へ送る */
class EnsureHasFamily
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->family_id) {
            return redirect()->route('family.setup');
        }

        return $next($request);
    }
}
