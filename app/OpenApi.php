<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Cosmic Tiger API',
    description: 'Social media API with posts, comments, likes, and feeds.',
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    description: 'Enter your Sanctum token',
)]
class OpenApi {}
