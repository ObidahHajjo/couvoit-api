<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

/**
 * Holds top-level OpenAPI metadata annotations.
 */
#[OA\OpenApi(openapi: '3.0.0')]
#[OA\Info(version: '1.0.0', title: 'Couvoit API')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    bearerFormat: 'JWT',
    scheme: 'bearer'
)]
final class Bootstrap {}
