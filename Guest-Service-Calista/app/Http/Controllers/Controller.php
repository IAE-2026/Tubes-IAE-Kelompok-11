<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Guest Service API - Hospitality System",
 *      description="Dokumentasi API untuk Guest Service. Dibuat oleh Aurel (102022400289) - Universitas Telkom.",
 * )
 *
 *
 * @OA\SecurityScheme(
 *      securityScheme="ApiKeyAuth",
 *      type="apiKey",
 *      in="header",
 *      name="X-IAE-KEY"
 * )
 */
abstract class Controller
{
    //
}