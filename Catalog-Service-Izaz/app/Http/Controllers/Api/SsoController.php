<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\JwksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * SsoController
 *
 * Provides endpoints related to the Federated SSO module:
 * - Verify a Bearer token and return the decoded payload.
 * - Return the currently authenticated SSO user's profile.
 *
 * These endpoints are useful for debugging and for the frontend
 * to confirm the SSO session is active.
 *
 * @OA\Tag(
 *     name="SSO",
 *     description="Federated SSO authentication endpoints"
 * )
 */
class SsoController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/sso/me",
     *     operationId="ssoMe",
     *     tags={"SSO"},
     *     summary="Get the authenticated SSO user profile",
     *     description="Returns the current user's profile, including their local role, after JWT verification.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Authenticated user profile",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="warga11"),
     *                 @OA\Property(property="email", type="string", example="warga11@ktp.iae.id"),
     *                 @OA\Property(property="sso_sub", type="string", example="abc-123-def"),
     *                 @OA\Property(property="role", type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="viewer"),
     *                     @OA\Property(property="display_name", type="string", example="Viewer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized — missing or invalid Bearer token"
     *     )
     * )
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No authenticated user found.',
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'      => $user->id,
                'name'    => $user->name,
                'email'   => $user->email,
                'sso_sub' => $user->sso_sub,
                'role'    => $user->role ? [
                    'id'           => $user->role->id,
                    'name'         => $user->role->name,
                    'display_name' => $user->role->display_name,
                ] : null,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/sso/verify",
     *     operationId="ssoVerify",
     *     tags={"SSO"},
     *     summary="Verify a JWT token and return decoded payload",
     *     description="Accepts a Bearer token, verifies it against the JWKS, and returns the decoded claims. Useful for debugging.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token is valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Token is valid."),
     *             @OA\Property(property="payload", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Token is invalid or expired"
     *     )
     * )
     */
    public function verify(Request $request, JwksService $jwksService): JsonResponse
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No Bearer token provided.',
            ], 401);
        }

        $payload = $jwksService->verifyToken($token);

        if (!$payload) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token verification failed. Token may be expired or invalid.',
            ], 401);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Token is valid.',
            'payload' => $payload,
        ]);
    }
}
