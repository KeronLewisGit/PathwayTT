<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Livewire binds `#[Url]` properties straight from the query string before
 * mount() runs, so `/jobs?q[]=x` would fatally try to assign an array to a
 * string property. Nothing in the app takes array query parameters; drop
 * them so the page renders with its defaults instead of a 500.
 */
class RejectArrayQueryStrings
{
    public function handle(Request $request, Closure $next): Response
    {
        $query = $request->query();
        $scalars = array_filter($query, fn ($value) => ! is_array($value));

        if (count($scalars) !== count($query)) {
            $request->query->replace($scalars);
            $request->server->set('QUERY_STRING', http_build_query($scalars));
        }

        return $next($request);
    }
}
