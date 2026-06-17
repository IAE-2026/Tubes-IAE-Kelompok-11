<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Guest Service API",
    version: "1.0.0",
    description: "API untuk manajemen data tamu hotel. NIM: 102022400289"
)]

#[OA\Server(
    url: "/api/v1",
    description: "API Server"
)]

#[OA\SecurityScheme(
    securityScheme: "ApiKeyAuth",
    type: "apiKey",
    in: "header",
    name: "X-IAE-KEY"
)]

class SwaggerController extends Controller
{
    //
}