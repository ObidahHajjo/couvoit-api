<?php

namespace App\Swagger;

use OpenApi\Annotations as OA;

/**
 * Legacy annotation-based OpenAPI metadata container.
 *
 * @OA\Info(
 *   title="Couvoit API",
 *   version="1.0.0",
 *   description="Covoiturage API - Laravel 12"
 * )
 *
 * @OA\Server(
 *   url="/",
 *   description="Default server"
 * )
 *
 * @OA\SecurityScheme(
 *   securityScheme="bearerAuth",
 *   type="http",
 *   scheme="bearer",
 *   bearerFormat="JWT"
 * )
 */
final class OpenApi {}
