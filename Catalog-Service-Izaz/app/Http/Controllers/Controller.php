<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    description: "Catalog Service API — Pilih-Pilih Kamar",
    title: "Catalog Service API"
)]
#[OA\Server(url: '/api/v1')]
#[OA\SecurityScheme(
    securityScheme: "ApiKeyAuth",
    type: "apiKey",
    in: "header",
    name: "X-IAE-KEY"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "JWT Bearer token from the central SSO server (Cloud Dosen)"
)]
abstract class Controller
{
    use ApiResponse;
}
