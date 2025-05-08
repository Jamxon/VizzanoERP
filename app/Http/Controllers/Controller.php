<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use OpenApi\Attributes as OA;


#[
    OA\Info(version: "1.0.0", description: "petshop api", title: "Petshop-api Documentation"),
    OA\Server(url: 'http://192.168.0.107:8000', description: "local server"),
    OA\Server(url: 'http://staging.example.com', description: "staging server"),
    OA\Server(url: 'http://example.com', description: "production server"),
    OA\SecurityScheme( securityScheme: 'bearerAuth', type: "http", name: "Authorization", in: "header", scheme: "bearer"),
]

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * @OA\Info(
     *     title="VizzanoERP API",
     *     version="1.0.0",
     *     description="VizzanoERP uchun API hujjatlari",
     *     @OA\Contact(
     *         email="support@vizzanoerp.com"
     *     )
     * )
     *
     * @OA\Server(
     *     url="http://192.168.0.107:8000",
     *     description="Local server"
     * )
     */

}
