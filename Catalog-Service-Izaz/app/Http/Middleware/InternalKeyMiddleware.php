<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InternalKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $internalKey = $request->header('X-INTERNAL-KEY');

        $expectedKey = env('INTERNAL_KEY', 'internal-tim-11-iae');

        if ($internalKey !== $expectedKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid Internal Key',
                'errors' => null
            ], 401);
        }

        return $next($request);
    }
}
