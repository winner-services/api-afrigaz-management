<?php

namespace App\Http\Controllers\Api\Swagger;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    info: new OA\Info(
        version: '1.0.0',
        title: 'API AFRIGAZ',
        description: 'Documentation API AFRIGAZ',
        contact: new OA\Contact(email: 'winnersthec001.com')
    )
)]
class SwaggerInfo {}