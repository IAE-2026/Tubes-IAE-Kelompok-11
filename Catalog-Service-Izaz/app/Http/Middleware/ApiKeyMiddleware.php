<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class ApiKeyMiddleware
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $apiKey = $request->header('X-IAE-KEY');

        if ($apiKey !== '102022400306') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
                'errors' => null
            ], 401);
        }

        return $next($request);
    }
}
