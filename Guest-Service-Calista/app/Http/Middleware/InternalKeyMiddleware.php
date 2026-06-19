<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalKeyMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $internalKey = $request->header('X-INTERNAL-KEY');
        $validKey = env('INTERNAL_SERVICE_KEY', 'internal-tim-11-iae');

        if (!$internalKey || $internalKey !== $validKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid or missing X-INTERNAL-KEY',
                'errors' => null
            ], 401);
        }

        return $next($request);
    }
}
